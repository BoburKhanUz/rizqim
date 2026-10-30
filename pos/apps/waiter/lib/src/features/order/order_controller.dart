import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';

import '../../common/errors.dart';
import 'courses.dart';

/// Amalni (kerak bo'lsa menejer tasdig'i bilan) bajaradigan funksiya: sahifa uni `withApproval(context, ...)` bilan
/// ulaydi.
typedef Approving = Future<T> Function<T>(
  Future<T> Function(String? approvalToken) action,
);

/// Bitta buyurtma ekrani holati va amallari. Server — yagona haqiqat: har amal to'liq buyurtmani qaytaradi va shu
/// ko'rinish ekranga qo'yiladi; narx va xizmat haqini server hisoblaydi.
///
/// * **Ofitsiant qoidasi.** Boshqa ofitsiantning buyurtmasi ([isForeign]) avval faqat ko'rish uchun; tahrirlash
///   ([startEditing]) yoqilgach har amal 403 `approval_required` oladi va tasdiq oynasi ochiladi.
/// * **Aloqa yo'q** ([online] `false`): hamma yozuv amali o'chiq, ekran faqat ko'rsatadi. Navbat yo'q.
/// * **Tarmoq xatosi amal paytida:** xuddi shu amal qayta bosilsa o'sha `Idempotency-Key` yuboriladi (server ikki
///   marta bajarmaydi).
class OrderController extends ChangeNotifier {
  OrderController({
    required this.data,
    required this.uuid,
    PosOrder? initial,
    this.realtime,
    this.pollInterval = const Duration(seconds: 15),
    this.livePollInterval = const Duration(seconds: 60),
    this.retryInterval = const Duration(seconds: 5),
  }) : order = initial;

  final PosData data;
  final String uuid;
  final PosRealtime? realtime;
  final Duration pollInterval;
  final Duration livePollInterval;
  final Duration retryInterval;

  PosOrder? order;
  PosCatalog? catalog;
  Object? loadError;
  bool loading = false;
  bool busy = false;

  /// Boshqa ofitsiantning buyurtmasi: tahrirlash yoqildi.
  bool editingForeign = false;

  /// Ofitsiant kassadan schyot so'ragan (P3-26: serverdagi `bill_requested_at`; kassada "Schyot so'raldi").
  bool get billRequested => order?.billRequestedAt != null;

  /// Schyot chiqarilgandan keyin buyurtma o'zgargan: kassir schyotni qayta chiqaradi.
  bool billStale = false;

  /// Oxirgi amalning xatosi (oyna ichida ko'rsatish uchun; keyingi amal boshida tozalanadi).
  String? lastError;

  /// Qisqa xabar (ekran uni pastda ko'rsatadi): `(matn, xatomi)`.
  void Function(String message, {bool error})? onNotice;

  PosRefreshScheduler? _scheduler;
  StreamSubscription<PosCatalog>? _catalogSub;
  Timer? _retry;

  /// Tarmoq xatosida javobi kelmagan amallar: kaliti va yuborilgan versiyasi (amal turi va tanasi bo'yicha).
  final Map<String, ({String key, int version})> _retryKeys =
      <String, ({String key, int version})>{};
  bool _disposed = false;

  PosSession get session => data.session;
  bool get online => data.connectivity.state.online;
  bool get closed => order != null && !order!.isOpen;

  /// Buyurtma boshqa ofitsiantniki va kirgan xodim ofitsiant (menejer va kassirga qoida tegmaydi).
  bool get isForeign {
    final PosOrder? o = order;
    final int? waiter = o?.waiter?.id;
    return o != null &&
        session.staffRole == 'waiter' &&
        waiter != null &&
        waiter != session.staffId;
  }

  /// Amal tugmalari ko'rinadimi (ochiq buyurtma, o'ziniki yoki tahrirlash yoqilgan).
  bool get editable =>
      order != null && !closed && (!isForeign || editingForeign);

  /// Amal tugmalari bosiladimi.
  bool get canAct => editable && online && !busy;

  Iterable<PosOrderItem> get readyItems =>
      order?.items.where(
        (PosOrderItem i) => i.kitchenStatus == PosKitchenStatus.ready,
      ) ??
      const <PosOrderItem>[];

  /// Hozir yuboriladigan (`now`) yuborilmagan qatorlar.
  Iterable<PosOrderItem> get pendingNow =>
      order?.items.where(
        (PosOrderItem i) =>
            i.kitchenStatus == PosKitchenStatus.pending &&
            i.fireMode != PosFireMode.hold,
      ) ??
      const <PosOrderItem>[];

  void start() {
    _catalogSub = data.catalog.changes.listen((PosCatalog c) {
      if (c.channel == PosChannel.hall) {
        catalog = c;
        _notify();
      }
    });
    _scheduler = PosRefreshScheduler(
      realtime: realtime,
      pollInterval: pollInterval,
      livePollInterval: livePollInterval,
      eventFilter: (PosEvent e) => e.orderUuid == uuid,
      onRefresh: (PosRefreshReason _) => load(silent: true),
    )..start();
    unawaited(load());
  }

  Future<void> refresh() =>
      _scheduler == null ? load() : _scheduler!.refreshNow();

  Future<void> load({bool silent = false}) async {
    if (_disposed) return;
    if (!silent && order == null) {
      loading = true;
      _notify();
    }
    try {
      order = await data.orders.get(uuid);
      loadError = null;
      _retry?.cancel();
      _retry = null;
      unawaited(_loadBill());
    } on Object catch (e) {
      loadError = e;
      if (e is PosNetworkException) {
        _retry ??= Timer(retryInterval, () {
          _retry = null;
          unawaited(load(silent: true));
        });
      }
    } finally {
      loading = false;
      _notify();
    }
  }

  Future<void> _loadBill() async {
    if (order?.billLocked != true) {
      billStale = false;
      return;
    }
    try {
      final PosBilling b = await data.billing.bills(uuid);
      billStale = b.order.billsOutOfDate;
      _notify();
    } on Object {
      // Schyot holati ixtiyoriy ma'lumot: ko'rinmasa ham ish davom etadi
    }
  }

  /// Menyu (kesh bo'yicha); yuklab bo'lmasa xato tashlaydi.
  Future<PosCatalog> ensureCatalog() async {
    final PosCatalog? known = catalog ?? data.catalog.cached(PosChannel.hall);
    if (known != null) {
      catalog = known;
      return known;
    }
    final PosCatalog loaded = await data.catalog.load(
      PosChannel.hall,
      menuVersion: session.bootstrap?.menuVersion,
    );
    catalog = loaded;
    _notify();
    return loaded;
  }

  /// Stop-list va qoldiq yangilanishi (menyu sahifasi ochilganda): xato jim o'tkaziladi.
  Future<void> refreshAvailability() async {
    final int? store = session.bootstrap?.store.id;
    if (store == null) return;
    try {
      await data.catalog.refreshAvailability(
        storeId: store,
        branchId: session.branchId,
      );
    } on Object {
      // Eski qoldiq bilan davom etamiz: server baribir tekshiradi
    }
  }

  void startEditing() {
    editingForeign = true;
    _notify();
  }

  // --- amallar ---------------------------------------------------------------------------------------------------

  /// Yangi bosqich ([PosFireMode.now] — darhol oshxonaga, [PosFireMode.hold] — ushlab turish).
  Future<bool> addRound(
    Approving approving,
    List<PosLineInput> lines,
    PosFireMode mode,
  ) {
    final String sig =
        'round:${mode.wire}:${jsonEncode(<Object?>[for (final PosLineInput l in lines) l.toJson()])}';
    return _change(
      approving,
      sig,
      (String? token, int version, String? key) => data.orders.addRound(
        uuid,
        version: version,
        items: lines,
        fireMode: mode,
        send: mode == PosFireMode.now,
        approvalToken: token,
        idempotencyKey: key,
      ),
      success: mode == PosFireMode.now
          ? 'Bosqich oshxonaga yuborildi.'
          : 'Bosqich saqlandi (ushlab turilgan).',
    );
  }

  /// Yuborilmagan hamma `now` qatorlarni oshxonaga.
  Future<bool> sendPending(Approving approving) => _change(
    approving,
    'send',
    (String? token, int version, String? key) => data.orders.send(
      uuid,
      version: version,
      approvalToken: token,
      idempotencyKey: key,
    ),
    success: 'Oshxonaga yuborildi.',
  );

  /// Bitta (masalan, ushlab turilgan) qatorni oshxonaga.
  Future<bool> sendLine(Approving approving, int itemId) => _change(
    approving,
    'send:$itemId',
    (String? token, int version, String? key) => data.orders.send(
      uuid,
      version: version,
      itemIds: <int>[itemId],
      approvalToken: token,
      idempotencyKey: key,
    ),
    success: 'Oshxonaga yuborildi.',
  );

  /// Taom mehmonga berildi.
  Future<bool> markServed(Approving approving, int itemId) => _change(
    approving,
    'served:$itemId',
    (String? token, int version, String? key) => data.orders.markServed(
      uuid,
      itemId,
      version: version,
      approvalToken: token,
      idempotencyKey: key,
    ),
    success: 'Taom berildi.',
  );

  /// Hamma tayyor taomlar berildi (bittadan, har safar yangi versiya bilan). Birinchi xatoda to'xtaydi.
  Future<bool> markAllServed(Approving approving) async {
    final List<int> ids = <int>[for (final PosOrderItem i in readyItems) i.id];
    var done = 0;
    for (final int id in ids) {
      final bool ok = await _change(
        approving,
        'served:$id',
        (String? token, int version, String? key) => data.orders.markServed(
          uuid,
          id,
          version: version,
          approvalToken: token,
          idempotencyKey: key,
        ),
      );
      if (!ok) return false;
      done++;
    }
    if (done > 0) onNotice?.call('$done ta taom berildi.');
    return done > 0;
  }

  /// Yuborilmagan qatorni o'chirish (yuborilganini faqat kassir/menejer o'chiradi).
  Future<bool> voidPending(Approving approving, int itemId) => _change(
    approving,
    'void:$itemId',
    (String? token, int version, String? key) => data.orders.voidItem(
      uuid,
      itemId,
      version: version,
      approvalToken: token,
      idempotencyKey: key,
    ),
    success: "Taom o'chirildi.",
  );

  Future<bool> setGuests(Approving approving, int guests) => _change(
    approving,
    'guests:$guests',
    (String? token, int version, String? key) => data.orders.setGuests(
      uuid,
      version: version,
      guestsCount: guests,
      approvalToken: token,
      idempotencyKey: key,
    ),
    success: 'Mehmonlar soni yangilandi.',
  );

  Future<bool> moveTo(Approving approving, int tableId) => _change(
    approving,
    'move:$tableId',
    (String? token, int version, String? key) => data.orders.move(
      uuid,
      version: version,
      tableId: tableId,
      approvalToken: token,
      idempotencyKey: key,
    ),
    success: "Buyurtma ko'chirildi.",
  );

  // --- P6 2-to'lqin: kurslar, og'irlik, mehmon, ofitsiantni almashtirish -------------------------------------------

  /// Buyurtma kurslari (serverning `courses[]`; eski javobda — qatorlardan).
  List<PosCourse> get courses {
    final PosOrder? o = order;
    return o == null ? const <PosCourse>[] : coursesOf(o);
  }

  /// Og'irligi kutilayotgan qatorlar soni (schyot ular tortilguncha chiqmaydi).
  int get weightPending {
    final PosOrder? o = order;
    if (o == null) return 0;
    final int local = o.items
        .where((PosOrderItem i) => i.awaitingWeight)
        .length;
    return o.weightPending > local ? o.weightPending : local;
  }

  /// Keyingi yuboriladigan kurs (eng kichik, yuboriladigani bor); yo'q — `null`.
  PosCourse? get nextCourse {
    for (final PosCourse c in courses) {
      if (c.canSend) return c;
    }
    return null;
  }

  /// P6-21 "N-kursni yuborish": kursning ushlab turilgan qatorlari oshxonaga.
  Future<bool> sendCourse(Approving approving, int courseNo) => _change(
    approving,
    'course:$courseNo',
    (String? token, int version, String? key) => data.orders.sendCourse(
      uuid,
      courseNo,
      version: version,
      approvalToken: token,
      idempotencyKey: key,
    ),
    success: '$courseNo-kurs oshxonaga yuborildi.',
  );

  /// P6-25: og'irlik kutayotgan qatorning haqiqiy og'irligi (qayta tortish — menejer tasdig'i bilan).
  Future<bool> setWeight(
    Approving approving,
    int itemId,
    PosQuantity quantity,
  ) => _change(
    approving,
    'weight:$itemId:${quantity.toApiString()}',
    (String? token, int version, String? key) => data.orders.setWeight(
      uuid,
      itemId,
      version: version,
      quantity: quantity,
      approvalToken: token,
      idempotencyKey: key,
    ),
    success: "Og'irlik kiritildi.",
  );

  /// P6-24: qatorning mehmoni (`null` — umumiy).
  Future<bool> setSeat(Approving approving, int itemId, int? seatNo) => _change(
    approving,
    'seat:$itemId:${seatNo ?? ''}',
    (String? token, int version, String? key) => data.hostess.setLineSeat(
      uuid,
      itemId,
      version: version,
      seatNo: seatNo,
      approvalToken: token,
      idempotencyKey: key,
    ),
    success: seatNo == null
        ? 'Taom umumiy qilindi.'
        : 'Taom $seatNo-mehmonga yozildi.',
  );

  /// P6-24: stolni boshqa ofitsiantga berish (`orders.transfer` yoki menejer PIN tasdig'i).
  Future<bool> transferTo(
    Approving approving,
    PosStaffRef waiter, {
    String? reason,
  }) async {
    final bool ok = await _change(
      approving,
      'transfer:${waiter.id}',
      (String? token, int version, String? key) => data.hostess.transferOrder(
        uuid,
        version: version,
        waiterId: waiter.id,
        reason: reason,
        approvalToken: token,
        idempotencyKey: key,
      ),
      success: 'Stol berildi: ${waiter.name}.',
    );
    // The order is someone else's now: the page goes back to read-only
    if (ok) editingForeign = false;
    return ok;
  }

  /// "Schyot so'rash" (P3-26: `POST /pos/orders/{uuid}/request-bill`): kassaga `order.bill_requested` boradi, zal
  /// xaritasida "Schyot so'raldi"; schyotni kassir tuzadi, chiqaradi va to'lovni oladi (ofitsiant ilovasida to'lov va
  /// chek yo'q). Takroriy so'rov o'zgarishsiz qaytadi.
  Future<bool> requestBill(Approving approving) async {
    final PosOrder? current = order;
    if (current == null || busy || !online) return false;
    busy = true;
    lastError = null;
    _notify();
    const String sig = 'bill';
    final ({String key, int version})? retry = _retryKeys[sig];
    final int version = retry?.version ?? current.version;
    try {
      final PosOrder next = await approving<PosOrder>(
        (String? token) => _remembering(
          sig,
          token,
          version,
          () => data.orders.requestBill(
            uuid,
            version: version,
            approvalToken: token,
            idempotencyKey: token == null ? retry?.key : null,
          ),
        ),
      );
      _retryKeys.remove(sig);
      order = next;
      onNotice?.call("Schyot so'raldi: kassir uni chiqaradi.");
      return true;
    } on Object catch (e) {
      _forgetUnlessLost(sig, e);
      await _handleFailure(e);
      return false;
    } finally {
      busy = false;
      _notify();
    }
  }

  // --- ichki -----------------------------------------------------------------------------------------------------

  Future<bool> _change(
    Approving approving,
    String signature,
    Future<PosOrder> Function(String? token, int version, String? key) call, {
    String? success,
  }) async {
    final PosOrder? current = order;
    if (current == null || busy || !online) return false;
    busy = true;
    lastError = null;
    _notify();
    // The same action again after a lost answer: the very same request (key, version, body), so the server answers
    // it once whether the first one reached it or not; a newer version would make it a new request (applied twice)
    final ({String key, int version})? retry = _retryKeys[signature];
    final int version = retry?.version ?? current.version;
    try {
      final PosOrder next = await approving<PosOrder>(
        (String? token) => _remembering(
          signature,
          token,
          version,
          () => call(token, version, token == null ? retry?.key : null),
        ),
      );
      _retryKeys.remove(signature);
      order = next;
      if (retry != null) {
        // A replayed answer may be older than what the poll has shown since
        try {
          order = await data.orders.get(uuid);
        } on Object {
          // the replayed one stays until the next poll
        }
      }
      if (order!.billLocked) billStale = true;
      if (success != null) onNotice?.call(success);
      return true;
    } on Object catch (e) {
      _forgetUnlessLost(signature, e);
      await _handleFailure(e);
      return false;
    } finally {
      busy = false;
      _notify();
    }
  }

  /// Tarmoq xatosida amalning kaliti va versiyasini eslab qoladi: shu amal qayta bosilsa aynan o'sha so'rov ketadi.
  Future<T> _remembering<T>(
    String signature,
    String? token,
    int version,
    Future<T> Function() call,
  ) async {
    try {
      return await call();
    } on PosNetworkException catch (e) {
      if (token == null && e.idempotencyKey != null) {
        _retryKeys[signature] = (key: e.idempotencyKey!, version: version);
      }
      rethrow;
    }
  }

  /// Server javob berdi (rad etdi, versiya to'qnashuvi...): keyingi urinish yangi so'rov.
  void _forgetUnlessLost(String signature, Object e) {
    if (e is! PosNetworkException && e is! PosApprovalCancelled) {
      _retryKeys.remove(signature);
    }
  }

  Future<void> _handleFailure(Object e) async {
    if (e is PosApprovalCancelled) return;
    if (e is PosVersionConflict || e is PosException && e.needsOrderReload) {
      // Boshqa qurilma o'zgartirgan (P3-35: yoki schyoti to'lana boshlagan): yangi ko'rinishni olib, amalni qayta
      // bajarishni so'raymiz
      try {
        order = await data.orders.get(uuid);
        unawaited(_loadBill());
      } on Object {
        // yangilab bo'lmadi: xabar baribir ko'rsatiladi
      }
    }
    final String text = describeError(e);
    if (text.isEmpty) return;
    lastError = text;
    onNotice?.call(text, error: true);
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _retry?.cancel();
    unawaited(_catalogSub?.cancel());
    final PosRefreshScheduler? s = _scheduler;
    if (s != null) unawaited(s.dispose());
    super.dispose();
  }
}
