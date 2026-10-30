import 'dart:async';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';

import '../../printing/label_printing.dart';
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
    this.labels,
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

  /// P6-32: oshxonaga ketgan qatorlarning stakan stikerlari (`labels` sozlamasi va stiker printeri bo'lsa).
  final CashierLabels? labels;
  final Random _random;

  /// P6-34: qadoq qatorlari (faqat aloqa borida).
  late final PosTakeawayRepository takeaway = PosTakeawayRepository(data.http);

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

  // A round whose answer was lost (network): the version it carried, so the retry is the very same request
  String? _lostRoundFor;
  int? _lostRoundVersion;
  // P3-37: a discount whose answer was lost (network): the retry is the very same request (key, version, body, token)
  String? _lostDiscountFor;
  int? _lostDiscountVersion;
  String? _lostDiscountKey;
  String? _lostDiscountToken;
  String? _externalId;
  int _draftGuests = 1;
  StreamSubscription<PosCatalog>? _catalogSub;
  StreamSubscription<List<SyncOperation>>? _syncSub;
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
    _syncSub ??= data.offline?.synced.listen(onSynced);
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
    ComboChoice? combo,
  }) {
    final bool plain =
        (note == null || note.trim().isEmpty) &&
        seatNo == null &&
        fireMode == null;
    if (plain) {
      for (final DraftLine l in _draft) {
        if (l.canMergeWith(variant, modifiers, combo: combo)) {
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
        combo: combo,
      ),
    );
    _touchDraft();
  }

  /// P6-32: qatorning tez modifikatorlari (hajm, "Muzsiz").
  void setDraftModifiers(int key, Map<String, int> modifiers) {
    final DraftLine? l = _line(key);
    if (l == null) return;
    l.modifiers = Map<String, int>.of(modifiers);
    _touchDraft();
  }

  /// P6-31: kombo qatori qayta tanlandi.
  void updateCombo(int key, ComboChoice combo, PosQuantity quantity) {
    final DraftLine? l = _line(key);
    if (l == null) return;
    l
      ..combo = combo
      ..quantity = quantity.isPositive ? quantity : l.quantity;
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
      final String what = 'round:$revision:$send';
      ok = await _mutate(
        (int v, String? t) async {
          // After a lost answer the same draft goes again exactly as it went (key, version, body): the server
          // answers it once, whether the first one reached it or not. A poll may have moved the version since.
          final int version = t == null && _lostRoundFor == what
              ? _lostRoundVersion!
              : v;
          try {
            final PosOrder next = await data.orders.addRound(
              _uuid!,
              version: version,
              items: items,
              fireMode: mode,
              send: send,
              approvalToken: t,
              idempotencyKey: t == null ? _keyFor(what) : null,
            );
            _lostRoundFor = null;
            return next;
          } on PosNetworkException {
            if (t == null) {
              _lostRoundFor = what;
              _lostRoundVersion = version;
            }
            rethrow;
          } on PosException {
            // The server answered: the next try is a new request
            _lostRoundFor = null;
            _roundKey = null;
            rethrow;
          }
        },
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
      labels?.printSent(null, created);
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

  /// P6-21: "N-kursni yuborish" — kursning ushlab turilgan taomlari oshxonaga (internetsiz ham, navbat orqali).
  Future<bool> sendCourse(int courseNo, {required ApprovalRunner approve}) =>
      _mutate(
        (int v, String? t) => data.orders.sendCourse(
          _uuid!,
          courseNo,
          version: v,
          approvalToken: t,
        ),
        approve: approve,
        reapply: true,
        success: '$courseNo-kurs oshxonaga yuborildi',
      );

  /// P6-25: og'irligi keyin kiritiladigan taomning haqiqiy og'irligi (narx shunga). Qayta tortish — menejer tasdig'i
  /// bilan (server so'raydi).
  Future<bool> setWeight(
    PosOrderItem item,
    PosQuantity quantity, {
    required ApprovalRunner approve,
  }) => _mutate(
    (int v, String? t) => data.orders.setWeight(
      _uuid!,
      item.id,
      version: v,
      quantity: quantity,
      approvalToken: t,
    ),
    approve: approve,
    success: "${item.displayName}: og'irlik ${quantity.label(item.unit)}",
  );

  /// P6-24: qatorga mehmon raqami ([seatNo] `null` — umumiy). Faqat internet bilan.
  Future<bool> setSeat(
    PosOrderItem item,
    int? seatNo, {
    required ApprovalRunner approve,
  }) {
    if (data.offline?.active ?? false) {
      _emit(
        "Internet yo'q: mehmon raqamini internet tiklangach yozing.",
        error: true,
      );
      return Future<bool>.value(false);
    }
    return _mutate(
      (int v, String? t) => data.hostess.setLineSeat(
        _uuid!,
        item.id,
        version: v,
        seatNo: seatNo,
        approvalToken: t,
      ),
      approve: approve,
      reapply: true,
      success: seatNo == null
          ? '${item.displayName}: umumiy'
          : '${item.displayName}: $seatNo-mehmon',
    );
  }

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

  /// P6-34: mijoz o'z paketini olib keldi — qadoq qatorini sabab bilan olib tashlash (aloqa borida; auditda).
  Future<bool> removePackaging(
    int ruleId,
    String reason, {
    required ApprovalRunner approve,
  }) => _packaging(
    (int v) => takeaway.removePackaging(
      _uuid!,
      ruleId,
      version: v,
      reason: reason,
      idempotencyKey: _actionKey('pack-remove:$ruleId:$v'),
    ),
    approve,
    'Qadoq olib tashlandi',
  );

  /// P6-34: olib tashlangan qadoqni qaytarish.
  Future<bool> restorePackaging(
    int ruleId, {
    required ApprovalRunner approve,
  }) => _packaging(
    (int v) => takeaway.restorePackaging(
      _uuid!,
      ruleId,
      version: v,
      idempotencyKey: _actionKey('pack-restore:$ruleId:$v'),
    ),
    approve,
    'Qadoq qaytarildi',
  );

  Future<bool> _packaging(
    Future<PosOrder> Function(int version) call,
    ApprovalRunner approve,
    String success,
  ) {
    if (data.offline?.active ?? false) {
      _emit(
        "Internet yo'q: qadoqni olib tashlab yoki qaytarib bo'lmaydi.",
        error: true,
      );
      return Future<bool>.value(false);
    }
    return _mutate(
      (int v, String? _) => call(v),
      approve: approve,
      success: success,
    );
  }

  /// P6-34: olib ketish vaqti (`scheduled_pickup`; [at] `null` — hozir, darhol oshxonaga).
  Future<bool> setPickupTime(DateTime? at, {required ApprovalRunner approve}) =>
      _mutate(
        (int v, String? _) => takeaway.setPickupTime(
          _uuid!,
          at,
          version: v,
          idempotencyKey: _actionKey('pickup:$v:${at?.toIso8601String()}'),
        ),
        approve: approve,
        success: at == null
            ? 'Vaqt olib tashlandi'
            : 'Olib ketish vaqti saqlandi',
      );

  /// P3-37: buyurtmaga chegirma ([value] `"0"` — olib tashlash). Kassir chegarasidan ko'pi — server menejer
  /// tasdig'ini so'raydi ([approve]). Javob kelmay qolsa (tarmoq) keyingi bosish aynan o'sha so'rovni — o'sha kalit,
  /// versiya, tana va bo'lgan bo'lsa o'sha tasdiq bilan — yuboradi: server uni bir marta bajaradi.
  Future<bool> discount(
    PosDiscountKind kind,
    String value, {
    String? reason,
    required ApprovalRunner approve,
  }) {
    final bool remove = value == '0';
    final String what = 'discount:${kind.wire}:$value:${reason ?? ''}';
    return _mutate(
      (int v, String? t) async {
        final bool retry = t == null && _lostDiscountFor == what;
        final int version = retry ? _lostDiscountVersion! : v;
        final String? token = retry ? _lostDiscountToken : t;
        final String key = retry ? _lostDiscountKey! : _randomId();
        try {
          final PosOrder next = await data.orders.discount(
            _uuid!,
            version: version,
            kind: kind,
            value: value,
            reason: reason,
            approvalToken: token,
            idempotencyKey: key,
          );
          _lostDiscountFor = null;
          return next;
        } on PosNetworkException catch (e) {
          _lostDiscountFor = what;
          _lostDiscountVersion = version;
          _lostDiscountKey = e.idempotencyKey ?? key;
          _lostDiscountToken = token;
          rethrow;
        } on PosException {
          // The server answered: the next try is a new request
          _lostDiscountFor = null;
          rethrow;
        }
      },
      approve: approve,
      success: remove ? 'Chegirma olib tashlandi' : 'Chegirma berildi',
    );
  }

  /// Chegirmani olib tashlash (`value` 0).
  Future<bool> removeDiscount({required ApprovalRunner approve}) => discount(
    _order?.discountKind ?? PosDiscountKind.percent,
    '0',
    approve: approve,
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
    PosOrder? before = _order;
    final String? id = _uuid;
    if (_busy || before == null || id == null) return false;
    _busy = true;
    _notify();
    try {
      if (before.items.any((PosOrderItem i) => i.id < 0) &&
          !(data.offline?.active ?? false)) {
        // The screen still shows the till's offline view: its lines have server ids now, compare with the server's
        try {
          before = _order = await data.orders.get(id);
        } on PosException {
          // the call below finds out
        }
      }
      PosOrder next;
      try {
        next = await approve<PosOrder>((String? t) => call(_order!.version, t));
      } on PosVersionConflict {
        final PosOrder fresh = await data.orders.get(id);
        _order = fresh;
        // Lines another device sent meanwhile are its tickets, not this one's
        before = fresh;
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
      labels?.printSent(before, next);
      if (success != null) _emit(success);
      return true;
    } on PosApprovalCancelled {
      return false;
    } on PosException catch (e) {
      _emit(posErrorText(e), error: true);
      // The screen is out of date (closed, changed elsewhere, P3-35: a bill on it is being paid): show the server's
      if (e is PosVersionConflict || e.needsOrderReload) {
        unawaited(_reloadQuietly());
      }
      return false;
    } finally {
      _busy = false;
      _notify();
    }
  }

  /// P3-35: offlayn navbat serverga yetdi. Shu buyurtmaning amali bo'lsa — buyurtma serverdan qayta (qator id'lari,
  /// narxlar); qator server narxida saqlangan bo'lsa (`offline_price_mismatch`) kassirga aytiladi: jami o'zgargan.
  @visibleForTesting
  Future<void> onSynced(List<SyncOperation> ops) async {
    final String? id = _uuid;
    final PosOffline? offline = data.offline;
    if (id == null || offline == null) return;
    if (!syncTouchesOrder(ops, id, offline.store.resolve)) return;
    final String uuid = offline.store.resolve(id);
    for (final PosSyncPriceNotice n in PosSyncPriceNotice.of(ops)) {
      if (n.orderUuid == uuid) {
        _emit(n.message, error: true);
      }
    }
    if (_busy) return;
    await _reloadQuietly();
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

  // P6-34: the packaging and pickup-time calls' keys, apart from the round's (a lost round keeps its own key)
  final Map<String, String> _actionKeys = <String, String>{};

  String _actionKey(String what) => _actionKeys[what] ??= _randomId();

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
    unawaited(_syncSub?.cancel());
    unawaited(_notices.close());
    super.dispose();
  }
}
