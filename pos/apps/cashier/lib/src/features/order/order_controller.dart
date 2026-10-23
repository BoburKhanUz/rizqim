import 'dart:async';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';

import 'frequent_items.dart';
import 'kitchen_tickets.dart';
import 'order_draft.dart';
import 'order_format.dart';

/// Menejer tasdig'i bilan bajaruvchi (sahifada `withApproval(context, ...)`).
typedef ApprovalRunner = Future<T> Function<T>(
  Future<T> Function(String? approvalToken) action,
);

/// Foydalanuvchiga xabar (sahifa SnackBar qilib ko'rsatadi).
class OrderNotice {
  const OrderNotice(this.message, {this.error = false});

  final String message;
  final bool error;
}

/// Buyurtma muharriri holati: buyurtma (server), menyu, yangi bosqich qoralamasi va amallar.
///
/// * Har amal `version` bilan; [PosVersionConflict] bo'lsa buyurtma qayta yuklanadi. Bosqich qo'shish, yuborish,
///   "berildi" va mehmonlar qayta yuklangan versiya bilan **o'zi takrorlanadi** (ma'nosi o'zgarmaydi); o'chirish,
///   ko'chirish, birlashtirish, xizmat qatori va bekor qilish takrorlanmaydi — foydalanuvchiga yangilangan buyurtmani
///   ko'rib, qayta bajarish aytiladi.
/// * Tarmoq xatosidan keyin aynan o'sha bosqich o'sha `Idempotency-Key` bilan qayta yuboriladi (ikki marta tushmaydi).
/// * Oshxonaga ketgan qatorlar markasi [KitchenTickets] orqali chiqadi (sotuvni to'xtatmaydi).
class OrderController extends ChangeNotifier {
  OrderController({
    required this.data,
    required this.tickets,
    required this.frequent,
    String? orderUuid,
    PosChannel draftChannel = PosChannel.takeaway,
    Random? random,
  }) : _uuid = orderUuid,
       _random = random ?? Random() {
    _draftChannel = draftChannel;
  }

  final PosData data;
  final KitchenTickets tickets;
  final FrequentItems frequent;
  final Random _random;

  String? _uuid;
  late PosChannel _draftChannel;
  PosOrder? _order;
  PosCatalog? _catalog;
  PosException? _loadError;
  bool _loading = false;
  bool _busy = false;
  bool _submitting = false;
  bool _disposed = false;
  final List<DraftLine> _draft = <DraftLine>[];
  bool _holdRound = false;
  int _draftRevision = 0;
  var _keySeq = 0;
  String? _roundKey;
  String? _roundKeyFor;
  String? _externalId;
  int _draftGuests = 1;
  StreamSubscription<PosCatalog>? _catalogSub;
  final StreamController<OrderNotice> _notices =
      StreamController<OrderNotice>.broadcast();

  String? get uuid => _uuid;
  PosOrder? get order => _order;
  PosCatalog? get catalog => _catalog;
  PosException? get loadError => _loadError;
  bool get loading => _loading;

  /// Amal bajarilmoqda (tugmalar o'chiriladi).
  bool get busy => _busy;

  /// "Yuborish"/"Saqlash" so'rovi ketmoqda (tugmada aylana).
  bool get submitting => _submitting;
  List<DraftLine> get draft => List<DraftLine>.unmodifiable(_draft);
  bool get holdRound => _holdRound;
  Stream<OrderNotice> get notices => _notices.stream;

  /// Hali ochilmagan stolsiz buyurtma (tez kassa): birinchi "Yuborish"/"Saqlash" uni ochadi.
  bool get isDraftOrder => _uuid == null;
  PosChannel get channel =>
      _order?.posChannel ?? (_uuid == null ? _draftChannel : PosChannel.hall);
  int get draftGuests => _draftGuests;

  bool get isOpen => _order == null ? _uuid == null : _order!.isOpen;

  /// Oshxonaga hali yuborilmagan "hozir" qatorlar (bosqich saqlangan, yuborilmagan).
  List<PosOrderItem> get unsentNow => <PosOrderItem>[
    for (final PosOrderItem i in _order?.unsentItems ?? const <PosOrderItem>[])
      if (i.fireMode != PosFireMode.hold) i,
  ];

  /// Ushlab turilgan qatorlar.
  List<PosOrderItem> get held => <PosOrderItem>[
    for (final PosOrderItem i in _order?.unsentItems ?? const <PosOrderItem>[])
      if (i.fireMode == PosFireMode.hold) i,
  ];

  PosMoney get draftEstimate =>
      PosMoney.sum(_draft.map((DraftLine l) => l.estimate));

  // --- yuklash ----------------------------------------------------------------------------------------------------

  /// Buyurtma va menyu (menyu keshdan, bootstrap `menu_version` bo'yicha).
  Future<void> load() async {
    _loading = true;
    _loadError = null;
    _notify();
    _catalogSub ??= data.catalog.changes.listen((PosCatalog c) {
      if (c.channel == channel) {
        _catalog = c;
        _notify();
      }
    });
    try {
      final String? id = _uuid;
      if (id != null) _order = await data.orders.get(id);
      await _loadCatalog();
    } on PosException catch (e) {
      _loadError = e;
    } finally {
      _loading = false;
      _notify();
    }
  }

  Future<void> _loadCatalog() async {
    final PosChannel c = channel;
    _catalog =
        data.catalog.cached(c) ??
        await data.catalog.load(
          c,
          menuVersion: data.session.bootstrap?.menuVersion,
        );
  }

  /// Serverdan qayta (real-time hodisa, polling). Amal bajarilayotganda — o'tkazib yuboriladi.
  Future<void> refresh() async {
    final String? id = _uuid;
    if (id == null || _busy) return;
    try {
      final PosOrder fresh = await data.orders.get(id);
      if (_busy) return;
      if (_order == null || fresh.version >= _order!.version) {
        _order = fresh;
        _notify();
      }
    } on PosException {
      // Keyingi yangilashda qayta urinadi
    }
  }

  /// Stop-list yoki qozon o'zgardi.
  Future<void> refreshAvailability() async {
    final int? storeId = data.session.bootstrap?.store.id;
    if (storeId == null) return;
    try {
      await data.catalog.refreshAvailability(storeId: storeId);
    } on PosException {
      // Keyingi hodisada
    }
  }

  /// Tez kassa: olib ketish yoki zalda stolsiz (narxlar kanal bo'yicha).
  Future<void> setDraftChannel(PosChannel next) async {
    if (!isDraftOrder || next == _draftChannel) return;
    _draftChannel = next;
    _draft.clear();
    _touchDraft();
    try {
      await _loadCatalog();
    } on PosException catch (e) {
      _emit(posErrorText(e), error: true);
    }
    _notify();
  }

  void setDraftGuests(int n) {
    _draftGuests = n < 1 ? 1 : n;
    _notify();
  }

  // --- qoralama (yangi bosqich) -------------------------------------------------------------------------------------

  /// Taom qo'shadi; bir xili bo'lsa miqdori oshadi.
  void addLine(
    PosProduct product,
    PosVariant variant,
    PosQuantity quantity, {
    Map<String, int> modifiers = const <String, int>{},
    String? note,
    int? seatNo,
    PosFireMode? fireMode,
  }) {
    final bool plain =
        (note == null || note.trim().isEmpty) &&
        seatNo == null &&
        fireMode == null;
    if (plain) {
      for (final DraftLine l in _draft) {
        if (l.canMergeWith(variant, modifiers)) {
          l.quantity = l.quantity + quantity;
          _touchDraft();
          return;
        }
      }
    }
    _draft.add(
      DraftLine(
        key: ++_keySeq,
        product: product,
        variant: variant,
        quantity: quantity,
        modifiers: Map<String, int>.of(modifiers),
        note: note,
        seatNo: seatNo,
        fireMode: fireMode,
      ),
    );
    _touchDraft();
  }

  void setQuantity(int key, PosQuantity q) {
    final DraftLine? l = _line(key);
    if (l == null) return;
    if (!q.isPositive) return removeLine(key);
    l.quantity = q;
    _touchDraft();
  }

  void updateLine(
    int key, {
    required PosQuantity quantity,
    required Map<String, int> modifiers,
    String? note,
    int? seatNo,
    PosFireMode? fireMode,
  }) {
    final DraftLine? l = _line(key);
    if (l == null) return;
    l
      ..quantity = quantity
      ..modifiers = Map<String, int>.of(modifiers)
      ..note = (note == null || note.trim().isEmpty) ? null : note.trim()
      ..seatNo = seatNo
      ..fireMode = fireMode;
    _touchDraft();
  }

  void removeLine(int key) {
    _draft.removeWhere((DraftLine l) => l.key == key);
    _touchDraft();
  }

  void clearDraft() {
    _draft.clear();
    _touchDraft();
  }

  /// "Hozir" yoki "ushlab turish" (yangi bosqichning standart rejimi).
  void setHoldRound(bool hold) {
    _holdRound = hold;
    _touchDraft();
  }

  DraftLine? _line(int key) {
    for (final DraftLine l in _draft) {
      if (l.key == key) return l;
    }
    return null;
  }

  void _touchDraft() {
    _draftRevision++;
    _notify();
  }

  // --- amallar ------------------------------------------------------------------------------------------------------

  /// "Yuborish" ([send]) yoki "Saqlash": qoralama bitta so'rovda bosqich bo'ladi (va oshxonaga ketadi). Qoralama bo'sh
  /// va [send] — saqlangan, yuborilmagan "hozir" qatorlar yuboriladi.
  Future<bool> submit({
    required bool send,
    required ApprovalRunner approve,
  }) async {
    if (_busy) return false;
    _submitting = true;
    try {
      return await _submit(send: send, approve: approve);
    } finally {
      _submitting = false;
      _notify();
    }
  }

  Future<bool> _submit({
    required bool send,
    required ApprovalRunner approve,
  }) async {
    if (_draft.isEmpty) {
      if (!send || unsentNow.isEmpty) return false;
      return _mutate(
        (int v, String? t) =>
            data.orders.send(_uuid!, version: v, approvalToken: t),
        approve: approve,
        reapply: true,
        success: 'Oshxonaga yuborildi',
      );
    }
    final List<PosLineInput> items = <PosLineInput>[
      for (final DraftLine l in _draft) l.toInput(),
    ];
    final PosFireMode? mode = _holdRound ? PosFireMode.hold : null;
    final List<DraftLine> sentDraft = List<DraftLine>.of(_draft);
    final int revision = _draftRevision;
    final bool ok;
    if (_uuid == null) {
      ok = await _openWithDraft(items, mode, send, approve);
    } else {
      ok = await _mutate(
        (int v, String? t) => data.orders.addRound(
          _uuid!,
          version: v,
          items: items,
          fireMode: mode,
          send: send,
          approvalToken: t,
          idempotencyKey: t == null
              ? _keyFor('round:$revision:$v:$send')
              : null,
        ),
        approve: approve,
        reapply: true,
        success: send && !_holdRound ? 'Oshxonaga yuborildi' : 'Saqlandi',
      );
    }
    if (ok) {
      for (final DraftLine l in sentDraft) {
        frequent.record(l.product.id);
      }
      if (_draftRevision == revision) {
        _draft.clear();
        _holdRound = false;
        _touchDraft();
      }
    }
    return ok;
  }

  Future<bool> _openWithDraft(
    List<PosLineInput> items,
    PosFireMode? mode,
    bool send,
    ApprovalRunner approve,
  ) async {
    _busy = true;
    _notify();
    _externalId ??= _randomId();
    try {
      final List<PosLineInput> lines = mode == null
          ? items
          : <PosLineInput>[
              for (final PosLineInput i in items)
                PosLineInput(
                  variantUuid: i.variantUuid,
                  quantity: i.quantity,
                  modifiers: i.modifiers,
                  note: i.note,
                  seatNo: i.seatNo,
                  fireMode: i.fireMode ?? mode,
                ),
            ];
      final PosOrder created = await approve<PosOrder>(
        (String? t) => data.orders.open(
          channel: _draftChannel,
          guestsCount: _draftChannel == PosChannel.hall ? _draftGuests : null,
          externalId: _externalId,
          items: lines,
          send: send,
          approvalToken: t,
          idempotencyKey: t == null
              ? _keyFor('open:$_draftRevision:$send')
              : null,
        ),
      );
      _uuid = created.uuid;
      _order = created;
      unawaited(tickets.printSent(null, created));
      _emit(send ? 'Buyurtma ochildi va yuborildi' : 'Buyurtma ochildi');
      return true;
    } on PosApprovalCancelled {
      return false;
    } on PosException catch (e) {
      _emit(posErrorText(e), error: true);
      return false;
    } finally {
      _busy = false;
      _notify();
    }
  }

  /// Ushlab turilgan (yoki tanlangan) qatorlarni hozir oshxonaga.
  Future<bool> sendItems(
    List<int> itemIds, {
    required ApprovalRunner approve,
  }) => _mutate(
    (int v, String? t) => data.orders.send(
      _uuid!,
      version: v,
      itemIds: itemIds,
      approvalToken: t,
    ),
    approve: approve,
    reapply: true,
    success: 'Oshxonaga yuborildi',
  );

  /// Qatorni o'chirish (yuborilgan bo'lsa sabab va tasdiq; "O'CHIRILDI" markasi).
  Future<bool> voidItem(
    PosOrderItem item, {
    String? reason,
    bool? restock,
    required ApprovalRunner approve,
  }) async {
    final bool ok = await _mutate(
      (int v, String? t) => data.orders.voidItem(
        _uuid!,
        item.id,
        version: v,
        reason: reason,
        restock: restock,
        approvalToken: t,
      ),
      approve: approve,
      success: "Taom o'chirildi",
    );
    if (ok && item.wasSent) {
      final PosOrderItem? voided = _order?.item(item.id);
      if (voided != null) unawaited(tickets.printVoid(_order!, voided));
    }
    return ok;
  }

  Future<bool> markServed(
    PosOrderItem item, {
    required ApprovalRunner approve,
  }) => _mutate(
    (int v, String? t) =>
        data.orders.markServed(_uuid!, item.id, version: v, approvalToken: t),
    approve: approve,
    reapply: true,
    success: 'Berildi',
  );

  Future<bool> setGuests(int count, {required ApprovalRunner approve}) =>
      _mutate(
        (int v, String? t) => data.orders.setGuests(
          _uuid!,
          version: v,
          guestsCount: count,
          approvalToken: t,
        ),
        approve: approve,
        reapply: true,
        success: 'Mehmonlar soni: $count',
      );

  Future<bool> move(FloorTable table, {required ApprovalRunner approve}) =>
      _mutate(
        (int v, String? t) => data.orders.move(
          _uuid!,
          version: v,
          tableId: table.id,
          approvalToken: t,
        ),
        approve: approve,
        success: "Buyurtma ko'chirildi: ${table.name}",
      );

  Future<bool> merge(FloorOrder source, {required ApprovalRunner approve}) =>
      _mutate(
        (int v, String? t) => data.orders.merge(
          _uuid!,
          version: v,
          sourceUuid: source.uuid,
          sourceVersion: source.version,
          approvalToken: t,
        ),
        approve: approve,
        success: 'Buyurtmalar birlashtirildi',
      );

  Future<bool> waiveCharge(
    PosChargeKind kind,
    String reason, {
    required ApprovalRunner approve,
  }) => _mutate(
    (int v, String? t) => data.orders.waiveCharge(
      _uuid!,
      kind,
      version: v,
      reason: reason,
      approvalToken: t,
    ),
    approve: approve,
    success: '${kind.label} olib tashlandi',
  );

  Future<bool> restoreCharge(
    PosChargeKind kind, {
    required ApprovalRunner approve,
  }) => _mutate(
    (int v, String? t) =>
        data.orders.restoreCharge(_uuid!, kind, version: v, approvalToken: t),
    approve: approve,
    success: '${kind.label} qaytarildi',
  );

  Future<bool> cancel(String reason, {required ApprovalRunner approve}) =>
      _mutate(
        (int v, String? t) => data.orders.cancel(
          _uuid!,
          version: v,
          reason: reason,
          approvalToken: t,
        ),
        approve: approve,
        success: 'Buyurtma bekor qilindi',
      );

  /// Bitta amal: `version` bilan, tasdiq so'ralsa [approve], versiya to'qnashuvida qayta yuklash (va [reapply] bo'lsa
  /// yangi versiya bilan takrorlash). Oshxonaga yangi ketgan qatorlar markasi chiqadi.
  Future<bool> _mutate(
    Future<PosOrder> Function(int version, String? approvalToken) call, {
    required ApprovalRunner approve,
    bool reapply = false,
    String? success,
  }) async {
    final PosOrder? before = _order;
    final String? id = _uuid;
    if (_busy || before == null || id == null) return false;
    _busy = true;
    _notify();
    try {
      PosOrder next;
      try {
        next = await approve<PosOrder>((String? t) => call(_order!.version, t));
      } on PosVersionConflict {
        final PosOrder fresh = await data.orders.get(id);
        _order = fresh;
        _notify();
        if (!reapply || !fresh.isOpen) {
          _emit(
            "Buyurtma boshqa qurilmada o'zgartirildi va yangilandi. Tekshirib, amalni qayta bajaring.",
            error: true,
          );
          return false;
        }
        next = await approve<PosOrder>((String? t) => call(fresh.version, t));
      }
      _order = next;
      unawaited(tickets.printSent(before, next));
      if (success != null) _emit(success);
      return true;
    } on PosApprovalCancelled {
      return false;
    } on PosException catch (e) {
      _emit(posErrorText(e), error: true);
      if (e is PosRejected && e.reason == 'order_closed' ||
          e is PosVersionConflict) {
        unawaited(_reloadQuietly());
      }
      return false;
    } finally {
      _busy = false;
      _notify();
    }
  }

  Future<void> _reloadQuietly() async {
    final String? id = _uuid;
    if (id == null) return;
    try {
      _order = await data.orders.get(id);
      _notify();
    } on PosException {
      // keyingi yangilashda
    }
  }

  /// Bir xil amal (bir xil tana) uchun bir xil kalit: tarmoq xatosidan keyin qayta bosilsa server takrorlamaydi.
  String _keyFor(String what) {
    if (_roundKeyFor != what || _roundKey == null) {
      _roundKeyFor = what;
      _roundKey = _randomId();
    }
    return _roundKey!;
  }

  String _randomId() {
    final StringBuffer b = StringBuffer();
    for (var i = 0; i < 32; i++) {
      b.write(_random.nextInt(16).toRadixString(16));
      if (i == 7 || i == 11 || i == 15 || i == 19) b.write('-');
    }
    return b.toString();
  }

  void _emit(String message, {bool error = false}) {
    if (!_notices.isClosed) _notices.add(OrderNotice(message, error: error));
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    unawaited(_catalogSub?.cancel());
    unawaited(_notices.close());
    super.dispose();
  }
}
