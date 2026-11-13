import 'dart:async';

import 'package:pos_data/pos_data.dart';

import 'messages.dart';

/// Prints one pre-check: the request (its id is the LAN operation's `op_id`), the bill to print and the order. `true`
/// when it went to the printer (queued); `false` when it did not (no printer, an error: the cashier prints by hand).
typedef LanBillPrinter = Future<bool> Function(
  BillPrintRequest request,
  PosBilling billing,
  PosOrder order,
);

/// The hub's automatic bill print for a waiter's "Schyot so'rash" that came over the LAN (P6 stage B; review P6
/// hardware/LAN C6 and C9). The cashier app wires it to its BillPrintService, the e2e drill to its emulated printer:
/// both run this logic.
///
/// * **Decide and print in one step** ([decide], [PosOfflineLanApplier.printsBill]): with the till offline, the
///   branch's `auto_print_bill` on and a printer at hand, the pre-check is printed *before* the request is queued, and
///   the request goes into the queue with `printed_on_lan` = whether it really went to the printer. The printed request
///   id is recorded on disk (the till's [SyncStore], [docKey]), so a waiter's retry after a restart of the till is not
///   printed twice ([printed]).
/// * **The start of an outage:** the till still thinks it is online (the request goes to the server, which prints it),
///   but if the request is still in the queue after [takeOverAfter] the server is not answering: the hub prints it
///   itself and records it. The server keeps the request's key as its print request id (`idempotency_key`, the op id),
///   so when the queue reaches it the till sees [printed] and does not print it again.
/// * **What prints:** the order's whole unbilled amount as one bill (a preview that is never queued: the cashier makes
///   the real bill when the guest pays). Not when payments started, the bill is split, a weight is pending, nothing is
///   left to bill, the order is closed or the bill was asked for already (the server does the same: the cashier prints
///   by hand).
class LanBillPrints {
  LanBillPrints({
    required this.offline,
    required this.print,
    required this.canPrint,
    required this.autoPrint,
    this.printerId,
    DateTime Function()? now,
    this.takeOverAfter = const Duration(seconds: 8),
    this.capacity = 200,
  }) : now = now ?? DateTime.now;

  final PosOffline offline;
  final LanBillPrinter print;

  /// Whether this till can print on [printerId] (or its receipt printer).
  final bool Function(int? printerId) canPrint;

  /// The branch's `auto_print_bill` (bootstrap).
  final bool Function() autoPrint;

  /// The branch's `bill_printer` (`null`: the till's receipt printer).
  final int? Function()? printerId;
  final DateTime Function() now;

  /// How long a request may sit unanswered in the queue (the till online) before the hub prints it itself.
  final Duration takeOverAfter;

  /// How many printed request ids are kept on disk.
  final int capacity;

  /// The till's queue doc with the request ids printed over the LAN.
  static const String docKey = 'lan.bill_prints';

  final Map<String, Timer> _watch = <String, Timer>{};
  final List<Future<bool>> _running = <Future<bool>>[];
  bool _disposed = false;

  /// This request id ([BillPrintRequest.requestId], the LAN op id) was printed on this till over the LAN.
  bool printed(String requestId) => _printedIds().contains(requestId);

  /// [PosOfflineLanApplier.printsBill]: prints the pre-check now when the hub is the one to print it; `true` only when
  /// it went to the printer (the request is then queued with `printed_on_lan: true`).
  Future<bool> decide(LanRemoteOp op) async {
    final String? ref = op.order;
    if (ref == null || _disposed) return false;
    // The waiter sent it again after the till restarted: on paper already
    if (printed(op.opId)) return true;
    if (!autoPrint() || !canPrint(printerId?.call())) return false;
    final OfflineOrderState? state = offline.book.state(ref);
    if (state == null || !state.isOpen) return false;
    // Asked already: the request changes nothing and prints nothing (as the server)
    if (PosOrder.fromJson(PosJson(state.order)).billRequestedAt != null) {
      return false;
    }
    if (offline.connectivity.state.online) {
      _watchQueue(op.opId, ref, op.staffId);
      return false;
    }
    return _track(_printNow(op.opId, ref, op.staffId));
  }

  /// Started prints are finished (tests).
  Future<void> idle() async {
    while (_running.isNotEmpty) {
      await _running.removeAt(0);
    }
  }

  void _watchQueue(String opId, String ref, int? staffId) {
    _watch.remove(opId)?.cancel();
    _watch[opId] = Timer(takeOverAfter, () {
      _watch.remove(opId);
      if (_disposed || printed(opId)) return;
      final bool waiting = offline.store.pending.any(
        (SyncOperation o) => o.opId == opId,
      );
      if (!waiting || !autoPrint() || !canPrint(printerId?.call())) return;
      _track(_printNow(opId, ref, staffId));
    });
  }

  Future<bool> _track(Future<bool> work) {
    _running.add(work);
    return work;
  }

  Future<bool> _printNow(String opId, String ref, int? staffId) async {
    final (PosBilling, PosOrder, String)? view = preview(ref, opId);
    if (view == null) return false;
    final (PosBilling billing, PosOrder order, String bill) = view;
    final bool queued;
    try {
      queued = await print(
        BillPrintRequest(
          billUuid: bill,
          orderUuid: ref,
          requestId: opId,
          printerId: printerId?.call(),
          requestedBy: staffId,
        ),
        billing,
        order,
      );
    } on Object {
      // Never the till's work: the cashier prints it by hand
      return false;
    }
    if (queued) await _record(opId);
    return queued;
  }

  /// The order's not yet billed part as one bill (never queued): `(bills, order, that bill's uuid)`; `null` when it
  /// cannot be printed.
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

  List<String> _printedIds() {
    final Object? value = offline.store.doc(docKey)?.value;
    return <String>[
      if (value is List)
        for (final Object? e in value)
          if (e is String) e,
    ];
  }

  Future<void> _record(String opId) async {
    final List<String> ids = _printedIds()..add(opId);
    while (ids.length > capacity) {
      ids.removeAt(0);
    }
    try {
      await offline.store.putDoc(docKey, ids);
    } on Object {
      // The disk refused: the paper is out already; only a restart's retry could print it again
    }
  }

  void dispose() {
    _disposed = true;
    for (final Timer t in _watch.values) {
      t.cancel();
    }
    _watch.clear();
  }
}
