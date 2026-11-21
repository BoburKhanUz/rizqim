import 'dart:async';
import 'dart:convert';
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
/// * **Chegaralar (5.4):** so'nggi 24 soatda aloqa va 24 soatdan yangi menyu bo'lmasa — YANGI buyurtma ochilmaydi
///   (M5 S1: ochiq buyurtmaga bosqich, schyot, naqd/karta to'lov va yopish hech qachon to'xtamaydi); navbatda 500
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
    String? Function()? device,
  }) : _http = http,
       _clock = clock ?? DateTime.now {
    book = OfflineOrderBook(store: store, settings: () => settings);
    sender = SyncSender(
      store: store,
      transport: transport ?? PosSyncTransport(http),
      clock: _clock,
      random: random,
      onDrained: _afterDrain,
    )..serverLastSeq = _serverLastSeq;
    _wasOnline = connectivity.state.online;
    _subs
      ..add(connectivity.changes.listen(_onConnectivity))
      ..add(store.changes.listen((_) => _emit()))
      ..add(sender.changes.listen((_) => _emit()));
    final Map<String, Object?>? boot = store.doc(_bootstrapKey)?.map;
    if (boot != null) settings = PosOfflineSettings.of(boot);
    _loadOrphans();
    // Answers from before this start were emitted then (and their receipts are in the store's own list)
    _emitted.addAll(store.answered.map((SyncOperation o) => o.opId));
    _startSender(device);
  }

  /// M5 S1 review: the sender starts only for a journal that belongs to the terminal this device holds now
  /// ([device]: the restored terminal's uuid; not given — not checked).
  /// * The last run could not set the queue aside ([held], kept on the disk): still nothing is sent.
  /// * The journal is bound to ANOTHER device (the till was paired again and stopped before [forget] ran): the old
  ///   queue is set aside now, as [forget] does, before anything can leave with the new token.
  /// * No terminal on this device and operations in the journal: nothing is sent and nothing is deleted ([held]) until
  ///   the till is paired ([bindDevice]: the same device goes on, another one sets the queue aside).
  void _startSender(String? Function()? device) {
    _held = store.doc(_heldKey) != null;
    final String? bound = boundDevice;
    final String? now = device?.call();
    final bool foreign = device != null && bound != null && bound != now;
    if (foreign && now != null) {
      _stopSender();
      unawaited(forget(reason: 'device_mismatch'));
    } else if (_held || (foreign && store.hasPending)) {
      _held = true;
      _stopSender();
    } else {
      sender.start();
    }
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

  /// M5 S1-e: the signed-in staff member's PIN session has expired with no server contact
  /// (`AuthSession.staffContinued`; PosData binds it). Such a staff member only finishes what is open: a round, the
  /// bill, the payment and the close of an existing order; a NEW order is refused ([continuedReason]). What is queued
  /// this way carries `staff_continued: true` in its payload, for the server's records.
  bool Function() staffContinued = () => false;

  /// M5 S1 review (owner answer 3): the continued staff member's PIN login is more than 12 h old
  /// (`AuthSession.staffFinishOnly`; PosData binds it): only the bill, the payment and the close of an open order.
  /// A new round and a void are refused too ([continuedReason]); the payment is never refused.
  bool Function() staffFinishOnly = () => false;

  /// M5 S6-a: the staff member was resumed without a PIN after an app restart during the outage
  /// (`AuthSession.staffResumed`; PosData binds it): continued and finish-only, and what is queued carries
  /// [resumedKey] as well.
  bool Function() staffResumed = () => false;

  /// Owner decision Q1 (21.11.2026, option 2): the resumed staff member ([staffResumed]) is past the 12 h after their
  /// PIN login (`AuthSession.staffResumeWindowOver`; PosData binds it; only while the server records the mark,
  /// bootstrap `offline.staff_window_over`). Only the payment and the close of open orders, and the bill a payment
  /// needs, go through ([windowOverReason] otherwise); what is queued carries [windowOverKey] as well.
  bool Function() staffWindowOver = () => false;
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

  /// The last connection state seen ([_onConnectivity]): a nudge only on the way back from offline.
  bool _wasOnline = true;
  int _probeFailures = 0;
  bool _warming = false;

  final Set<String> _emitted = <String>{};

  static const String _bootstrapKey = 'bootstrap';

  /// M5 S6 (review 2 HIGH): the open shift as this till last knew it (`{"shift": {...} | null}`), written at every
  /// change ([rememberShift]), also while operations are queued (the bootstrap copy is not written then).
  static const String _shiftKey = 'shift';
  static const String _salesKey = '_sale_receipts';

  /// [PosOfflineUnavailable.reason] of a NEW order refused by the age limits ([OfflineStatus.canOpenOrders]).
  static const String limitReason = 'offline_limit';

  /// [PosOfflineUnavailable.reason] of a NEW order refused because the staff member goes on without a PIN
  /// ([staffContinued]).
  static const String continuedReason = 'staff_continued';

  /// Payload key of an operation queued by a staff member who goes on without a PIN ([staffContinued]).
  static const String continuedKey = 'staff_continued';

  /// M5 S6-a: payload key of an operation queued by a staff member resumed without a PIN after an app restart
  /// ([staffResumed]); sent only to a server that said it records it (bootstrap `offline.staff_resume`).
  static const String resumedKey = 'staff_resumed';

  /// Owner decision Q1: payload key of an operation queued past the 12 h by a resumed staff member ([staffWindowOver]);
  /// the server records such a payment as a `staff_unverified` conflict for the manager (never rejected or dropped).
  static const String windowOverKey = 'staff_window_over';

  /// Owner decision Q1: [PosOfflineUnavailable.reason] of anything but the payment, the close and the bill they need,
  /// past the 12 h ([staffWindowOver]).
  static const String windowOverReason = 'staff_window_over';

  /// Owner decision Q1: what may be queued past the 12 h ([staffWindowOver]): the bill an open order needs to be paid,
  /// the payment and the close.
  static const Set<String> windowOverTypes = <String>{
    SyncOpType.billCreate,
    SyncOpType.billPay,
    SyncOpType.orderClose,
  };

  static const String _windowOverMessage =
      "PIN'siz davom etish muddati (PIN bilan kirilganidan 12 soat) tugadi: endi faqat to'lov olish va ochiq "
      'buyurtmani yopish mumkin. Internet tiklangach PIN bilan kiring.';

  /// M5 S2: [PosOfflineUnavailable.reason] of a discount refused because the server did not say it takes
  /// `order.discount` (bootstrap `offline.discount`): the discount needs the internet, as before.
  static const String discountUnsupportedReason =
      'offline_discount_unsupported';

  /// M5 S2: [PosOfflineUnavailable.reason] of a discount above what anybody gives offline (owner decision of
  /// 20.11.2026: the branch's cashier limit, for a manager and a holder of `orders.discount` too; no limit configured:
  /// no offline discount), or by somebody who gives none alone: a larger one needs the internet, a manager's approval
  /// cannot be given offline.
  static const String discountLimitReason = 'offline_discount_limit';

  /// M5 S2: [PosOfflineUnavailable.reason] of a discount refused because the order already has a bill (offline a
  /// discount is given only before the bill, by everybody).
  static const String discountBilledReason = 'offline_discount_billed';

  /// "PIN'siz davom etilmoqda" lentasi uchun: PIN sessiyasi aloqa yo'q paytda tugagan xodimning ismi (bo'lmasa
  /// `null`). Ism noma'lum bo'lsa — "Xodim".
  String? get continuedStaffName =>
      staffContinued() ? (staffName() ?? 'Xodim') : null;

  /// Lenta matni ([continuedStaffName] bo'lsa), aks holda `null`. PIN bilan kirilganiga 12 soatdan oshgan bo'lsa
  /// ([staffFinishOnly]) — torroq qoida aytiladi.
  String? get continuedNotice {
    final String? name = continuedStaffName;
    if (name == null) return null;
    if (staffWindowOver()) {
      return "PIN'siz davom etilmoqda: $name. PIN bilan kirilganiga 12 soat o'tdi: endi faqat to'lov olish va ochiq "
          "buyurtmani yopish mumkin (yangi buyurtma, taom qo'shish, oshxonaga yuborish va chegirma yo'q). Bu "
          "to'lovlar menejer tekshiruviga tushadi. Internet tiklangach PIN bilan kiring.";
    }
    if (staffResumed()) {
      return "PIN'siz davom etilmoqda: $name (ilova internetsiz qayta ishga tushdi). Faqat ochiq buyurtmalarni "
          "yakunlash va to'lov olish mumkin: schyot, to'lov va buyurtmani yopish (yangi buyurtma, taom qo'shish, "
          "o'chirish va chegirma yo'q). Internet tiklangach PIN bilan kiring.";
    }
    return staffFinishOnly()
        ? "PIN'siz davom etilmoqda: $name. PIN bilan kirilganiga 12 soatdan oshdi: faqat schyot, to'lov va "
              "buyurtmani yopish mumkin (yangi taom qo'shilmaydi, taom o'chirilmaydi)."
        : "PIN'siz davom etilmoqda: $name. Faqat ochiq buyurtmalarni yakunlash va to'lov olish mumkin.";
  }

  /// Shuncha chop etilgan chek `op_id` si eslab qolinadi (qayta chiqmasin).
  static const int keepPrintedSales = 500;

  PosConnectivity get connectivity => _http.connectivity;

  /// M5 S6-a: the latest time this till already saw (its last server contact, its newest queued operation): a device
  /// clock behind it was set back. `null`: nothing seen.
  DateTime? get latestKnownTime {
    DateTime? latest = store.lastContactAt;
    for (final SyncOperation o in store.pending) {
      if (latest == null || o.occurredAt.isAfter(latest)) latest = o.occurredAt;
    }
    return latest;
  }

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

  /// Serverga yetgan `bill.pay` amallari chop etish ro'yxatiga (bir marta). M5 S1 review: xodim tekshiruvidan o'tmagan
  /// (`staff_unverified`), lekin schyotga tushgan to'lov ham (server `bill_uuid` qaytaradi: schyot to'langan) — chek
  /// to'xtamaydi. Schyotga tushmagan to'lovda `bill_uuid` yo'q: chek yo'q.
  Future<void> _recordSales(List<SyncOperation> answered) async {
    final List<String> seen = _salesSeen();
    final Set<String> known = seen.toSet();
    final List<PosOfflineSale> todo = salesToPrint;
    var added = false;
    for (final SyncOperation op in answered) {
      final bool settled =
          op.status == SyncOpStatus.applied ||
          (op.status == SyncOpStatus.conflict &&
              op.reason == PosSyncReasons.staffUnverified);
      if (op.type != SyncOpType.billPay ||
          !settled ||
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
    // P6: the local bridge's keys are never written to the plain copy on disk (the app keeps them in secure storage)
    await _put(_bootstrapKey, <String, Object?>{
      for (final MapEntry<String, Object?> e in raw.entries)
        if (e.key != 'lan') e.key: e.value,
    });
    // The server's answer: its shift is what this till knows now (shifts are never opened or closed offline)
    await _writeShift(raw['shift']);
  }

  /// M5 S6 (review 2 HIGH): the open shift changed (opened, closed, read again; pos_data `PosSession.updateShift`).
  /// Kept in its own small copy, written every time (even with operations queued), and read back with the bootstrap
  /// copy ([storedBootstrap]) after an app restart during the outage: the shift opened after the PIN login is known
  /// then.
  void rememberShift(PosShiftSummary? shift) =>
      unawaited(_writeShift(shift?.toJson()));

  Future<void> _writeShift(Object? shift) async {
    try {
      await store.putDoc(_shiftKey, <String, Object?>{'shift': shift});
    } on Object {
      // Not saved: the previous copy stays
    }
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

  /// Oxirgi bootstrap nusxasi (ilova internetsiz qayta ochilganda). M5 S1: yoshi cheklanmaydi — ochiq buyurtmaning
  /// zali, haqlari va smenasi 24 soatdan keyin ham kerak (schyot va to'lov to'xtamaydi); yangi buyurtma ochishni
  /// [OfflineStatus.canOpenOrders] to'xtatadi.
  ///
  /// M5 S6: with the open shift as this till last knew it ([rememberShift]), not as the copy was taken.
  PosBootstrap? storedBootstrap() {
    final SyncDoc? d = store.doc(_bootstrapKey);
    final Map<String, Object?>? m = d?.map;
    if (d == null || m == null) return null;
    final Map<String, Object?>? shift = store.doc(_shiftKey)?.map;
    try {
      return PosBootstrap.fromJson(
        PosJson(
          shift == null || !shift.containsKey('shift')
              ? m
              : <String, Object?>{...m, 'shift': shift['shift']},
        ),
        receivedAt: d.takenAt,
      );
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
    int? waiterId,
    String? waiterName,
  }) async {
    _guard('orders.manage', opening: true);
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
    await _enqueue(SyncOpType.orderOpen, <String, Object?>{
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
      // P6 local bridge: a waiter's order is theirs (as the server makes it)
      'waiter_id': ?waiterId,
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
        'staff_name': _staffName(),
        'waiter': waiterId == null
            ? null
            : <String, Object?>{'id': waiterId, 'name': waiterName},
      },
      'auto_items': channel == PosChannel.hall
          ? _autoItems(cat, hall?.id, guests)
          : const <Object?>[],
    });
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
    _guard('orders.manage', changing: true);
    final PosOrder current = order(uuid);
    if (!current.isOpen) {
      throw const PosOfflineUnavailable('Buyurtma yopilgan.');
    }
    final PosCatalog cat = _catalogOf(current.posChannel ?? PosChannel.hall);
    await _enqueue(SyncOpType.orderRound, <String, Object?>{
      'order': uuid,
      'base_version': ?_serverVersion(uuid),
      'items': <Object?>[for (final PosLineInput i in items) _line(cat, i)],
      'fire_mode': ?fireMode?.wire,
      if (send) 'send': true,
      'idempotency_key': ?idempotencyKey,
      'view': <String, Object?>{'staff_name': _staffName()},
    });
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
    await _enqueue(SyncOpType.orderSend, <String, Object?>{
      'order': uuid,
      'base_version': ?_serverVersion(uuid),
      'lines': ?lines,
      'idempotency_key': ?idempotencyKey,
    });
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
    await _enqueue(SyncOpType.orderCourseSend, <String, Object?>{
      'order': uuid,
      'base_version': ?_serverVersion(uuid),
      'course_no': courseNo,
      'idempotency_key': ?idempotencyKey,
    });
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
    await _enqueue(SyncOpType.orderWeight, <String, Object?>{
      'order': uuid,
      'base_version': ?_serverVersion(uuid),
      'line': ref,
      'quantity': quantity.toApiString(),
      'idempotency_key': ?idempotencyKey,
      'view': <String, Object?>{'staff_name': _staffName()},
    });
    return order(uuid);
  }

  /// Faqat oshxonaga yuborilmagan qator (yuborilganini o'chirish uchun menejer tasdig'i — internet kerak).
  Future<PosOrder> voidItem(
    String uuid,
    int itemId, {
    String? reason,
    String? idempotencyKey,
  }) async {
    _guard('orders.manage', changing: true);
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
    await _enqueue(SyncOpType.orderVoid, <String, Object?>{
      'order': uuid,
      'base_version': ?_serverVersion(uuid),
      'line': ref,
      'reason': ?reason,
      'idempotency_key': ?idempotencyKey,
    });
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
    await _enqueue(SyncOpType.orderServed, <String, Object?>{
      'order': uuid,
      'base_version': ?_serverVersion(uuid),
      'line': ref,
      'idempotency_key': ?idempotencyKey,
    });
    return order(uuid);
  }

  // --- M5 S2: internetsiz chegirma --------------------------------------------------------------------------------

  /// Server `order.discount` amalini qabul qiladi (bootstrap `offline.discount: true`). `false` — eski server:
  /// chegirma faqat internet bilan.
  bool get discountEnabled => settings.discount;

  /// Internetsiz hech kim cheksiz chegirma bermaydi (egasining qarori, 20.11.2026): menejer va `orders.discount`
  /// egasi ham filialning kassir chegarasigacha ([discountLimitPercent]). Doim `false`; kattarog'i — faqat onlayn.
  bool get discountUnlimited => false;

  /// Shu xodim internetsiz bera oladigan eng katta foiz — filialning kassir chegarasi, bootstrap nusxasidan (`"10"`,
  /// `"12.5"`), hammaga bir xil (`orders.discount` egasiga ham). Chegara belgilanmagan, 0 yoki xodim o'zi chegirma
  /// bera olmaydi (na `orders.discount`, na `pos.take_payment`) — `null`: internetsiz chegirma yo'q.
  String? get discountLimitPercent {
    if (!_givesDiscountAlone) return null;
    final String? limit = _cashierLimit;
    return limit == null ? null : formatPercent(limit);
  }

  /// Whoever gives a discount without a manager's approval online: a holder of `orders.discount`, or a cashier.
  bool get _givesDiscountAlone =>
      _can('orders.discount') || _can('pos.take_payment');

  String? get _cashierLimit {
    final String? limit =
        bootstrap()?.branch.settings.cashierDiscountMaxPercent;
    return (OfflineDiscount.hundredths(limit) ?? 0) > 0 ? limit : null;
  }

  /// Nega shu buyurtmaga internetsiz UMUMAN chegirma berib (yoki olib tashlab) bo'lmaydi — o'zbekcha matn, tugma
  /// yonida ko'rsatish uchun; mumkin bo'lsa `null`. Qiymatga bog'liq sabablar (kassir chegarasi, sabab yozilmagan):
  /// [discountRefusal].
  String? discountBlock(String uuid) => _discountBlock(uuid)?.message;

  /// Aynan shu chegirmani internetsiz berib bo'lmasa — sababi (o'zbekcha), aks holda `null`. [discount] aynan shu
  /// tekshiruvdan o'tadi (hech narsa navbatga yozilmaydi).
  String? discountRefusal(
    String uuid, {
    required PosDiscountKind kind,
    required String value,
    String? reason,
  }) => _discountCheck(uuid, kind, value, reason).refused?.message;

  /// M5 S2 (docs/scale/M5_DESIGN.md 3-bo'lim): buyurtmaga internetsiz chegirma, menejer tasdig'isiz (uni server
  /// beradi). Egasining qarori (20.11.2026): internetsiz HAMMA — kassir (`pos.take_payment`), menejer va
  /// `orders.discount` egasi ham — ko'pi bilan bootstrap nusxasidagi `cashier_discount_max_percent` gacha beradi
  /// (qiymat yozilganidek); undan ko'pi yoki chegara belgilanmagan — rad etiladi ([discountLimitReason]). Faqat schyot
  /// tuzilguncha, 24 soatlik chegaradan keyin emas, PIN'siz davom etayotgan xodim bera olmaydi, sabab majburiy.
  /// [value] `"0"` — chegirmani olib tashlash (sabab shart emas). Navbatga `order.discount` yoziladi; summa serverdagi
  /// qoida bilan hisoblanadi ([OfflineDiscount]) va har qator o'zgarishida qayta hisoblanadi.
  Future<PosOrder> discount(
    String uuid, {
    required PosDiscountKind kind,
    required String value,
    String? reason,
    String? idempotencyKey,
  }) async {
    final ({
      PosOfflineUnavailable? refused,
      int hundredths,
      PosMoney items,
      String? reason,
    })
    check = _discountCheck(uuid, kind, value, reason);
    if (check.refused != null) throw check.refused!;
    final bool removing = check.hundredths == 0;
    final String canonical = OfflineDiscount.canonical(check.hundredths);
    await _enqueue(SyncOpType.orderDiscount, <String, Object?>{
      'order': uuid,
      'kind': kind.wire,
      'value': canonical,
      if (!removing) 'reason': check.reason,
      // What the till shows now: the server compares it with what it applied (`discount_mismatch`, a note)
      'expected_discount':
          (removing
                  ? PosMoney.zero
                  : OfflineDiscount.amount(kind.wire, canonical, check.items))
              .toApiString(),
      'idempotency_key': ?idempotencyKey,
    });
    return order(uuid);
  }

  /// Why no discount at all may be given (or taken off) on this order offline; `null`: it may.
  PosOfflineUnavailable? _discountBlock(String uuid) {
    if (!settings.discount) {
      return const PosOfflineUnavailable(
        'Chegirma uchun internet kerak: aloqa tiklangach bering.',
        reason: discountUnsupportedReason,
      );
    }
    if (_actor != null) {
      // Never relayed over the LAN (as the server: PosSyncOperationType::relayable)
      return const PosOfflineUnavailable(
        "Chegirma faqat kassaning o'zida beriladi.",
      );
    }
    if (_forgetting != null) {
      return const PosOfflineUnavailable(
        'Kassa serverdan uzildi: amal saqlanmadi. Kassani qayta ulang.',
      );
    }
    if (staffId() == null) {
      return const PosOfflineUnavailable('Xodim kirmagan.');
    }
    if (!status.canOpenOrders) {
      return const PosOfflineUnavailable(
        "Kassa 24 soatdan beri server bilan bog'lanmagan (yoki menyu nusxasi 24 soatdan eski): internetsiz chegirma "
        "berilmaydi. Schyot va to'lov ishlaydi; chegirmani internet tiklangach bering.",
        reason: limitReason,
      );
    }
    if (_actor == null && staffWindowOver()) {
      return const PosOfflineUnavailable(
        _windowOverMessage,
        reason: windowOverReason,
      );
    }
    if (_continued) {
      return const PosOfflineUnavailable(
        "PIN'siz davom etilmoqda: internetsiz chegirma berilmaydi. Faqat ochiq buyurtmalarni yakunlash va to'lov "
        'olish mumkin. Internet tiklangach PIN bilan kiring.',
        reason: continuedReason,
      );
    }
    if (!_can('orders.discount') && !_can('orders.manage')) {
      return const PosOfflineUnavailable(
        "Chegirma berishga ruxsat yo'q (internetsiz menejer tasdig'i olinmaydi).",
        reason: 'permission_denied',
      );
    }
    final OfflineOrderState? state = book.state(uuid);
    if (state == null) {
      return const PosOfflineUnavailable(
        'Bu buyurtma kassada saqlanmagan: chegirmani internet tiklangach bering.',
        reason: 'offline_not_cached',
      );
    }
    if (!state.isOpen) {
      return const PosOfflineUnavailable('Buyurtma yopilgan.');
    }
    final bool billed =
        state.order['bill_locked'] == true ||
        state.bills.any((Map<String, Object?> b) => b['status'] != 'void');
    if (billed) {
      return const PosOfflineUnavailable(
        "Schyot tuzilgan: internetsiz chegirma faqat schyot tuzilguncha beriladi. Internet tiklangach menejer bilan "
        "o'zgartiring.",
        reason: discountBilledReason,
      );
    }
    return null;
  }

  /// The whole check of one discount (the same rules as PosOrderService::discount, without the approval token):
  /// `refused` when it may not be given offline; else the value in hundredths, the dishes it is taken from and the
  /// trimmed reason.
  ({
    PosOfflineUnavailable? refused,
    int hundredths,
    PosMoney items,
    String? reason,
  })
  _discountCheck(
    String uuid,
    PosDiscountKind kind,
    String value,
    String? reason,
  ) {
    ({
      PosOfflineUnavailable? refused,
      int hundredths,
      PosMoney items,
      String? reason,
    })
    no(String message, String why) => (
      refused: PosOfflineUnavailable(message, reason: why),
      hundredths: 0,
      items: PosMoney.zero,
      reason: null,
    );

    final PosOfflineUnavailable? block = _discountBlock(uuid);
    if (block != null) {
      return (
        refused: block,
        hundredths: 0,
        items: PosMoney.zero,
        reason: null,
      );
    }
    final int? h = OfflineDiscount.hundredths(value.trim());
    if (h == null) {
      return no(
        "Chegirma qiymatini to'g'ri kiriting (manfiy emas, ko'pi bilan ikki kasr raqam).",
        'value_invalid',
      );
    }
    final PosMoney items = PosMoney.sum(<PosMoney>[
      for (final Map<String, Object?> i in book.state(uuid)!.items)
        if (i['kitchen_status'] != 'void') PosMoney.parse(i['total_price']),
    ]);
    if (h == 0) {
      // Taking a discount off raises the bill: whoever may ask at all may do it, no reason needed
      return (refused: null, hundredths: 0, items: items, reason: null);
    }
    final String trimmed = reason?.trim() ?? '';
    if (trimmed.isEmpty) {
      return no('Chegirma sababini yozing.', 'reason_required');
    }
    final String why = trimmed.length > 255
        ? trimmed.substring(0, 255)
        : trimmed;
    if (kind == PosDiscountKind.percent && h > 10000) {
      return no('Chegirma 100 foizdan oshmasligi kerak.', 'value_invalid');
    }
    if (kind == PosDiscountKind.amount && PosMoney(h) > items) {
      return no(
        "Chegirma taomlar summasidan (${items.format()}) katta bo'lmasligi kerak.",
        'value_invalid',
      );
    }
    // Owner decision of 20.11.2026: offline everybody has the branch's cashier limit, a holder of `orders.discount`
    // too (as PosSyncApplier::discount: `offline_limit`); a larger discount is given online
    if (!_givesDiscountAlone) {
      return no(
        "Internetsiz chegirma berish uchun menejer tasdig'i kerak, u esa faqat internet bilan olinadi: chegirmani "
        'aloqa tiklangach bering.',
        discountLimitReason,
      );
    }
    final String? limit = _cashierLimit;
    if (limit == null) {
      return no(
        'Filialda kassir chegirmasi chegarasi belgilanmagan: internetsiz chegirma berilmaydi. Chegirmani internet '
        'qaytgach bering.',
        discountLimitReason,
      );
    }
    if (!OfflineDiscount.withinLimit(
      kind.wire,
      OfflineDiscount.canonical(h),
      items,
      limit,
    )) {
      return no(
        "Internetsiz chegirma: filial chegarasi ${formatPercent(limit)}% gacha. Kattarog'i internet qaytgach "
        'beriladi.',
        discountLimitReason,
      );
    }
    return (refused: null, hundredths: h, items: items, reason: why);
  }

  /// Bitta schyot (bo'lish — internet kerak).
  Future<PosBilling> createBill(
    String orderUuid, {
    String? idempotencyKey,
  }) async {
    if (!_can('orders.manage') && !_can('pos.take_payment')) {
      _guard('pos.take_payment', finishing: true);
    }
    // (Owner decision Q1: the bill an open order needs to be paid is allowed past the 12 h too)
    _guard(null, finishing: true);
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
    await _enqueue(SyncOpType.billCreate, <String, Object?>{
      'order': orderUuid,
      'base_version': ?_serverVersion(orderUuid),
      'bill': newUuid(),
      'expected_total': current.totalPrice.toApiString(),
      'idempotency_key': ?idempotencyKey,
    });
    return billing(orderUuid);
  }

  /// P6 lokal ko'prik: KDS qatorni "tayyor" qildi (oshxonada bo'lgan qator).
  Future<PosOrder> kitchenBump(String uuid, int itemId) =>
      _kitchen(uuid, itemId, ready: true);

  /// P6: KDS "tayyor"ni qaytardi (qator yana oshxonada).
  Future<PosOrder> kitchenRecall(String uuid, int itemId) =>
      _kitchen(uuid, itemId, ready: false);

  Future<PosOrder> _kitchen(
    String uuid,
    int itemId, {
    required bool ready,
  }) async {
    _guard('kitchen.manage');
    final PosOrderItem? item = order(uuid).item(itemId);
    if (item == null) throw const PosOfflineUnavailable('Taom topilmadi.');
    final PosKitchenStatus from = ready
        ? PosKitchenStatus.sent
        : PosKitchenStatus.ready;
    if (item.kitchenStatus != from) {
      throw PosOfflineUnavailable(
        ready ? 'Taom oshxonada emas.' : 'Taom hali tayyor emas.',
        reason: 'kitchen_status',
      );
    }
    final Object? ref = _lineRef(uuid, itemId);
    if (ref == null) {
      throw const PosOfflineUnavailable(
        'Bu qatorni internet tiklangach belgilang.',
      );
    }
    await _enqueue(
      ready ? SyncOpType.kitchenBump : SyncOpType.kitchenRecall,
      <String, Object?>{'order': uuid, 'line': ref},
    );
    return order(uuid);
  }

  /// P6: yig'ish ekrani buyurtmani yig'di.
  Future<PosOrder> kitchenAssembled(
    String uuid, {
    String? idempotencyKey,
  }) async {
    _guard('kitchen.manage');
    if (!order(uuid).isOpen) {
      throw const PosOfflineUnavailable('Buyurtma yopilgan.');
    }
    await _enqueue(SyncOpType.kitchenAssembled, <String, Object?>{
      'order': uuid,
      'idempotency_key': ?idempotencyKey,
    });
    return order(uuid);
  }

  /// P6: ofitsiant schyot so'radi (bir marta; schyotni kassa tuzadi). [printedOnLan] (P6 B bosqichi): markaz so'rovni
  /// LAN orqali internetsiz oldi va pre-check'ni o'zi chiqaradi — amalda `printed_on_lan: true`, server navbat yetganda
  /// uni qayta chiqarmaydi.
  Future<PosOrder> requestBill(
    String uuid, {
    bool printedOnLan = false,
    String? idempotencyKey,
  }) async {
    _guard('orders.manage');
    final PosOrder current = order(uuid);
    if (!current.isOpen) {
      throw const PosOfflineUnavailable('Buyurtma yopilgan.');
    }
    if (current.billRequestedAt != null) return current;
    await _enqueue(SyncOpType.billRequest, <String, Object?>{
      'order': uuid,
      if (printedOnLan) 'printed_on_lan': true,
      'idempotency_key': ?idempotencyKey,
      'view': <String, Object?>{'staff_name': _staffName()},
    });
    return order(uuid);
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
    _guard('pos.take_payment', finishing: true);
    // M5 S1 review: the payment and the close that follows it are one action. [forget] waits for it ([_composing]),
    // so the close cannot land in the middle of the setting aside, or after it in the next pairing's journal
    final Completer<void> whole = Completer<void>();
    _composing.add(whole.future);
    try {
      // M5 S6 (review 1 LOW1): who acts is read ONCE for the whole action: the end of a PIN-less continuation (the
      // 12 h, the clock) never falls between the payment and the close
      final _Acting who = _acting();
      return await runZoned(
        () => _pay(
          billUuid,
          tenders: tenders,
          shiftUuid: shiftUuid,
          idempotencyKey: idempotencyKey,
          tipStaffId: tipStaffId,
        ),
        zoneValues: <Object?, Object?>{_actingKey: who},
      );
    } finally {
      _composing.remove(whole.future);
      whole.complete();
    }
  }

  Future<PosBillResult> _pay(
    String billUuid, {
    required List<PosTenderInput> tenders,
    String? shiftUuid,
    String? idempotencyKey,
    int? tipStaffId,
  }) async {
    final String? shift = shiftUuid ?? this.shiftUuid();
    // M5 S6 (review 2 HIGH, safety net): a staff member resumed after a restart with no shift known on this till
    // (an older copy, a shift opened elsewhere) still takes the payment: it is queued without `shift_uuid` and the
    // server puts it in the till's current shift (PosSyncApplier::openShiftOf). A PIN-signed one is told to open one.
    if (shift == null && !(Zone.current[_actingKey] as _Acting).resumed) {
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
    await _enqueue(SyncOpType.billPay, <String, Object?>{
      'order': orderRef,
      'bill': billUuid,
      'shift_uuid': ?shift,
      'tenders': <Object?>[for (final PosTenderInput t in tenders) t.toJson()],
      // P6-22: the tip goes with its tender (`tip_amount`); whom it goes to (review P6 wave 2: someone other than
      // the waiter needs a manager's approval, which offline cannot be given — the server gives it to the waiter)
      'tip_staff_id': ?tipStaffId,
      'waiter_id': ?waiterId,
      'idempotency_key': ?idempotencyKey,
    });
    final PosBilling b = billing(orderRef);
    if (b.order.isClosed) {
      await _enqueue(SyncOpType.orderClose, <String, Object?>{
        'order': orderRef,
      });
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

  // --- M5 S1: uzilgan kassaning yuborilmagan amallari --------------------------------------------------------------

  /// Diskdagi hujjat kaliti old qo'shimchasi ([PosOrphanQueue.key]).
  static const String orphanPrefix = 'orphan_';
  static const String _deviceKey = '_device';
  static const String _ackKey = '_warnings_seen';

  /// On the disk while [held] (the queue could not be set aside): a restart must not start sending it.
  static const String _heldKey = '_held';

  final List<PosOrphanQueue> _orphans = <PosOrphanQueue>[];
  int _orphanSeq = 0;
  bool _held = false;
  bool _disposed = false;
  Future<void>? _stopping;
  Future<void>? _forgetting;

  /// Cashier actions that queue more than one operation and have passed [_guard] ([pay]): [forget] lets them finish
  /// before it sets the queue aside.
  final Set<Future<void>> _composing = <Future<void>>{};

  /// Avvalgi ulanishlardan qolgan, serverga yetmagan amallar (eng eskisi birinchi). Hech qachon o'z-o'zidan
  /// o'chirilmaydi va yuborilmaydi.
  List<PosOrphanQueue> get orphans =>
      List<PosOrphanQueue>.unmodifiable(_orphans);

  /// Kassa uzilgan, lekin navbatni chetga olib bo'lmadi (disk xatosi): jurnal joyida turibdi, hech narsa
  /// yuborilmaydi; keyingi [forget] yoki [bindDevice] yana urinadi.
  bool get held => _held;

  /// Navbat qaysi qurilma (ulanish) nomidan yig'ilyapti (noma'lum — `null`).
  String? get boundDevice => store.doc(_deviceKey)?.map?['uuid'] as String?;

  /// Kassa ulandi yoki xodim kirdi: navbat shu qurilmaniki. Navbatda BOSHQA ulanishdan qolgan amal bo'lsa (uzilish
  /// paytida chetga olib bo'lmagan), u avval chetga olinadi: eski amallar yangi token bilan yuborilmaydi.
  Future<void> bindDevice(String uuid, {bool paired = false}) async {
    final String? bound = boundDevice;
    if (bound == uuid) {
      // The same device is back (its queue was kept in place): it goes on
      if (_held) {
        _held = false;
        try {
          await store.removeDoc(_heldKey);
        } on Object {
          // Still marked: the next start holds the queue until this device signs in again
        }
        _resumeSender();
        _emit();
      }
      return;
    }
    if (paired && (store.hasPending || _held)) {
      await forget(reason: 'paired_again');
    }
    try {
      await store.putDoc(_deviceKey, <String, Object?>{'uuid': uuid});
    } on Object {
      // Not saved: the next sign-in writes it
    }
  }

  /// Terminal uzildi yoki server uni bekor qildi (401). M5 S1: serverga yetmagan amallar O'CHIRILMAYDI — navbatdan
  /// olinib, diskda alohida saqlanadi ([orphans]); shundan keyingina navbat, nusxalar va chek ro'yxati tozalanadi
  /// (yangi ulanish eski amallarni yangi token bilan yubormasin). Saqlab bo'lmasa — hech narsa o'chirilmaydi va
  /// yuborilmaydi ([held]).
  Future<void> forget({String reason = 'terminal_forgotten'}) {
    final Future<void> before = _forgetting ?? Future<void>.value();
    final Future<void> run = before.then((_) => _forget(reason));
    _forgetting = run;
    return run.whenComplete(() {
      if (identical(_forgetting, run)) _forgetting = null;
    });
  }

  Future<void> _forget(String reason) async {
    _printed.clear();
    _emitted.clear();
    // Nothing of this pairing may be sent with credentials that are gone (or with another pairing's)
    if (store.hasPending || _held) _stopSender();
    var unsent = false;
    try {
      // An operation accepted just before the till was cut off may still be on its way to the disk ([_guard] takes
      // no new one from here on): wait for it, so that it is set aside with the rest. First for an action that is
      // still queueing its operations (a payment and its close), then for the disk
      while (_composing.isNotEmpty) {
        await Future.wait(_composing.toList());
      }
      await store.removeDoc('_');
      unsent = store.hasPending || store.damaged.isNotEmpty;
      await _carryOrphans();
      if (unsent) await _setAside(reason);
      await store.clear();
    } on Object {
      // The disk failed: nothing is deleted, the journal stays where it is and is not sent
      _stopSender();
      _held = true;
      try {
        await store.putDoc(_heldKey, <String, Object?>{
          'reason': reason,
          'at': _clock().toUtc().toIso8601String(),
        });
      } on Object {
        // The disk refuses this too: held for this run; the next start still compares the journal's device
      }
      _emit();
      return;
    }
    settings = const PosOfflineSettings();
    sender.limits = settings.limits;
    _held = false;
    _resumeSender();
    _emit();
  }

  /// Stops the sender at once (nothing more leaves from this call on); the stop itself ends a little later.
  void _stopSender() => _stopping ??= sender.stop();

  /// Starts the sender again once a stop asked for earlier has ended (never before: the two would cross).
  void _resumeSender() {
    final Future<void>? stopping = _stopping;
    if (stopping == null) return;
    _stopping = null;
    unawaited(
      stopping.then((_) {
        if (!_held && !_disposed && _stopping == null) sender.start();
      }),
    );
  }

  String _orphanKey() {
    final String stamp = _clock().toUtc().toIso8601String().replaceAll(
      RegExp('[^0-9]'),
      '',
    );
    return '$orphanPrefix${stamp}_${++_orphanSeq}_${newUuid().substring(0, 8)}';
  }

  /// Writes the unsent operations (and the unreadable journal lines) to their own document, straight through the
  /// storage: the store does not know the key, so its `clear` leaves the file alone. Read back before anything is
  /// cleared.
  Future<void> _setAside(String reason) async {
    final DateTime now = _clock();
    final Map<String, Object?>? boot = store.doc(_bootstrapKey)?.map;
    final Map<String, Object?> value = <String, Object?>{
      'version': 1,
      'set_aside_at': now.toUtc().toIso8601String(),
      'reason': reason,
      'device_uuid': boundDevice,
      'store_id': (boot?['store'] as Map?)?['id'],
      'branch_id': (boot?['branch'] as Map?)?['id'],
      'last_seq': store.lastSeq,
      'operations': <Object?>[
        // M5 S3 review: a signed operation keeps its signature (`raw`, `mac`, `prev`, `kid`, `dev`) beside its
        // fields, so that the file can still be verified when it is imported
        for (final SyncOperation o in store.pending) o.toStored(),
      ],
      'damaged': <Object?>[
        for (final JournalDamage d in store.damaged)
          <String, Object?>{
            'line': d.line,
            'text': d.text,
            'found_at': d.foundAt.toUtc().toIso8601String(),
          },
      ],
    };
    final String key = _orphanKey();
    await store.storage.writeDoc(key, _envelope(value, now));
    final PosOrphanQueue? written = _readOrphan(
      key,
      await store.storage.readDoc(key),
    );
    if (written == null || written.count != store.pendingCount) {
      throw StateError('The unsent operations were not saved');
    }
    _orphans.add(written);
  }

  /// Orphans the store has loaded (an earlier start) would be deleted by its `clear`: each gets a copy under a key
  /// the store does not know, written before the old one goes.
  Future<void> _carryOrphans() async {
    for (final String key in store.docKeys(prefix: orphanPrefix).toList()) {
      final String? text = await store.storage.readDoc(key);
      if (text == null) continue;
      final String next = _orphanKey();
      await store.storage.writeDoc(next, text);
      final int i = _orphans.indexWhere((PosOrphanQueue o) => o.key == key);
      if (i >= 0) _orphans[i] = _orphans[i].withKey(next);
    }
  }

  /// The store's own document shape ([SyncStore.putDoc]), so that the next start loads it.
  String _envelope(Map<String, Object?> value, DateTime at) =>
      jsonEncode(<String, Object?>{
        'taken_at': at.toUtc().toIso8601String(),
        'seq': store.lastSeq,
        'value': value,
      });

  static PosOrphanQueue? _readOrphan(String key, String? text) {
    if (text == null) return null;
    try {
      final Object? j = jsonDecode(text);
      return j is Map ? PosOrphanQueue.tryParse(key, j['value']) : null;
    } on FormatException {
      return null;
    }
  }

  void _loadOrphans() {
    for (final String key in store.docKeys(prefix: orphanPrefix)) {
      final PosOrphanQueue? o = PosOrphanQueue.tryParse(
        key,
        store.doc(key)?.value,
      );
      if (o != null) _orphans.add(o);
    }
    _orphans.sort(
      (PosOrphanQueue a, PosOrphanQueue b) =>
          a.setAsideAt.compareTo(b.setAsideAt),
    );
  }

  /// Menejer ko'rishi kerak bo'lgan, hali "Tushunarli" bosilmagan ogohlantirishlar: jurnalning o'qilmagan qatorlari
  /// ([SyncStore.damaged]: tasdiqlangan sotuv serverda yo'q bo'lishi mumkin) va [orphans].
  PosOfflineWarnings get warnings {
    final DateTime? seen = DateTime.tryParse(
      '${store.doc(_ackKey)?.map?['until']}',
    );
    bool fresh(DateTime at) => seen == null || at.isAfter(seen);
    return PosOfflineWarnings(
      damagedLines: store.damaged
          .where((JournalDamage d) => fresh(d.foundAt))
          .length,
      orphans: <PosOrphanQueue>[
        for (final PosOrphanQueue o in _orphans)
          if (fresh(o.setAsideAt)) o,
      ],
      held: _held,
    );
  }

  /// Menejer ogohlantirishlarni ko'rdi ("Tushunarli"): shu paytgacha bo'lganlari lentada qayta chiqmaydi (ma'lumot
  /// o'chirilmaydi).
  Future<void> acknowledgeWarnings() async {
    try {
      await store.putDoc(_ackKey, <String, Object?>{
        'until': _clock().toUtc().toIso8601String(),
      });
    } on Object {
      // Not saved: the warning stays
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

  /// Hozir yuborish (aloqa qaytgan bo'lsa). The answer is that of an attempt made for this call: one that left earlier,
  /// while the network was down, is waited for and the queue sent afresh ([SyncSender.drain]).
  Future<SyncDrainResult> syncNow() {
    sender.nudge();
    return sender.drain();
  }

  /// Tells the server how many operations wait in the queue now (`GET /pos/sync/status?queue_size=`). M5 S3 review:
  /// the server refuses a new sync key (409 `queue_not_empty`) while the last queue the till reported was not empty;
  /// a till whose queue has gone since (set aside, or drained without a word) says so here. `false`: no answer.
  Future<bool> reportQueue() async {
    try {
      await _http.raw(
        'GET',
        '/v1/pos/sync/status',
        query: <String, Object?>{'queue_size': store.pendingCount},
        scope: AuthScope.terminal,
        idempotent: false,
      );
      return true;
    } on PosException {
      return false;
    }
  }

  /// M5 S1 review (design bug 5): asks the server for this device's `last_seq` (`GET /pos/sync/status`). `null`: no
  /// answer.
  Future<int?> _serverLastSeq() async {
    try {
      final PosResponse r = await _http.raw(
        'GET',
        '/v1/pos/sync/status',
        query: <String, Object?>{'queue_size': store.pendingCount},
        scope: AuthScope.terminal,
        idempotent: false,
      );
      return _lastSeqOf(r.data);
    } on PosException {
      return null;
    }
  }

  static int? _lastSeqOf(Object? status) {
    final Object? device = status is Map ? status['device'] : null;
    final Object? last = device is Map ? device['last_seq'] : null;
    return last is num ? last.toInt() : null;
  }

  /// The server knows a higher `last_seq` for this device than the journal does (the journal was lost or damaged while
  /// the pairing survived): the till goes on after the server's number ([SyncStore.adoptServerSeq]). [serverLast]: the
  /// number from a status answer already at hand; not given — the server is asked. `true`: the numbers changed.
  Future<bool> adoptServerSeq([int? serverLast]) async {
    final int? last = serverLast ?? await _serverLastSeq();
    if (last == null || last <= store.lastSeq) return false;
    try {
      return await store.adoptServerSeq(last);
    } on Object {
      // Not written: the next status answer (or the server's `seq_reused`) does it again
      return false;
    }
  }

  // --- ichki ------------------------------------------------------------------------------------------------------

  /// Who may act offline. M5 S1 (owner rule: payment is never blocked): the age limits (24 h without contact, a menu
  /// copy older than 24 h) are asked only when a NEW order is opened ([opening]). A round on an open order, the bill,
  /// the payment and the close never consult them.
  ///
  /// [changing]: the operation changes what is on an open order (a new round, a void). A continued staff member
  /// whose PIN login is more than 12 h old ([staffFinishOnly]) may not; the bill, the payment and the close never
  /// pass it.
  ///
  /// [finishing]: the payment, or the bill a payment needs (owner decision Q1: the only actions left past the 12 h,
  /// [staffWindowOver]; everything else is refused with [windowOverReason]).
  void _guard(
    String? permission, {
    bool opening = false,
    bool changing = false,
    bool finishing = false,
  }) {
    if (_forgetting != null) {
      // The till is being cut off right now: the queue is being set aside, nothing may join it
      throw const PosOfflineUnavailable(
        'Kassa serverdan uzildi: amal saqlanmadi. Kassani qayta ulang.',
      );
    }
    if (opening) {
      final OfflineStatus s = status;
      if (!s.canOpenOrders) {
        throw PosOfflineUnavailable(
          s.blockReason ?? 'Internet kerak: yangi buyurtma ochilmaydi.',
          reason: limitReason,
        );
      }
    }
    if (_actor == null && staffId() == null) {
      throw const PosOfflineUnavailable('Xodim kirmagan.');
    }
    // Owner decision Q1: past the 12 h only the payment and the close of open orders (and the bill they need)
    if (!finishing && _actor == null && staffWindowOver()) {
      throw const PosOfflineUnavailable(
        _windowOverMessage,
        reason: windowOverReason,
      );
    }
    // M5 S1-e: a staff member whose PIN session ran out during the outage finishes open orders, starts none
    if (opening && _continued) {
      throw const PosOfflineUnavailable(
        "PIN'siz davom etilmoqda: yangi buyurtma ochilmaydi. Faqat ochiq buyurtmalarni yakunlash va to'lov olish "
        'mumkin. Internet tiklangach PIN bilan kiring.',
        reason: continuedReason,
      );
    }
    if (changing && _continued && staffResumed()) {
      throw const PosOfflineUnavailable(
        "PIN'siz davom etilmoqda (ilova qayta ishga tushdi): yangi taom qo'shilmaydi va taom o'chirilmaydi. Faqat "
        "schyot, to'lov va buyurtmani yopish mumkin. Internet tiklangach PIN bilan kiring.",
        reason: continuedReason,
      );
    }
    if (changing && _continued && staffFinishOnly()) {
      throw const PosOfflineUnavailable(
        "PIN'siz davom etilmoqda, PIN bilan kirilganiga 12 soatdan oshdi: yangi taom qo'shilmaydi va taom "
        "o'chirilmaydi. Faqat schyot, to'lov va buyurtmani yopish mumkin. Internet tiklangach PIN bilan kiring.",
        reason: continuedReason,
      );
    }
    if (permission != null && !_can(permission)) {
      throw const PosOfflineUnavailable(
        "Bu amal uchun ruxsat yo'q (internetsiz menejer tasdig'i olinmaydi).",
        reason: 'permission_denied',
      );
    }
  }

  /// Who acts now: inside a composed action ([pay]) as it was read at its start, otherwise read here (which checks
  /// the end of a PIN-less continuation: `AuthSession.staffResumed`). Nobody: refused, nothing is queued.
  _Acting _acting() {
    final _Acting? composed = Zone.current[_actingKey] as _Acting?;
    if (composed != null) return composed;
    final PosOfflineActor? a = _actor;
    if (a != null) {
      return _Acting(
        staffId: a.staffId,
        continued: false,
        resumed: false,
        windowOver: false,
      );
    }
    final int? id = staffId();
    if (id == null) throw const PosOfflineUnavailable('Xodim kirmagan.');
    final bool continued = staffContinued();
    final bool resumed = continued && staffResumed();
    return _Acting(
      staffId: id,
      continued: continued,
      resumed: resumed,
      windowOver: resumed && staffWindowOver(),
    );
  }

  static final Object _actingKey = Object();

  /// The till's own staff member goes on without a PIN (a LAN actor has its own signed grant).
  bool get _continued => _actor == null && staffContinued();

  String? _staffName() => _actor == null ? staffName() : _actor!.staffName;

  bool _can(String permission) => _actor?.can(permission) ?? can(permission);

  // --- P6: lokal ko'prik ------------------------------------------------------------------------------------------

  static final Object _actorKey = Object();

  PosOfflineActor? get _actor => Zone.current[_actorKey] as PosOfflineActor?;

  /// P6 lokal ko'prik (docs/POS_SPEC.md 5.5): markaz boshqa qurilmaning (ofitsiant telefoni, KDS) amalini shu yerning
  /// o'z qoidalari bilan, lekin o'sha qurilmadagi xodim nomidan va huquqlari bilan qo'llaydi: [body] ichidagi amallar
  /// navbatga [actor] ning `op_id` si va qurilmasi ([SyncOperation.origin]) bilan yoziladi. Kassadagi xodim va
  /// boshqa bir vaqtdagi amallar o'zgarmaydi (zona bo'yicha).
  Future<T> actAs<T>(PosOfflineActor actor, Future<T> Function() body) =>
      runZoned(body, zoneValues: <Object?, Object?>{_actorKey: actor});

  Future<SyncOperation> _enqueue(String type, Map<String, Object?> payload) {
    final PosOfflineActor? a = _actor;
    final _Acting who = _acting();
    // Owner decision Q1: past the 12 h nothing but the payment, the close and the bill they need is queued, whatever
    // the caller checked ([_guard])
    if (who.windowOver && !windowOverTypes.contains(type)) {
      throw const PosOfflineUnavailable(
        _windowOverMessage,
        reason: windowOverReason,
      );
    }
    return store.enqueue(
      type,
      who.continued
          ? <String, Object?>{
              ...payload,
              continuedKey: true,
              if (who.resumed) resumedKey: true,
              if (who.windowOver) windowOverKey: true,
            }
          : payload,
      staffId: who.staffId,
      opId: a?._takeOpId(),
      origin: a?.device,
    );
  }

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
          for (final PosComboPart part in combo.parts)
            <String, Object?>{
              ...part.toJson(),
              // P6-34: each part's dish for the packaging rules (a cup per drink counts the combo's drink too)
              'category_id': part.productId == null
                  ? null
                  : cat.product(part.productId!)?.categoryId,
              'unit':
                  cat.variantByUuid(part.variantUuid ?? '')?.unit.wire ??
                  'piece',
            },
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

  /// The connection state changes on every answer (the contact time) and every queue change. Only the way back from
  /// offline (no answer -> an answer, from any request, the probe or the sync request itself) nudges the sender: the
  /// backoff wait is cancelled and the queue goes at once ([SyncSender.nudge]: an attempt already on the wire is not
  /// doubled, and is made again only if it never got through). Other answers, while the server refuses or stops the
  /// queue (5xx, `stopped_at`), do not cut the backoff short; the cashier's "send now" ([syncNow]) always can.
  void _onConnectivity(PosConnectivityState s) {
    final bool back = s.online && !_wasOnline;
    _wasOnline = s.online;
    if (s.online) {
      final DateTime? at = s.lastContactAt;
      if (at != null) unawaited(store.markContact(at));
      _stopProbe();
      if (back && store.hasPending) sender.nudge();
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
        final PosResponse r = await _http.raw(
          'GET',
          '/v1/pos/sync/status',
          query: <String, Object?>{'queue_size': store.pendingCount},
          scope: AuthScope.terminal,
          idempotent: false,
        );
        _probeFailures = 0;
        // M5 S1 review (design bug 5): a server that is ahead of the journal
        final int? last = _lastSeqOf(r.data);
        if (last != null && !_held && _forgetting == null) {
          await adoptServerSeq(last);
        }
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
    _disposed = true;
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

/// P6 lokal ko'prik: boshqa qurilmadagi xodim (markaz uning amalini qo'llaganda, [PosOffline.actAs]): server imzolagan
/// grantdan — kim, huquqlari va qaysi qurilmada; [opId] — o'sha qurilma bergan amal id'si (navbatga shu bilan yoziladi).
class PosOfflineActor {
  PosOfflineActor({
    required this.staffId,
    required this.device,
    required Set<String> permissions,
    this.staffName,
    this.role,
    this.opId,
  }) : permissions = Set<String>.unmodifiable(permissions);

  final int staffId;
  final String? staffName;
  final String? role;

  /// Amal qilingan qurilma uuid'si.
  final String device;
  final Set<String> permissions;
  String? opId;

  bool can(String permission) => permissions.contains(permission);

  /// The first queued operation takes the device's op id (a later one, e.g. a close after a payment, gets its own).
  String? _takeOpId() {
    final String? id = opId;
    opId = null;
    return id;
  }
}

/// Who queues an operation (`PosOffline._acting`): read once per composed action.
class _Acting {
  const _Acting({
    required this.staffId,
    required this.continued,
    required this.resumed,
    required this.windowOver,
  });

  final int staffId;
  final bool continued;
  final bool resumed;

  /// Owner decision Q1: past the 12 h (`PosOffline.staffWindowOver`).
  final bool windowOver;
}
