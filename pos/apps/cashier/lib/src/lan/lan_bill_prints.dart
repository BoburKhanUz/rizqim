import 'dart:async';

import 'package:pos_data/pos_data.dart';
import 'package:pos_lan/pos_lan.dart';

import '../printing/bill_print_service.dart';

/// P6 B bosqichi: internetsiz paytda ofitsiant LAN orqali "Schyot so'rash"ni bossa, markaz kassa pre-check'ni o'zi
/// chiqaradi (onlayn buni server `bill.print_requested` bilan qiladi).
///
/// * **Qachon:** [decide] — kassa internetsiz, filialda "schyotni avtomatik chop etish" yoqilgan va kassada schyot
///   chiqadigan printer bor. Shunda amal navbatga `printed_on_lan: true` bilan yoziladi ([PosOfflineLanApplier]) va
///   server navbat yetganda uni **qayta chiqarmaydi** (bir so'rov — bitta qog'oz). Internet bor paytda markaz chiqarmaydi:
///   amal darhol serverga ketadi va server odatdagidek chiqartiradi.
/// * **Nima chiqadi:** buyurtmaning hali to'lanmagan hammasi bitta schyot ko'rinishida (pre-check, "Bu fiskal chek
///   emas"); navbatga schyot yozilmaydi — kassir mehmon to'laganda schyotni odatdagidek tuzadi. Buyurtmada to'lov
///   boshlangan, schyot bo'lingan, og'irligi kiritilmagan taom bor yoki schyotga tushadigan narsa qolmagan bo'lsa —
///   chiqmaydi (server ham shunday: kassir qo'lda chiqaradi).
/// * **Bir marta:** so'rov id'si — amalning `op_id` si ([BillPrintService] uni eslab qoladi); faqat shu ishga
///   tushishdan keyin navbatga tushgan amallar ko'riladi.
class LanBillPrints {
  LanBillPrints({
    required this.offline,
    required this.prints,
    required this.now,
    required this.autoPrint,
    this.printerId,
  });

  final PosOffline offline;
  final BillPrintService prints;
  final DateTime Function() now;

  /// Filial sozlamasi `auto_print_bill` (bootstrap).
  final bool Function() autoPrint;

  /// Filial sozlamasi `bill_printer` (`null` — kassaning chek printeri).
  final int? Function()? printerId;

  StreamSubscription<void>? _sub;
  int _lastSeq = 0;
  final List<Future<BillPrintOutcome>> _running = <Future<BillPrintOutcome>>[];

  /// [PosOfflineLanApplier.printsBill]: shu so'rovni markaz o'zi chiqaradimi.
  bool decide(LanRemoteOp op) {
    if (offline.connectivity.state.online) return false;
    if (!autoPrint()) return false;
    return prints.canPrint(printerId?.call());
  }

  void start() {
    if (_sub != null) return;
    _lastSeq = offline.store.lastSeq;
    _sub = offline.store.changes.listen((_) => _scan());
  }

  /// Boshlangan chiqarishlar tugaguncha kutadi (testlar).
  Future<void> idle() async {
    while (_running.isNotEmpty) {
      await _running.removeAt(0);
    }
  }

  void _scan() {
    final List<SyncOperation> fresh = offline.store.operationsAfter(_lastSeq);
    if (fresh.isEmpty) return;
    _lastSeq = fresh.last.seq;
    for (final SyncOperation op in fresh) {
      if (op.type != SyncOpType.billRequest ||
          op.payload['printed_on_lan'] != true) {
        continue;
      }
      final String? ref = op.orderRef;
      if (ref == null) continue;
      _running.add(_print(op, ref));
    }
  }

  Future<BillPrintOutcome> _print(SyncOperation op, String ref) async {
    final (PosBilling, PosOrder, String)? view = preview(ref, op.opId);
    if (view == null) return BillPrintOutcome.stale;
    final (PosBilling billing, PosOrder order, String bill) = view;
    try {
      return await prints.handle(
        BillPrintRequest(
          billUuid: bill,
          orderUuid: ref,
          requestId: op.opId,
          printerId: printerId?.call(),
          requestedBy: op.staffId,
        ),
        localBilling: billing,
        localOrder: order,
      );
    } on Object {
      // Never the till's work: the cashier prints it by hand
      return BillPrintOutcome.unavailable;
    }
  }

  /// Buyurtmaning hali schyotga tushmagan hammasi bitta schyot bo'lib (navbatga yozilmaydi): `(schyotlar, buyurtma,
  /// shu schyotning uuid'si)`; chiqarib bo'lmasa `null`.
  (PosBilling, PosOrder, String)? preview(String ref, String opId) {
    final OfflineOrderState? state = offline.book.state(ref);
    if (state == null || !state.isOpen) return null;
    final PosOrder order = PosOrder.fromJson(PosJson(state.order));
    if (order.items.any((PosOrderItem i) => i.awaitingWeight)) return null;
    final PosBilling before = PosBilling.fromJson(PosJson(state.billingJson()));
    final List<PosBill> active = <PosBill>[
      for (final PosBill b in before.bills)
        if (b.status != 'void') b,
    ];
    // Being paid or split: the till works on those
    if (active.any((PosBill b) => b.isTaken) || active.length > 1) return null;
    if (!state.leftToBill.isPositive) return null;
    final String bill = 'lan-$opId';
    state.createBill(
      SyncOperation(
        opId: bill,
        seq: 0,
        type: SyncOpType.billCreate,
        occurredAt: now(),
        staffId: 0,
        payload: <String, Object?>{'order': ref, 'bill': bill},
      ),
    );
    return (PosBilling.fromJson(PosJson(state.billingJson())), order, bill);
  }

  Future<void> dispose() async {
    await _sub?.cancel();
    _sub = null;
  }
}
