import 'dart:async';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';

import '../features/order/frequent_items.dart';
import '../features/order/kitchen_tickets.dart';
import '../features/order/order_controller.dart'
    show ApprovalRunner, OrderNotice;
import '../features/order/order_draft.dart';
import '../features/order/order_format.dart';
import 'counter_format.dart';
import 'counter_queue.dart';

/// Tez kassaning bosqichi.
enum CounterStage {
  /// Mijozning savati yig'ilmoqda.
  cart,

  /// Buyurtma ochildi (taomlar oshxonaga ketdi), to'lov kutilmoqda: to'lovni davom ettirish yoki bekor qilish.
  awaitingPayment,

  /// To'landi: talon va "Keyingi mijoz".
  done,
}

/// To'langan sotuv (talon va ekrandagi chek uchun).
class CounterReceipt {
  const CounterReceipt({
    required this.order,
    required this.bill,
    required this.lines,
    required this.paidAt,
    this.number,
    this.ticket,
  });

  final PosOrder order;
  final PosBill bill;

  /// Talondagi qisqa ro'yxat ("2 x Osh").
  final List<String> lines;
  final DateTime paidAt;

  /// Navbat raqami: serverning talonidagi, bo'lmasa kassa olgani.
  final int? number;
  final PosQueueTicket? ticket;

  PosMoney get total => bill.total;
}

/// Tez kassa holati (P6-12, A1, A4): menyudan savat (bosish = +1, tez miqdor ×2…×10), "To'lash" — navbat raqami
/// olinadi, buyurtma taomlar bilan **darhol oshxonaga** yuborib ochiladi ("avval to'lov, keyin oshxona": to'langan
/// buyurtma yopiladi, lekin raqami KDS'da qoladi), bitta schyot tuziladi va to'lov sahifasi ochiladi; to'langach talon
/// va "Keyingi mijoz" (bir bosish — yangi savat).
///
/// Ochish so'rovi bitta `Idempotency-Key` va `external_id` bilan: aloqa uzilib qayta bosilsa server ikkinchi buyurtma
/// ochmaydi; aloqa yo'q bo'lsa (P3-31) buyurtma internetsiz navbat orqali ochiladi, raqam o'sha blokdan.
class CounterController extends ChangeNotifier {
  CounterController({
    required this.data,
    required this.tickets,
    required this.frequent,
    required this.queue,
    required this.now,
    Random? random,
  }) : _random = random ?? Random();

  final PosData data;
  final KitchenTickets tickets;
  final FrequentItems frequent;
  final CounterQueueNumbers queue;
  final DateTime Function() now;
  final Random _random;

  PosChannel _channel = PosChannel.takeaway;
  PosCatalog? _catalog;
  PosException? _loadError;
  bool _loading = false;
  bool _busy = false;
  bool _disposed = false;
  int _multiplier = 1;
  final List<DraftLine> _lines = <DraftLine>[];
  var _keySeq = 0;
  int _revision = 0;

  // The sale being paid: one key and external id for the whole checkout, so a retry never opens a second order
  String? _externalId;
  int? _number;
  bool _numberTaken = false;
  PosOrder? _order;
  PosBill? _bill;
  CounterReceipt? _receipt;
  StreamSubscription<PosCatalog>? _catalogSub;
  final StreamController<OrderNotice> _notices =
      StreamController<OrderNotice>.broadcast();

  PosChannel get channel => _channel;
  PosCatalog? get catalog => _catalog;
  PosException? get loadError => _loadError;
  bool get loading => _loading;
  bool get busy => _busy;
  int get multiplier => _multiplier;
  List<DraftLine> get lines => List<DraftLine>.unmodifiable(_lines);
  bool get isEmpty => _lines.isEmpty;
  Stream<OrderNotice> get notices => _notices.stream;

  /// Ochilgan, hali to'lanmagan buyurtma.
  PosOrder? get order => _order;
  PosBill? get bill => _bill;
  CounterReceipt? get receipt => _receipt;

  /// Shu mijozga olingan navbat raqami (ochilgandan keyin — serverniki). `queue_numbers` moduli yo'q filialda —
  /// `null` (talon yo'q; ichki kunlik raqam ekranda ko'rsatilmaydi).
  int? get number =>
      data.session.queueNumbers ? (_order?.queue?.number ?? _number) : null;

  CounterStage get stage => _receipt != null
      ? CounterStage.done
      : (_order != null ? CounterStage.awaitingPayment : CounterStage.cart);

  /// Savatni o'zgartirish mumkin (buyurtma hali ochilmagan).
  bool get editable => _order == null && _receipt == null && !_busy;

  /// Savatdagi taomlar soni (dona bo'yicha; kg qator — bitta).
  int get count => _lines.fold(
    0,
    (int s, DraftLine l) =>
        s + (l.variant.unit.isMeasured ? 1 : l.quantity.wholePart),
  );

  /// Taxminiy jami (aniq narxni server hisoblaydi).
  PosMoney get estimate =>
      PosMoney.sum(_lines.map((DraftLine l) => l.estimate));

  /// To'lanadigan summa: schyot bo'lsa — schyotniki.
  PosMoney get total => _bill?.dueAmount ?? _order?.totalPrice ?? estimate;

  // --- menyu ----------------------------------------------------------------------------------------------------------

  Future<void> load() async {
    _loading = true;
    _loadError = null;
    _notify();
    _catalogSub ??= data.catalog.changes.listen((PosCatalog c) {
      if (c.channel == _channel) {
        _catalog = c;
        _notify();
      }
    });
    try {
      await _loadCatalog();
    } on PosException catch (e) {
      _loadError = e;
    } finally {
      _loading = false;
      _notify();
    }
  }

  Future<void> _loadCatalog() async {
    _catalog =
        data.catalog.cached(_channel) ??
        await data.catalog.load(
          _channel,
          menuVersion: data.session.bootstrap?.menuVersion,
        );
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

  /// Olib ketish yoki zalda (stolsiz): narxlar kanal bo'yicha, savat yangi narxlar bilan qoladi (yo'q taom tushadi).
  Future<void> setChannel(PosChannel next) async {
    if (next == _channel) return;
    // On the paid screen the choice is the next customer's
    if (_receipt != null) nextCustomer();
    if (!editable) return;
    _channel = next;
    _resetKeys();
    try {
      await _loadCatalog();
    } on PosException catch (e) {
      _emit(posErrorText(e), error: true);
    }
    final PosCatalog? cat = _catalog;
    if (cat != null) {
      final List<DraftLine> kept = <DraftLine>[];
      for (final DraftLine l in _lines) {
        final PosProduct? p = cat.product(l.product.id);
        final PosVariant? v = p?.variants
            .where((PosVariant x) => x.uuid == l.variant.uuid)
            .firstOrNull;
        if (p == null || v == null || !cat.isVariantAvailable(v)) continue;
        kept.add(
          DraftLine(
            key: l.key,
            product: p,
            variant: v,
            quantity: l.quantity,
            modifiers: l.modifiers,
          ),
        );
      }
      if (kept.length != _lines.length) {
        _emit("Bu kanal menyusida yo'q taomlar savatdan olindi.", error: true);
      }
      _lines
        ..clear()
        ..addAll(kept);
    }
    _touch();
  }

  /// Keyingi bosishdagi miqdor (×1…×10); bir marta qo'llanadi va ×1 ga qaytadi.
  void setMultiplier(int n) {
    _multiplier = n.clamp(1, 99);
    _notify();
  }

  // --- savat ----------------------------------------------------------------------------------------------------------

  /// Taom qo'shadi: bosish = +1 (tez miqdor tanlangan bo'lsa — shuncha); bir xili bo'lsa miqdori oshadi. To'langan
  /// sotuvdan keyin birinchi bosish yangi mijozni boshlaydi.
  void add(
    PosProduct product,
    PosVariant variant, {
    PosQuantity? quantity,
    Map<String, int> modifiers = const <String, int>{},
  }) {
    // ×N chosen on the paid screen belongs to the next customer's first dish
    final int times = _multiplier;
    if (_receipt != null) nextCustomer();
    if (!editable) return;
    final PosQuantity base = quantity ?? variant.minimum;
    final PosQuantity q = variant.unit.isMeasured || times == 1
        ? base
        : PosQuantity.milli(base.milli * times);
    _multiplier = 1;
    for (final DraftLine l in _lines) {
      if (l.canMergeWith(variant, modifiers)) {
        l.quantity = l.quantity + q;
        _touch();
        return;
      }
    }
    _lines.add(
      DraftLine(
        key: ++_keySeq,
        product: product,
        variant: variant,
        quantity: q,
        modifiers: Map<String, int>.of(modifiers),
      ),
    );
    _touch();
  }

  /// Qator miqdori: + / − (donali taomda bittadan, kg da qadam bilan).
  void step(int key, int delta) {
    final DraftLine? l = _line(key);
    if (l == null || !editable) return;
    final PosQuantity unit = l.variant.unit.isMeasured
        ? l.variant.minimum
        : PosQuantity.one;
    final PosQuantity next = PosQuantity.milli(
      l.quantity.milli + unit.milli * delta,
    );
    if (!next.isPositive) {
      remove(key);
      return;
    }
    l.quantity = next;
    _touch();
  }

  void setQuantity(int key, PosQuantity q) {
    final DraftLine? l = _line(key);
    if (l == null || !editable) return;
    if (!q.isPositive) return remove(key);
    l.quantity = q;
    _touch();
  }

  void remove(int key) {
    if (!editable) return;
    _lines.removeWhere((DraftLine l) => l.key == key);
    _touch();
  }

  void clear() {
    if (!editable) return;
    _lines.clear();
    _multiplier = 1;
    _touch();
  }

  DraftLine? _line(int key) {
    for (final DraftLine l in _lines) {
      if (l.key == key) return l;
    }
    return null;
  }

  // --- to'lov ---------------------------------------------------------------------------------------------------------

  /// "To'lash": raqam olinadi, buyurtma ochilib oshxonaga yuboriladi (birinchi marta), bitta schyot tuziladi. Qaytaradi:
  /// to'lanadigan schyot yoki `null` (xato — [notices] da).
  Future<PosBill?> checkout({required ApprovalRunner approve}) async {
    if (_busy || _receipt != null) return null;
    if (_order == null && _lines.isEmpty) return null;
    _busy = true;
    _notify();
    try {
      final PosOrder? order = _order ?? await _open(approve);
      if (order == null) return null;
      final PosBill? open = _bill;
      if (open != null && !open.isPaid) return open;
      final PosBilling billing = await approve<PosBilling>(
        (String? token) => data.billing.createBills(
          order.uuid,
          version: order.version,
          approvalToken: token,
          idempotencyKey: token == null ? _key('bill:${order.uuid}') : null,
        ),
      );
      final PosBill? bill = billing.bills
          .where((PosBill b) => !b.isPaid && b.status != 'void')
          .firstOrNull;
      _bill = bill;
      if (bill == null) {
        _emit(
          "Schyot tuzilmadi: buyurtmada to'lanadigan taom yo'q.",
          error: true,
        );
      }
      return bill;
    } on PosApprovalCancelled {
      return null;
    } on PosVersionConflict {
      await _reloadOrder();
      _emit(
        "Buyurtma boshqa qurilmada o'zgardi: yangilandi. «To'lash» ni qayta bosing.",
        error: true,
      );
      return null;
    } on PosException catch (e) {
      _emit(posErrorText(e), error: true);
      return null;
    } finally {
      _busy = false;
      _notify();
    }
  }

  Future<PosOrder?> _open(ApprovalRunner approve) async {
    if (!_numberTaken) {
      _number = await queue.take();
      _numberTaken = true;
    }
    _externalId ??= _uuid();
    final List<PosLineInput> items = <PosLineInput>[
      for (final DraftLine l in _lines) l.toInput(),
    ];
    final PosOrder created = await approve<PosOrder>(
      (String? token) => data.orders.open(
        channel: _channel,
        guestsCount: _channel == PosChannel.hall ? 1 : null,
        externalId: _externalId,
        items: items,
        send: true,
        queueNumber: _number,
        approvalToken: token,
        idempotencyKey: token == null ? _key('open:$_revision') : null,
      ),
    );
    _order = created;
    await queue.note(created.queue?.number);
    unawaited(tickets.printSent(null, created));
    for (final DraftLine l in _lines) {
      frequent.record(l.product.id);
    }
    return created;
  }

  /// To'lov sahifasi qaytdi: schyot to'landimi. To'langan bo'lsa sotuv yakunlanadi ([receipt]).
  void paymentFinished(PosBill? paid, {required bool orderClosed}) {
    final PosOrder? o = _order;
    if (o == null) return;
    if (paid != null) _bill = paid;
    final PosBill? b = _bill;
    if (b == null || !(b.isPaid || orderClosed)) {
      _notify();
      return;
    }
    final PosQueueTicket? ticket = b.ticket;
    _receipt = CounterReceipt(
      order: o,
      bill: b,
      lines: <String>[
        for (final DraftLine l in _lines)
          '${l.quantity.label(l.variant.unit)} x ${l.name}',
      ],
      paidAt: now(),
      number: data.session.queueNumbers ? (ticket?.number ?? number) : null,
      ticket: ticket,
    );
    _notify();
  }

  /// To'lanmagan buyurtma serverdan (to'lov sahifasi to'lovsiz yopildi).
  Future<void> refreshSale() async {
    final PosOrder? o = _order;
    if (o == null) return;
    try {
      final PosBilling billing = await data.billing.bills(o.uuid);
      _bill = billing.bills.where((PosBill b) => b.status != 'void').lastOrNull;
      await _reloadOrder();
      // Paid while the payment page waited for the receipt and was left: the sale is done
      if ((_bill?.isPaid ?? false) || !(_order?.isOpen ?? true)) {
        paymentFinished(_bill, orderClosed: !(_order?.isOpen ?? true));
        return;
      }
    } on PosException {
      // Keyingi bosishda
    }
    _notify();
  }

  Future<void> _reloadOrder() async {
    final PosOrder? o = _order;
    if (o == null) return;
    try {
      _order = await data.orders.get(o.uuid);
    } on PosException {
      // eski holat qoladi
    }
  }

  /// Mijoz voz kechdi: ochilgan (to'lanmagan) buyurtma bekor qilinadi (oshxonaga «O'CHIRILDI» markasi).
  Future<bool> cancelSale(
    String reason, {
    required ApprovalRunner approve,
  }) async {
    final PosOrder? before = _order;
    if (before == null || _busy) return false;
    _busy = true;
    _notify();
    try {
      final PosOrder after = await approve<PosOrder>(
        (String? token) => data.orders.cancel(
          before.uuid,
          version: before.version,
          reason: reason,
          approvalToken: token,
        ),
      );
      for (final PosOrderItem i in before.items) {
        final PosOrderItem? now = after.item(i.id);
        if (i.wasSent && !i.isVoid && now != null) {
          unawaited(tickets.printVoid(after, now));
        }
      }
      _busy = false;
      _reset();
      _emit('Buyurtma bekor qilindi.');
      return true;
    } on PosApprovalCancelled {
      return false;
    } on PosVersionConflict {
      await _reloadOrder();
      _emit("Buyurtma o'zgardi: qayta urinib ko'ring.", error: true);
      return false;
    } on PosException catch (e) {
      _emit(posErrorText(e), error: true);
      return false;
    } finally {
      _busy = false;
      _notify();
    }
  }

  /// "Keyingi mijoz": bo'sh savat (bir bosish).
  void nextCustomer() {
    if (_order != null && _receipt == null) return;
    _reset();
  }

  void _reset() {
    _lines.clear();
    _multiplier = 1;
    _order = null;
    _bill = null;
    _receipt = null;
    _number = null;
    _numberTaken = false;
    _externalId = null;
    _resetKeys();
    _touch();
  }

  // --- yordamchilar ---------------------------------------------------------------------------------------------------

  final Map<String, String> _keys = <String, String>{};

  /// Bir xil amal (bir xil savat) uchun bir xil kalit: aloqa uzilib qayta bosilsa server takrorlamaydi.
  String _key(String what) => _keys[what] ??= _uuid();

  void _resetKeys() => _keys.clear();

  void _touch() {
    if (_order == null) {
      // A changed cart is a new request (its own key)
      _revision++;
      _keys.removeWhere((String k, _) => k.startsWith('open:'));
    }
    _notify();
  }

  String _uuid() {
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
