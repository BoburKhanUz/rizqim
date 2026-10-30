import 'dart:async';
import 'dart:math';

import 'package:pos_core/pos_core.dart';
import 'package:pos_sync/pos_sync.dart';
import 'package:rizqim_api/rizqim_api.dart';

import '../billing/billing_models.dart';
import '../bootstrap/bootstrap_models.dart';
import '../catalog/catalog_models.dart';
import '../catalog/combo_models.dart';
import '../floor/floor_models.dart';
import '../format/format_models.dart';
import '../http/connectivity.dart';
import '../http/pos_exceptions.dart';
import '../http/pos_http.dart';
import '../json.dart';
import '../orders/course_models.dart';
import '../orders/order_models.dart';
import 'offline_book.dart';
import 'offline_pricing.dart';
import 'offline_settings.dart';
import 'sync_models.dart';

/// `POST /pos/sync` terminal tokeni bilan ([AuthScope.terminal]).
class PosSyncTransport implements SyncTransport {
  PosSyncTransport(this._http);

  final PosHttp _http;

  @override
  Future<SyncBatchResponse> send(SyncBatchRequest request) async {
    try {
      final PosResponse r = await _http.raw(
        'POST',
        '/v1/pos/sync',
        body: request.toJson(),
        scope: AuthScope.terminal,
        idempotent: false,
      );
      return SyncBatchResponse.fromJson(PosJson.of(r.data, 'data').map);
    } on PosNetworkException catch (e) {
      throw SyncUnreachable(e);
    } on PosException catch (e) {
      throw SyncRefused(e.message, status: e.statusCode, reason: e.reason);
    }
  }
}

/// Kassaning internetsiz cheklangan rejimi (docs/POS_SPEC.md 5-bo'lim) `pos_data` ichida.
///
/// * **Qachon:** server javob bermayapti ([PosConnectivity]) yoki navbatda serverga hali yetmagan amal bor. Shu payt
///   repozitoriylar (`orders`, `billing`, `floor`) HTTP o'rniga shu yerga keladi: amal [SyncStore] navbatiga yoziladi
///   (diskka), natija — lokal ko'rinish ([OfflineOrderBook]) serverning o'z JSON shaklida.
/// * **Ruxsat etilgan amallar (5.1):** buyurtma ochish (olib ketish yoki zal, xona), bosqich, oshxonaga yuborish,
///   yuborilmagan qatorni o'chirish, "berildi", bitta schyot, schyotni chop etish (pre-check), naqd yoki karta
///   to'lovi, yopish. Qolgani [PosOfflineUnavailable] (sababi bilan).
/// * **Chegaralar (5.4):** so'nggi 24 soatda aloqa va 24 soatdan yangi menyu bo'lmasa — sotuv yo'q; navbatda 500
///   buyurtma — yangisi yo'q; smena va Z — navbat bo'shaguncha yo'q.
/// * **Narx:** menyu nusxasidan, oddiy qoidalar ([OfflinePricing]).
/// * **Sinxronlash:** [SyncSender] navbatni tartib bilan yuboradi; aloqa yo'q paytda har 15–60 soniyada server
///   tekshiriladi. Navbat bo'shagach zal va tegishli buyurtmalar serverdan qayta olinadi.
class PosOffline {
  PosOffline({
    required this.store,
    required PosHttp http,
    DateTime Function()? clock,
    Random? random,
    SyncTransport? transport,
    this.heartbeat = const Duration(seconds: 15),
  }) : _http = http,
       _clock = clock ?? DateTime.now {
    book = OfflineOrderBook(store: store, settings: () => settings);
    sender = SyncSender(
      store: store,
      transport: transport ?? PosSyncTransport(http),
      clock: _clock,
      random: random,
      onDrained: _afterDrain,
    );
    _subs
      ..add(connectivity.changes.listen(_onConnectivity))
      ..add(store.changes.listen((_) => _emit()))
      ..add(sender.changes.listen((_) => _emit()));
    final Map<String, Object?>? boot = store.doc(_bootstrapKey)?.map;
    if (boot != null) settings = PosOfflineSettings.of(boot);
    // Answers from before this start were emitted then (and their receipts are in the store's own list)
    _emitted.addAll(store.answered.map((SyncOperation o) => o.opId));
    sender.start();
  }

  final SyncStore store;
  final PosHttp _http;
  final DateTime Function() _clock;
  final Duration heartbeat;
  late final OfflineOrderBook book;
  late final SyncSender sender;

  /// Kim bajaryapti va qaysi smenada (PosData bog'laydi).
  int? Function() staffId = () => null;
  String? Function() staffName = () => null;
  bool Function(String permission) can = (_) => true;
  String? Function() shiftUuid = () => null;
  PosBootstrap? Function() bootstrap = () => null;
  PosCatalog? Function(PosChannel channel) catalog = (_) => null;

  PosOfflineSettings settings = const PosOfflineSettings();

  final List<StreamSubscription<Object?>> _subs =
      <StreamSubscription<Object?>>[];
  final StreamController<OfflineStatus> _changes =
      StreamController<OfflineStatus>.broadcast();
  final StreamController<List<SyncOperation>> _synced =
      StreamController<List<SyncOperation>>.broadcast();
  Timer? _probe;
  Timer? _tick;
  int _probeFailures = 0;
  bool _warming = false;

  final Set<String> _emitted = <String>{};

  static const String _bootstrapKey = 'bootstrap';
  static const String _salesKey = '_sale_receipts';

  /// Shuncha chop etilgan chek `op_id` si eslab qolinadi (qayta chiqmasin).
  static const int keepPrintedSales = 500;

  PosConnectivity get connectivity => _http.connectivity;

  /// Cheklangan rejim: aloqa yo'q yoki navbat hali bo'shamagan.
  bool get active => !connectivity.state.online || store.hasPending;

  /// UI holati (aloqa, navbat, yoshi, ogohlantirishlar, nima mumkin emasligi).
  OfflineStatus get status => OfflineStatus.evaluate(
    now: _clock(),
    limits: settings.limits,
    online: connectivity.state.online,
    queueSize: store.pendingCount,
    queuedOrders: store.pendingOrders,
    errorsCount: store.errors.length,
    oldestPendingAt: store.oldestPendingAt,
    oldestUnsyncedSaleAt: store.oldestUnsyncedSaleAt,
    lastContactAt: store.lastContactAt ?? connectivity.state.lastContactAt,
    menuTakenAt: _menuTakenAt,
    syncing: sender.syncing,
    clockWarning: sender.clockWarning,
    lastSyncError: sender.lastError is SyncRefused
        ? (sender.lastError! as SyncRefused).message
        : null,
  );

  Stream<OfflineStatus> get changes => _changes.stream;

  /// Navbatdan serverga yetgan amallar — faqat shu yuborishda javob olganlari (har yuborishdan keyin). Sotuv chekini
  /// chop etish uchun [salesToPrint] ni o'qing.
  Stream<List<SyncOperation>> get synced => _synced.stream;

  /// Oflayn to'langan, serverga yetgan va sotuv cheki hali chop etilmagan schyotlar (diskda saqlanadi: ilova qayta
  /// ochilsa yoki xodim almashsa ham yo'qolmaydi va qayta chiqmaydi). Chek chiqqach [salePrinted].
  List<PosOfflineSale> get salesToPrint => <PosOfflineSale>[
    for (final Object? s
        in (store.doc(_salesKey)?.map?['todo'] as List<Object?>?) ??
            const <Object?>[])
      if (s is Map) PosOfflineSale.fromJson(s.cast<String, Object?>()),
  ];

  /// Chek chop etildi: ro'yxatdan olinadi.
  Future<void> salePrinted(String opId) async {
    final List<PosOfflineSale> todo = salesToPrint;
    final int before = todo.length;
    todo.removeWhere((PosOfflineSale s) => s.opId == opId);
    if (todo.length == before) return;
    await _writeSales(todo, _salesSeen());
  }

  List<String> _salesSeen() => <String>[
    for (final Object? id
        in (store.doc(_salesKey)?.map?['seen'] as List<Object?>?) ??
            const <Object?>[])
      '$id',
  ];

  Future<void> _writeSales(List<PosOfflineSale> todo, List<String> seen) =>
      store.putDoc(_salesKey, <String, Object?>{
        'todo': <Object?>[for (final PosOfflineSale s in todo) s.toJson()],
        'seen': seen.length > keepPrintedSales
            ? seen.sublist(seen.length - keepPrintedSales)
            : seen,
      });

  /// Serverga yetgan `bill.pay` amallari chop etish ro'yxatiga (bir marta).
  Future<void> _recordSales(List<SyncOperation> answered) async {
    final List<String> seen = _salesSeen();
    final Set<String> known = seen.toSet();
    final List<PosOfflineSale> todo = salesToPrint;
    var added = false;
    for (final SyncOperation op in answered) {
      if (op.type != SyncOpType.billPay ||
          op.status != SyncOpStatus.applied ||
          known.contains(op.opId)) {
        continue;
      }
      final String? order = op.refs['order_uuid'] as String?;
      final String? bill = op.refs['bill_uuid'] as String?;
      if (order == null || bill == null) continue;
      todo.add(PosOfflineSale(opId: op.opId, orderUuid: order, billUuid: bill));
      seen.add(op.opId);
      known.add(op.opId);
      added = true;
    }
    if (added) await _writeSales(todo, seen);
  }

  DateTime? get _menuTakenAt {
    DateTime? newest;
    for (final String key in store.docKeys(prefix: 'catalog:')) {
      final DateTime t = store.doc(key)!.takenAt;
      if (newest == null || t.isAfter(newest)) newest = t;
    }
    return newest;
  }

  // --- nusxalar (onlayn javoblardan) ------------------------------------------------------------------------------

  Future<void> captureBootstrap(Map<String, Object?> raw) async {
    settings = PosOfflineSettings.of(raw);
    sender.limits = settings.limits;
    await _put(_bootstrapKey, raw);
  }

  Future<void> captureCatalog(PosChannel channel, Object? raw) =>
      _put('catalog:${channel.wire}', raw);

  /// Serverdagi menyu versiyasi qurilmadagi bilan bir xil (bootstrap tasdiqladi): nusxa yangi hisoblanadi.
  Future<void> confirmCatalog(PosChannel channel, String menuVersion) async {
    final SyncDoc? d = store.doc('catalog:${channel.wire}');
    if (d == null || d.map?['menu_version'] != menuVersion) return;
    if (_clock().difference(d.takenAt) < const Duration(minutes: 10)) return;
    await _put('catalog:${channel.wire}', d.value);
  }

  /// Qurilmadagi menyu nusxasi (aloqa yo'q paytda ilova qayta ochilganda).
  PosCatalog? storedCatalog(PosChannel channel) {
    final SyncDoc? d = store.doc('catalog:${channel.wire}');
    final Map<String, Object?>? m = d?.map;
    if (d == null || m == null) return null;
    try {
      return PosCatalog.fromJson(PosJson(m), fetchedAt: d.takenAt);
    } on FormatException {
      return null;
    }
  }

  Future<void> captureFloor(Map<String, Object?> raw) async {
    await _put(OfflineOrderBook.floorKey, raw);
    unawaited(_warm(raw));
  }

  Future<void> captureOrder(Map<String, Object?> raw) async {
    final Object? uuid = raw['uuid'];
    if (uuid is String) await _put(OfflineOrderBook.orderKey(uuid), raw);
  }

  Future<void> captureBilling(String orderUuid, Object? raw) =>
      _put(OfflineOrderBook.billingKey(orderUuid), raw);

  Future<void> captureOpenOrders(Object? raw) =>
      _put(OfflineOrderBook.openOrdersKey, raw);

  /// Oxirgi bootstrap nusxasi (ilova internetsiz qayta ochilganda), 24 soatdan eski bo'lmasa.
  PosBootstrap? storedBootstrap() {
    final SyncDoc? d = store.doc(_bootstrapKey);
    final Map<String, Object?>? m = d?.map;
    if (d == null ||
        m == null ||
        d.age(_clock()) > settings.limits.contactMaxAge) {
      return null;
    }
    try {
      return PosBootstrap.fromJson(PosJson(m), receivedAt: d.takenAt);
    } on FormatException {
      return null;
    }
  }

  Future<void> _put(String key, Object? value) async {
    // Only while the server state includes every queued operation: a copy taken during the drain would be ahead of
    // or behind the queue
    if (store.hasPending) return;
    try {
      await store.putDoc(key, value);
    } on Object {
      // Not saved: the previous copy stays
    }
  }

  /// Zaldagi ochiq buyurtmalarning to'liq nusxasi (uzilishda ochiq xona hisoblari qurilmada bo'lsin).
  Future<void> _warm(Map<String, Object?> floor) async {
    if (_warming || active) return;
    _warming = true;
    try {
      final List<Map<String, Object?>> orders = <Map<String, Object?>>[
        for (final Object? h
            in (floor['halls'] as List<Object?>?) ?? const <Object?>[])
          if (h is Map)
            for (final Object? t
                in (h['tables'] as List<Object?>?) ?? const <Object?>[])
              if (t is Map)
                for (final Object? o
                    in (t['orders'] as List<Object?>?) ?? const <Object?>[])
                  if (o is Map) o.cast<String, Object?>(),
        for (final Object? o
            in (floor['untabled_orders'] as List<Object?>?) ??
                const <Object?>[])
          if (o is Map) o.cast<String, Object?>(),
      ];
      var fetched = 0;
      for (final Map<String, Object?> o in orders) {
        if (active || fetched >= 40) break;
        final String? uuid = o['uuid'] as String?;
        if (uuid == null) continue;
        final int? cached =
            (store.doc(OfflineOrderBook.orderKey(uuid))?.map?['version']
                    as num?)
                ?.toInt();
        if (cached != null && cached == (o['version'] as num?)?.toInt()) {
          continue;
        }
        try {
          final Object? data = (await _http.raw(
            'GET',
            '/v1/pos/orders/$uuid',
            idempotent: false,
          )).data;
          if (data is Map) await captureOrder(data.cast<String, Object?>());
          fetched++;
        } on PosException {
          break;
        }
      }
    } finally {
      _warming = false;
    }
  }

  // --- amallar ----------------------------------------------------------------------------------------------------

  /// Buyurtma ochish (olib ketish yoki zal; xona yoki stol bo'lsa [tableId]).
  Future<PosOrder> open({
    required PosChannel channel,
    int? tableId,
    int? guestsCount,
    String? externalId,
    String? note,
    List<PosLineInput> items = const <PosLineInput>[],
    bool send = false,
    String? idempotencyKey,
    int? queueNumber,
    String? tableLabel,
  }) async {
    _guard('orders.manage');
    if (status.newOrdersBlocked) {
      throw const PosOfflineUnavailable(
        "Navbatda 500 ta oflayn buyurtma bor: yangi buyurtma internet tiklangach ochiladi.",
      );
    }
    final PosBootstrap? boot = bootstrap();
    final PosTable? table = tableId == null ? null : _table(boot, tableId);
    if (tableId != null && table == null) {
      throw const PosOfflineUnavailable("Stol yoki xona kassada topilmadi.");
    }
    if (table != null &&
        table.kind == FloorTableKind.room &&
        _roomBusy(tableId!)) {
      throw const PosOfflineUnavailable('Xona band: unda ochiq buyurtma bor.');
    }
    final PosHall? hall = table == null ? null : _hall(boot, table.hallId);
    final int guests = channel == PosChannel.hall
        ? max(1, guestsCount ?? 1)
        : (guestsCount ?? 0);
    final String uuid = externalId ?? newUuid();
    final PosCatalog cat = _catalogOf(channel);
    final String local = store.nextLocalNumber(
      settings.devicePrefix ?? 'K',
      serverNext: settings.nextLocalNumber,
    );
    await store.enqueue(SyncOpType.orderOpen, <String, Object?>{
      'order': uuid,
      'local_number': local,
      // P6-13: the talon's number from this till's block (the server keeps it or answers queue_number_taken)
      'queue_number': ?queueNumber,
      'channel': channel.wire,
      'table_id': ?tableId,
      'guests_count': guests,
      'note': ?note,
      // P6-25: the table plate of a counter order carried to the table
      'table_label': ?tableLabel,
      'items': <Object?>[for (final PosLineInput i in items) _line(cat, i)],
      if (send) 'send': true,
      'menu_taken_at': ?store
          .doc('catalog:${channel.wire}')
          ?.takenAt
          .toUtc()
          .toIso8601String(),
      'idempotency_key': ?idempotencyKey,
      // For the till's own view only (the server reads the order from its own tables)
      'view': <String, Object?>{
        'store_id': boot?.store.id,
        'branch_id': boot?.branch.id,
        'hall': hall == null
            ? null
            : <String, Object?>{'id': hall.id, 'name': hall.name},
        'table': table == null
            ? null
            : <String, Object?>{
                'id': table.id,
                'name': table.name,
                'kind': table.kind.wire,
              },
        'staff_name': staffName(),
      },
      'auto_items': channel == PosChannel.hall
          ? _autoItems(cat, hall?.id, guests)
          : const <Object?>[],
    }, staffId: _staff());
    return order(uuid);
  }

  /// Yangi bosqich (mavjud buyurtmaga, uzilishdan oldingisiga ham).
  Future<PosOrder> addRound(
    String uuid, {
    required List<PosLineInput> items,
    PosFireMode? fireMode,
    bool send = false,
    String? idempotencyKey,
  }) async {
    _guard('orders.manage');
    final PosOrder current = order(uuid);
    if (!current.isOpen) {
      throw const PosOfflineUnavailable('Buyurtma yopilgan.');
    }
    final PosCatalog cat = _catalogOf(current.posChannel ?? PosChannel.hall);
    await store.enqueue(SyncOpType.orderRound, <String, Object?>{
      'order': uuid,
      'base_version': ?_serverVersion(uuid),
      'items': <Object?>[for (final PosLineInput i in items) _line(cat, i)],
      'fire_mode': ?fireMode?.wire,
      if (send) 'send': true,
      'idempotency_key': ?idempotencyKey,
      'view': <String, Object?>{'staff_name': staffName()},
    }, staffId: _staff());
    return order(uuid);
  }

  Future<PosOrder> send(
    String uuid, {
    List<int>? itemIds,
    String? idempotencyKey,
  }) async {
    _guard('orders.manage');
    final PosOrder current = order(uuid);
    final List<Object?>? lines = itemIds == null
        ? null
        : <Object?>[for (final int id in itemIds) _lineRef(uuid, id)];
    final bool nothing = itemIds == null
        ? !current.unsentItems.any(
            (PosOrderItem i) => i.fireMode != PosFireMode.hold,
          )
        : itemIds.isEmpty;
    if (nothing) {
      throw const PosOfflineUnavailable(
        "Oshxonaga yuboriladigan yangi taom yo'q.",
        reason: 'nothing_to_send',
      );
    }
    await store.enqueue(SyncOpType.orderSend, <String, Object?>{
      'order': uuid,
      'base_version': ?_serverVersion(uuid),
      'lines': ?lines,
      'idempotency_key': ?idempotencyKey,
    }, staffId: _staff());
    return order(uuid);
  }

  /// P6-21: "N-kursni yuborish" — kursning ushlab turilgan qatorlari oshxonaga.
  Future<PosOrder> sendCourse(
    String uuid,
    int courseNo, {
    String? idempotencyKey,
  }) async {
    _guard('orders.manage');
    final PosOrder current = order(uuid);
    final bool any = current.unsentItems.any(
      (PosOrderItem i) => i.courseNo == courseNo,
    );
    if (!any) {
      throw PosOfflineUnavailable(
        "$courseNo-kursda oshxonaga yuboriladigan taom yo'q.",
        reason: 'nothing_to_send',
      );
    }
    await store.enqueue(SyncOpType.orderCourseSend, <String, Object?>{
      'order': uuid,
      'base_version': ?_serverVersion(uuid),
      'course_no': courseNo,
      'idempotency_key': ?idempotencyKey,
    }, staffId: _staff());
    return order(uuid);
  }

  /// P6-25: og'irligi keyin kiritiladigan qatorning haqiqiy og'irligi (bir marta; qayta tortish — internet va menejer).
  Future<PosOrder> setWeight(
    String uuid,
    int itemId, {
    required PosQuantity quantity,
    String? idempotencyKey,
  }) async {
    _guard('orders.manage');
    final PosOrderItem? item = order(uuid).item(itemId);
    if (item == null) throw const PosOfflineUnavailable('Taom topilmadi.');
    if (item.weightStatus != PosWeightStatus.awaiting) {
      throw const PosOfflineUnavailable(
        "Og'irlikni qayta kiritish uchun internet va menejer tasdig'i kerak.",
      );
    }
    final Object? ref = _lineRef(uuid, itemId);
    if (ref == null) {
      throw const PosOfflineUnavailable(
        "Bu qatorning og'irligini internet tiklangach kiriting.",
      );
    }
    await store.enqueue(SyncOpType.orderWeight, <String, Object?>{
      'order': uuid,
      'base_version': ?_serverVersion(uuid),
      'line': ref,
      'quantity': quantity.toApiString(),
      'idempotency_key': ?idempotencyKey,
      'view': <String, Object?>{'staff_name': staffName()},
    }, staffId: _staff());
    return order(uuid);
  }

  /// Faqat oshxonaga yuborilmagan qator (yuborilganini o'chirish uchun menejer tasdig'i — internet kerak).
  Future<PosOrder> voidItem(
    String uuid,
    int itemId, {
    String? reason,
    String? idempotencyKey,
  }) async {
    _guard('orders.manage');
    final PosOrderItem? item = order(uuid).item(itemId);
    if (item == null) throw const PosOfflineUnavailable('Taom topilmadi.');
    if (item.wasSent) {
      throw const PosOfflineUnavailable(
        "Oshxonaga yuborilgan taomni internetsiz o'chirib bo'lmaydi: menejer tasdig'i kerak.",
      );
    }
    final Object? ref = _lineRef(uuid, itemId);
    if (ref == null) {
      throw const PosOfflineUnavailable(
        "Bu qatorni (choy-non) internet tiklangach o'chiring.",
      );
    }
    await store.enqueue(SyncOpType.orderVoid, <String, Object?>{
      'order': uuid,
      'base_version': ?_serverVersion(uuid),
      'line': ref,
      'reason': ?reason,
      'idempotency_key': ?idempotencyKey,
    }, staffId: _staff());
    return order(uuid);
  }

  Future<PosOrder> markServed(
    String uuid,
    int itemId, {
    String? idempotencyKey,
  }) async {
    _guard('orders.manage');
    final Object? ref = _lineRef(uuid, itemId);
    if (ref == null) {
      throw const PosOfflineUnavailable(
        "Bu qatorni internet tiklangach belgilang.",
      );
    }
    await store.enqueue(SyncOpType.orderServed, <String, Object?>{
      'order': uuid,
      'base_version': ?_serverVersion(uuid),
      'line': ref,
      'idempotency_key': ?idempotencyKey,
    }, staffId: _staff());
    return order(uuid);
  }

  /// Bitta schyot (bo'lish — internet kerak).
  Future<PosBilling> createBill(
    String orderUuid, {
    String? idempotencyKey,
  }) async {
    if (!can('orders.manage') && !can('pos.take_payment')) {
      _guard('pos.take_payment');
    }
    _guard(null);
    final PosOrder current = order(orderUuid);
    // P6-25: no bill while a line waits for its weight (as on the server: 409 weight_pending)
    if (current.items.any((PosOrderItem i) => i.awaitingWeight)) {
      throw const PosOfflineUnavailable(
        "Og'irligi kiritilmagan taom bor: avval uni torting va og'irligini kiriting.",
        reason: 'weight_pending',
      );
    }
    if (!(book.state(orderUuid)?.leftToBill.isPositive ?? true)) {
      throw const PosOfflineUnavailable(
        "Schyotga tushadigan narsa qolmadi: ochiq schyotni to'lang.",
        reason: 'nothing_to_bill',
      );
    }
    await store.enqueue(SyncOpType.billCreate, <String, Object?>{
      'order': orderUuid,
      'base_version': ?_serverVersion(orderUuid),
      'bill': newUuid(),
      'expected_total': current.totalPrice.toApiString(),
      'idempotency_key': ?idempotencyKey,
    }, staffId: _staff());
    return billing(orderUuid);
  }

  /// Schyotni chop etish (pre-check): server bilmaydi, kassada belgilanadi.
  PosBillResult printBill(String billUuid) {
    final (String orderRef, OfflineOrderState state) = _stateOfBill(billUuid);
    state.markPrinted(billUuid, _clock());
    _printed[billUuid] = (_printed[billUuid] ?? 0) + 1;
    final PosBilling b = billing(orderRef);
    return PosBillResult(
      bill: b.bills.firstWhere((PosBill x) => x.uuid == billUuid),
      order: b.order,
      copy: (_printed[billUuid] ?? 0) > 1,
    );
  }

  final Map<String, int> _printed = <String, int>{};

  /// Naqd yoki karta (alohida terminal). Sotuv cheki internet tiklangach chiqadi.
  Future<PosBillResult> pay(
    String billUuid, {
    required List<PosTenderInput> tenders,
    String? shiftUuid,
    String? idempotencyKey,
    int? tipStaffId,
  }) async {
    _guard('pos.take_payment');
    final String? shift = shiftUuid ?? this.shiftUuid();
    if (shift == null) {
      throw const PosOfflineUnavailable(
        "Ochiq smena yo'q: internetsiz smena ochib bo'lmaydi.",
      );
    }
    for (final PosTenderInput t in tenders) {
      if (t.tender != PosTender.cash && t.tender != PosTender.card) {
        throw const PosOfflineUnavailable(
          "Internetsiz faqat naqd va karta (alohida terminal) qabul qilinadi.",
        );
      }
    }
    final (String orderRef, OfflineOrderState state) = _stateOfBill(billUuid);
    final Map<String, Object?>? bill = state.bill(billUuid);
    final PosMoney due = PosMoney.parse(bill?['due_amount']);
    final PosMoney sum = PosMoney.sum(
      tenders.map((PosTenderInput t) => t.amount),
    );
    if (sum > due) {
      throw PosOfflineUnavailable(
        "To'lov schyotdagi qarzdan ko'p: qoldiq ${due.format()}.",
        reason: 'overpayment',
      );
    }
    // Review P6 wave 2 (C10): the order's waiter as this till shows it when the money is taken — the server stamps
    // the bill (sales) and the default personal tip with them, even if the table was handed over before the sync
    final Map<String, Object?>? seen = book.order(orderRef);
    final int? waiterId = seen == null
        ? null
        : PosOrder.fromJson(PosJson(seen)).waiter?.id;
    await store.enqueue(SyncOpType.billPay, <String, Object?>{
      'order': orderRef,
      'bill': billUuid,
      'shift_uuid': shift,
      'tenders': <Object?>[for (final PosTenderInput t in tenders) t.toJson()],
      // P6-22: the tip goes with its tender (`tip_amount`); whom it goes to (review P6 wave 2: someone other than
      // the waiter needs a manager's approval, which offline cannot be given — the server gives it to the waiter)
      'tip_staff_id': ?tipStaffId,
      'waiter_id': ?waiterId,
      'idempotency_key': ?idempotencyKey,
    }, staffId: _staff());
    final PosBilling b = billing(orderRef);
    if (b.order.isClosed) {
      await store.enqueue(SyncOpType.orderClose, <String, Object?>{
        'order': orderRef,
      }, staffId: _staff());
    }
    final PosBilling after = billing(orderRef);
    return PosBillResult(
      bill: after.bills.firstWhere((PosBill x) => x.uuid == billUuid),
      order: after.order,
    );
  }

  // --- ko'rinishlar -----------------------------------------------------------------------------------------------

  PosOrder order(String uuid) {
    final Map<String, Object?>? json = book.order(uuid);
    if (json == null) {
      throw const PosOfflineUnavailable(
        "Bu buyurtma kassada saqlanmagan: uni internet tiklangach oching.",
        reason: 'offline_not_cached',
      );
    }
    return PosOrder.fromJson(PosJson(json));
  }

  PosBilling billing(String orderUuid) {
    final Map<String, Object?>? json = book.billing(orderUuid);
    if (json == null) {
      throw const PosOfflineUnavailable(
        "Bu buyurtmaning schyoti kassada yo'q.",
        reason: 'offline_not_cached',
      );
    }
    return PosBilling.fromJson(PosJson(json));
  }

  PosFloor floor() {
    final Map<String, Object?>? json = book.floor();
    if (json == null) {
      throw const PosOfflineUnavailable(
        "Zal xaritasi kassada saqlanmagan: internet kerak.",
        reason: 'offline_not_cached',
      );
    }
    return PosFloor.fromJson(PosJson(json));
  }

  List<PosOpenOrder> openOrders() => <PosOpenOrder>[
    for (final Map<String, Object?> o in book.openOrders())
      PosOpenOrder.fromJson(PosJson(o)),
  ];

  /// Terminal uzildi yoki server uni bekor qildi: navbat, nusxalar va chek ro'yxati o'chiriladi (yangi ulanish eski
  /// amallarni yangi token bilan yubormasin). Uzishdan oldin navbat bo'sh bo'lishi kerak ([store.hasPending]).
  Future<void> forget() async {
    _printed.clear();
    _emitted.clear();
    settings = const PosOfflineSettings();
    sender.limits = settings.limits;
    try {
      await store.clear();
    } on Object {
      // The disk failed: what is left is cleared on the next forget
    }
    _emit();
  }

  // --- server: holat va xatolar -----------------------------------------------------------------------------------

  /// "Sinxronlash xatolari" serverdan (`GET /pos/sync/errors`, xodim sessiyasi bilan).
  Future<PosSyncErrorsPage> serverErrors({bool all = false}) => _http.get(
    '/v1/pos/sync/errors',
    (Object? data) => PosSyncErrorsPage.fromJson(dataObject(data)),
    query: <String, Object?>{if (all) 'all': true},
  );

  /// Menejer hal qildi (`resolved`) yoki yopdi (`dismissed`).
  Future<PosSyncError> resolveError(
    String opId, {
    required String resolution,
    String? note,
  }) => _http.mutate(
    'POST',
    '/v1/pos/sync/errors/$opId/resolve',
    (Object? data) => PosSyncError.fromJson(dataObject(data)),
    body: <String, Object?>{'resolution': resolution, 'note': ?note},
  );

  /// Hozir yuborish (aloqa qaytgan bo'lsa).
  Future<SyncDrainResult> syncNow() {
    sender.nudge();
    return sender.drain();
  }

  // --- ichki ------------------------------------------------------------------------------------------------------

  void _guard(String? permission) {
    final OfflineStatus s = status;
    if (!s.canSell) {
      throw PosOfflineUnavailable(s.blockReason ?? 'Internet kerak.');
    }
    if (staffId() == null) throw const PosOfflineUnavailable('Xodim kirmagan.');
    if (permission != null && !can(permission)) {
      throw const PosOfflineUnavailable(
        "Bu amal uchun ruxsat yo'q (internetsiz menejer tasdig'i olinmaydi).",
        reason: 'permission_denied',
      );
    }
  }

  int _staff() => staffId()!;

  int? _serverVersion(String uuid) =>
      (store
                  .doc(OfflineOrderBook.orderKey(store.resolve(uuid)))
                  ?.map?['version']
              as num?)
          ?.toInt();

  /// Qator id'si (ekrandagi) → amaldagi havola: oflayn qo'shilgani — qurilma uuid'si, serverdagisi — server id'si,
  /// choy-non (oflayn) — `null`.
  Object? _lineRef(String uuid, int itemId) {
    final Map<String, Object?>? json = book.order(uuid);
    for (final Object? i
        in (json?['items'] as List<Object?>?) ?? const <Object?>[]) {
      if (i is Map && i['id'] == itemId) {
        if (itemId > 0) return itemId;
        return i['client_line_id'];
      }
    }
    return itemId > 0 ? itemId : null;
  }

  (String, OfflineOrderState) _stateOfBill(String billUuid) {
    final (String, OfflineOrderState)? found = book.stateOfBill(billUuid);
    if (found != null) return found;
    throw const PosOfflineUnavailable(
      "Schyot kassada topilmadi.",
      reason: 'offline_not_cached',
    );
  }

  PosCatalog _catalogOf(PosChannel channel) {
    final PosCatalog? c = catalog(channel);
    if (c != null) return c;
    final Map<String, Object?>? raw = store.doc('catalog:${channel.wire}')?.map;
    if (raw != null) return PosCatalog.fromJson(PosJson(raw));
    throw const PosOfflineUnavailable(
      "Menyu kassada saqlanmagan: internet kerak.",
    );
  }

  Map<String, Object?> _line(PosCatalog cat, PosLineInput input) {
    final PosVariant? v = cat.variantByUuid(input.variantUuid);
    final PosProduct? p = v == null ? null : cat.product(v.productId);
    if (v == null || p == null) {
      throw const PosOfflineUnavailable("Taom menyu nusxasida yo'q.");
    }
    final List<Map<String, Object?>> mods = <Map<String, Object?>>[];
    for (final PosModifierChoice m in input.modifiers) {
      PosModifier? found;
      String? group;
      for (final PosModifierGroup g in p.modifierGroups) {
        for (final PosModifier x in g.modifiers) {
          if (x.uuid == m.modifierUuid) {
            found = x;
            group = g.name;
          }
        }
      }
      mods.add(<String, Object?>{
        'id': m.modifierUuid,
        'quantity': m.quantity,
        'price_delta': (found?.priceDelta ?? PosMoney.zero).toApiString(),
        'modifier_id': found?.id,
        'name': found?.name,
        'group_name': group,
      });
    }
    // P6-31: a combo sold offline: its picks checked and priced as the server does (Combos::resolve); the server
    // keeps the picks and compares the delta (`combo_delta`) with its own
    final PosComboResolution? combo = _combo(cat, p, input);
    return <String, Object?>{
      'line_id': newUuid(),
      ...input.toJson(),
      'modifiers': mods,
      'unit_price': v.price.toApiString(),
      if (combo != null) ...<String, Object?>{
        'combo_delta': combo.delta.toApiString(),
        'combo_parts': <Map<String, Object?>>[
          for (final PosComboPart part in combo.parts) part.toJson(),
        ],
      },
      // For the till's own view and tickets
      'name': p.name,
      'variant_name': v.name,
      'unit': v.unit.wire,
      'unit_label': v.unitLabel,
      'product_variant_id': v.id,
      // P6-34: the dish and its category, for the packaging rules (OfflinePackaging); the server ignores them
      'product_id': p.id,
      'category_id': p.categoryId,
      'station_id':
          p.stationId ??
          bootstrap()?.stationRoutes.stationFor(
            productId: p.id,
            categoryId: p.categoryId,
          ),
      // P6-25: the till's own view of a dish weighed later (the server decides by the same rule: only with the
      // branch's `weight_later` module — a catalog cached before it was switched off still names the rule)
      if ((input.awaitingWeight ?? p.weighedLater) &&
          (bootstrap()?.modules.has(PosModule.weightLater) ?? false) &&
          v.unit != PosUnit.piece &&
          !p.batchTracked)
        'weight_basis': (p.weightMode ?? PosWeightBasis.raw).wire,
    };
  }

  /// P6-31: the combo of a line's dish, its picks checked against the cached menu (an option the catalog marked
  /// unavailable is refused, as the server would); `null` for a plain dish.
  PosComboResolution? _combo(PosCatalog cat, PosProduct p, PosLineInput input) {
    final PosCombo? combo = p.combo;
    if (combo == null) return null;
    try {
      return PosComboPricing.resolve(
        combo,
        input.combo ?? const <PosComboPick>[],
        comboName: p.name,
        available: (PosComboOption o) {
          final PosVariant? part = cat.variantByUuid(o.variantUuid);
          return o.isAvailable &&
              (part == null || cat.isVariantAvailable(part));
        },
        partPrice: (PosComboOption o) =>
            cat.variantByUuid(o.variantUuid)?.price ?? PosMoney.zero,
        modifierInfo: (String uuid) {
          for (final PosProduct x in cat.products) {
            for (final PosModifierGroup g in x.modifierGroups) {
              for (final PosModifier m in g.modifiers) {
                if (m.uuid.toLowerCase() == uuid) {
                  return (
                    name: m.name,
                    priceDelta: m.priceDelta,
                    groupName: g.name,
                  );
                }
              }
            }
          }
          return null;
        },
      );
    } on PosComboRejected catch (e) {
      throw PosOfflineUnavailable(e.message);
    }
  }

  List<Object?> _autoItems(PosCatalog cat, int? hallId, int guests) =>
      <Object?>[
        for (final PosOfflineGuestItem g in settings.guestItemsFor(hallId))
          if (cat.variantByUuid(g.variantUuid) case final PosVariant v)
            if (cat.product(v.productId) case final PosProduct p)
              <String, Object?>{
                'variant_id': v.uuid,
                'quantity': OfflinePricing.guestQuantity(
                  g.quantityPerGuest,
                  guests,
                  v.unit,
                ).toApiString(),
                'unit_price': v.price.toApiString(),
                'name': p.name,
                'variant_name': v.name,
                'unit': v.unit.wire,
                'unit_label': v.unitLabel,
                'product_variant_id': v.id,
                'fire_mode': 'now',
                'station_id':
                    p.stationId ??
                    bootstrap()?.stationRoutes.stationFor(
                      productId: p.id,
                      categoryId: p.categoryId,
                    ),
              },
      ];

  static PosTable? _table(PosBootstrap? boot, int id) {
    for (final PosTable t in boot?.tables ?? const <PosTable>[]) {
      if (t.id == id) return t;
    }
    return null;
  }

  static PosHall? _hall(PosBootstrap? boot, int id) {
    for (final PosHall h in boot?.halls ?? const <PosHall>[]) {
      if (h.id == id) return h;
    }
    return null;
  }

  bool _roomBusy(int tableId) {
    try {
      return floor().table(tableId)?.orders.isNotEmpty ?? false;
    } on PosOfflineUnavailable {
      return false;
    }
  }

  void _onConnectivity(PosConnectivityState s) {
    if (s.online) {
      final DateTime? at = s.lastContactAt;
      if (at != null) unawaited(store.markContact(at));
      _stopProbe();
      if (store.hasPending) sender.nudge();
    } else {
      _startProbe();
    }
    _emit();
  }

  /// Aloqa yo'q paytda: serverni vaqti-vaqti bilan tekshirish (navbat bo'sh bo'lsa ham — rejimdan chiqish uchun).
  void _startProbe() {
    _tick ??= Timer.periodic(const Duration(seconds: 30), (_) => _emit());
    if (_probe != null) return;
    final int step = min(_probeFailures, 2);
    _probe = Timer(heartbeat * (1 << step), () async {
      _probe = null;
      try {
        await _http.raw(
          'GET',
          '/v1/pos/sync/status',
          query: <String, Object?>{'queue_size': store.pendingCount},
          scope: AuthScope.terminal,
          idempotent: false,
        );
        _probeFailures = 0;
      } on PosException {
        _probeFailures++;
        if (!connectivity.state.online) _startProbe();
      }
    });
  }

  void _stopProbe() {
    _probe?.cancel();
    _probe = null;
    _probeFailures = 0;
    if (!store.hasPending) {
      _tick?.cancel();
      _tick = null;
    }
  }

  /// Navbat bo'shadi: zal va oflayn tegishli buyurtmalar serverdan qayta olinadi, keyin eski amallar tashlanadi.
  Future<void> _afterDrain() async {
    final List<SyncOperation> synced = <SyncOperation>[
      for (final SyncOperation o in store.answered)
        if (_emitted.add(o.opId)) o,
    ];
    try {
      await _recordSales(store.answered);
    } on Object {
      // Not saved: the next drain records them again
    }
    if (!_synced.isClosed) _synced.add(synced);
    if (store.hasPending) return;
    try {
      final Object? floor = (await _http.raw(
        'GET',
        '/v1/pos/floor',
        idempotent: false,
      )).data;
      if (floor is Map) {
        await store.putDoc(
          OfflineOrderBook.floorKey,
          floor.cast<String, Object?>(),
        );
      }
      final Set<String> touched = <String>{
        for (final SyncOperation o in store.operationsAfter(0))
          if (o.orderRef != null) store.resolve(o.orderRef!),
      };
      for (final String uuid in touched) {
        try {
          final Object? order = (await _http.raw(
            'GET',
            '/v1/pos/orders/$uuid',
            idempotent: false,
          )).data;
          if (order is Map) {
            await store.putDoc(
              OfflineOrderBook.orderKey(uuid),
              order.cast<String, Object?>(),
            );
          }
          final Object? bills = (await _http.raw(
            'GET',
            '/v1/pos/orders/$uuid/bills',
            idempotent: false,
          )).data;
          await store.putDoc(OfflineOrderBook.billingKey(uuid), bills);
        } on PosNotFound {
          await store.removeDoc(OfflineOrderBook.orderKey(uuid));
        }
      }
      await store.markReloaded();
    } on PosException {
      // The next drain or refresh does it again
    }
    _emit();
  }

  void _emit() {
    // The top bar's sync badge reads the queue size from the connectivity state
    connectivity.setPendingOperations(store.pendingCount);
    if (!_changes.isClosed) _changes.add(status);
  }

  Future<void> dispose() async {
    _probe?.cancel();
    _tick?.cancel();
    for (final StreamSubscription<Object?> s in _subs) {
      await s.cancel();
    }
    await sender.dispose();
    // Not awaited: a broadcast stream's close waits for its listeners (the screens) to see the end
    unawaited(_changes.close());
    unawaited(_synced.close());
  }
}
