import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart';

import '../features/order/kitchen_tickets.dart' show KitchenPrintIssues;
import '../features/settings/cashier_prefs.dart';
import '../services.dart';

/// M5 S4-d: one order line of the summary slip. [number] is the till's local number (`null`: the order was opened
/// while online, so only its payment is queued); [paid] is `null` while no payment of it is queued.
class OfflineSummaryOrder {
  const OfflineSummaryOrder({required this.order, this.number, this.paid});

  final String order;
  final String? number;
  final PosMoney? paid;

  /// What the slip prints for the order.
  String get label =>
      number ??
      'Buyurtma ${order.substring(0, order.length < 6 ? order.length : 6)}';
}

/// What the till still holds when the internet is gone: counts, cash and card totals and the local numbers.
/// Money is [PosMoney] (integer tiyin), never a double.
class OfflineSummary {
  const OfflineSummary({
    required this.outageStart,
    required this.printedAt,
    required this.orders,
    required this.payments,
    required this.cash,
    required this.card,
    required this.other,
    required this.lines,
  });

  /// Start of the outage (`null`: unknown).
  final DateTime? outageStart;
  final DateTime printedAt;

  /// Queued `order.open` and `bill.pay` operations.
  final int orders;
  final int payments;

  /// Sums of the queued `bill.pay` tenders (the amount of the bill, not the cash handed over).
  final PosMoney cash;
  final PosMoney card;

  /// QR, voucher and every other tender.
  final PosMoney other;
  final List<OfflineSummaryOrder> lines;

  bool get isEmpty => orders == 0 && payments == 0;

  PosMoney get total => cash + card + other;
}

/// The start of the outage from the existing offline status: the last contact while offline, else the oldest queued
/// operation.
DateTime? outageStartOf(OfflineStatus status) =>
    status.offlineSince ?? status.oldestPendingAt ?? status.lastContactAt;

/// Builds the summary from the queue ([SyncStore.pending]) and the queues set aside ([PosOffline.orphans]). An
/// operation that is in both counts once (by `op_id`). Pure: no I/O, no clock.
OfflineSummary buildOfflineSummary({
  required Iterable<SyncOperation> pending,
  Iterable<PosOrphanQueue> orphans = const <PosOrphanQueue>[],
  required DateTime? outageStart,
  required DateTime printedAt,
}) {
  final List<SyncOperation> ops = <SyncOperation>[];
  final Set<String> seen = <String>{};
  void add(Iterable<SyncOperation> list) {
    for (final SyncOperation o in list) {
      if (seen.add(o.opId)) ops.add(o);
    }
  }

  final List<PosOrphanQueue> older = orphans.toList()
    ..sort(
      (PosOrphanQueue a, PosOrphanQueue b) =>
          a.setAsideAt.compareTo(b.setAsideAt),
    );
  for (final PosOrphanQueue q in older) {
    add(q.operations);
  }
  add(pending);

  var orders = 0;
  var payments = 0;
  var cash = PosMoney.zero;
  var card = PosMoney.zero;
  var other = PosMoney.zero;
  // Order uuid → line, in the order of first appearance
  final Map<String, ({String? number, PosMoney? paid})> byOrder =
      <String, ({String? number, PosMoney? paid})>{};

  for (final SyncOperation o in ops) {
    final String? order = o.orderRef;
    if (order == null) continue;
    if (o.type == SyncOpType.orderOpen) {
      orders++;
      final ({String? number, PosMoney? paid})? line = byOrder[order];
      byOrder[order] = (
        number: o.payload['local_number'] as String? ?? line?.number,
        paid: line?.paid,
      );
    } else if (o.type == SyncOpType.billPay) {
      payments++;
      var paid = PosMoney.zero;
      final Object? tenders = o.payload['tenders'];
      for (final Object? t
          in tenders is List<Object?> ? tenders : const <Object?>[]) {
        if (t is! Map) continue;
        final PosMoney? amount = PosMoney.tryParse(t['amount']);
        if (amount == null) continue;
        paid += amount;
        switch (PosTender.fromWire(t['tender'] as String?)) {
          case PosTender.cash:
            cash += amount;
          case PosTender.card:
            card += amount;
          case _:
            other += amount;
        }
      }
      final ({String? number, PosMoney? paid})? line = byOrder[order];
      byOrder[order] = (
        number: line?.number,
        paid: line?.paid == null ? paid : line!.paid! + paid,
      );
    }
  }

  return OfflineSummary(
    outageStart: outageStart,
    printedAt: printedAt,
    orders: orders,
    payments: payments,
    cash: cash,
    card: card,
    other: other,
    lines: <OfflineSummaryOrder>[
      for (final MapEntry<String, ({String? number, PosMoney? paid})> e
          in byOrder.entries)
        OfflineSummaryOrder(
          order: e.key,
          number: e.value.number,
          paid: e.value.paid,
        ),
    ],
  );
}

/// The slip: store, title, outage start, counts, cash/card totals, the local numbers with their paid amounts.
PrintLayout layoutOfflineSummary(
  OfflineSummary s,
  ReceiptHeader header,
  PaperWidth paper,
) {
  final PrintWriter w = PrintWriter(paper);
  w.center(header.storeName, bold: true);
  w.divider('=');
  w.center('QISQA HISOBOT', bold: true, size: PrintTextSize.large);
  w.center('Internetsiz rejim');
  w.divider();
  w.pair(
    'Uzilgan vaqt:',
    s.outageStart == null
        ? "noma'lum"
        : formatReceiptDateTime(s.outageStart!.toLocal()),
  );
  w.pair('Chop etildi:', formatReceiptDateTime(s.printedAt.toLocal()));
  w.divider();
  if (s.isEmpty) {
    w.center("Navbatda hech narsa yo'q", bold: true);
  } else {
    w.pair('Buyurtmalar:', '${s.orders}');
    w.pair("To'lovlar:", '${s.payments}');
    w.pair('Naqd:', paperAmount(s.cash));
    w.pair('Karta:', paperAmount(s.card));
    if (!s.other.isZero) w.pair('Boshqa:', paperAmount(s.other));
    w.pair('Jami:', paperAmount(s.total), bold: true);
    if (s.lines.isNotEmpty) {
      w.divider();
      w.text('BUYURTMALAR', bold: true);
      for (final OfflineSummaryOrder l in s.lines) {
        w.pair(l.label, l.paid == null ? "to'lanmagan" : paperAmount(l.paid!));
      }
    }
  }
  w.divider();
  w.center(
    "Bu fiskal chek emas. Internet qaytgach navbat serverga yuboriladi.",
  );
  w.cut();
  return w.build(title: 'Qisqa hisobot');
}

/// Outcome of [printOfflineSummary]: [text] is for the screen.
typedef SummaryPrintOutcome = ({bool ok, String text});

/// Prints the summary on the receipt printer. Never throws and never blocks anything: a missing printer or a failed
/// print is only a message.
Future<SummaryPrintOutcome> printOfflineSummary(
  CashierServices services,
) async {
  try {
    final PosOffline? offline = services.data.offline;
    if (offline == null) {
      return (ok: false, text: "Qisqa hisobotni chiqarib bo'lmadi.");
    }
    await CashierPrefs.instance.load();
    final PrinterConfig? base = services.printing.receiptPrinter;
    if (base == null) {
      return (
        ok: false,
        text: "Qisqa hisobot chiqmadi: chek printeri sozlanmagan. Boshqaruv panelida Printerlar bo'limidan qo'shing.",
      );
    }
    final PrinterConfig printer = CashierPrefs.instance.apply(base);
    final OfflineSummary summary = buildOfflineSummary(
      pending: offline.store.pending,
      orphans: offline.orphans,
      outageStart: outageStartOf(offline.status),
      printedAt: services.now(),
    );
    final PrintJob job = await services.printing.printers.print(
      printer,
      layoutOfflineSummary(
        summary,
        services.billPrints.header(),
        printer.paper,
      ),
    );
    KitchenPrintIssues.receiptsOf(services.printing)
        .watch(services.printing.printers.queue, job);
    return (ok: true, text: 'Qisqa hisobot chop etishga yuborildi.');
  } on Object catch (e) {
    return (ok: false, text: 'Qisqa hisobot chiqmadi: $e');
  }
}
