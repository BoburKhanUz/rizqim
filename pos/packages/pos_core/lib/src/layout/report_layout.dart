import '../documents/queue_slip.dart';
import '../documents/shift_report.dart';
import '../format.dart';
import '../money.dart';
import '../print/print_line.dart';
import '../print/print_writer.dart';
import 'receipt_layout.dart';

/// X yoki Z hisoboti → qatorlar.
PrintLayout layoutShiftReport(ShiftReport r, PaperWidth paper) {
  final PrintWriter w = PrintWriter(paper);
  final bool z = r.kind == ShiftReportKind.z;
  writeHeader(w, r.header);
  w.divider('=');
  w.center(r.kind.title, bold: true, size: PrintTextSize.large);
  if (z) w.center('SMENA YOPILDI', bold: true);
  w.text('Smena: ${r.shiftNumber}');
  if (r.businessDate != null) {
    w.text('Biznes kun: ${formatReceiptDate(r.businessDate!)}');
  }
  w.pair('Ochildi:', formatReceiptDateTime(r.openedAt));
  if (r.openedBy != null) w.text(r.openedBy!, align: PrintAlign.right);
  if (r.closedAt != null) {
    w.pair('Yopildi:', formatReceiptDateTime(r.closedAt!));
    if (r.closedBy != null) w.text(r.closedBy!, align: PrintAlign.right);
  }
  w.pair('Chop etildi:', formatReceiptDateTime(r.printedAt));

  void section(String title) {
    w.divider();
    w.text(title, bold: true);
  }

  void amount(String label, PosMoney value, {bool bold = false, int? count}) {
    final String left = count == null ? label : '$label ($count)';
    w.pair('$left:', paperAmount(value), bold: bold);
  }

  section('SAVDO');
  w.pair('Cheklar soni:', '${r.receiptsCount}');
  amount('Sotuv', r.sales);
  if (!r.refunds.isZero) amount('Qaytarish', -r.refunds);
  amount('Sof tushum', r.netSales, bold: true);
  if (!r.roomFees.isZero) amount('Shu jumladan xona puli', r.roomFees);
  if (!r.serviceCharges.isZero) {
    amount('Shu jumladan xizmat haqi', r.serviceCharges);
  }
  if (!r.packaging.isZero) amount('Shu jumladan qadoq', r.packaging);
  if (!r.discounts.isZero) amount('Chegirmalar', -r.discounts.abs());
  amount("O'rtacha chek", r.averageCheck);

  if (r.tenders.isNotEmpty) {
    section("TO'LOV TURLARI");
    for (final ReportAmount t in r.tenders) {
      amount(t.label, t.amount, count: t.count);
    }
  }

  if (!r.advancesReceived.isZero ||
      !r.advancesApplied.isZero ||
      !r.creditSales.isZero ||
      !r.creditRepayments.isZero) {
    section('AVANS VA NASIYA');
    amount("Olingan avans (bo'nak)", r.advancesReceived);
    amount('Sotuvda ayirilgan avans', r.advancesApplied);
    amount('Nasiyaga yopilgan', r.creditSales);
    amount("Nasiya to'lovlari (kredit)", r.creditRepayments);
  }

  if (r.tips.isNotEmpty) {
    // P6-22: tips are not revenue: their own section, never part of the sales above
    section('CHOY PULI (tushum emas)');
    for (final ReportAmount t in r.tips) {
      amount(t.label, t.amount, count: t.count);
    }
  }

  section('KASSA (NAQD)');
  amount("Boshlang'ich qoldiq", r.openingCash);
  amount('Naqd tushum', r.cashSales);
  if (!r.cashRefunds.isZero) amount('Naqd qaytarish', -r.cashRefunds);
  for (final ReportAmount a in r.cashIn) {
    amount('Kirim: ${a.label}', a.amount);
  }
  for (final ReportAmount a in r.cashOut) {
    amount('Chiqim: ${a.label}', -a.amount);
  }
  amount('Kassada bo\'lishi kerak', r.expectedCash, bold: true);
  if (r.countedCash != null) {
    amount('Sanaldi', r.countedCash!, bold: true);
    final PosMoney diff = r.difference!;
    final String sign = diff.isPositive ? '+' : '';
    w.pair(
      diff.isZero ? 'Farq:' : (diff.isNegative ? 'Kamomad:' : 'Ortiqcha:'),
      '$sign${paperAmount(diff)}',
      bold: true,
    );
  }
  if (r.denominations.isNotEmpty) {
    section('KUPYURALAR');
    for (final CashDenomination d in r.denominations) {
      w.pair(
        '${groupDigits(d.nominal, ' ')} x ${d.count}',
        paperAmount(d.amount),
      );
    }
  }

  if (r.voidedItems > 0 || r.fiscalPending > 0 || r.openOrders > 0) {
    section('NAZORAT');
    if (r.voidedItems > 0) {
      amount("O'chirilgan taomlar", r.voidedAmount, count: r.voidedItems);
    }
    if (r.openOrders > 0) {
      w.pair('Ochiq buyurtmalar:', '${r.openOrders} ta', bold: true);
    }
    if (r.fiscalPending > 0) {
      w.pair('Fiskal kutmoqda:', '${r.fiscalPending} ta chek', bold: true);
    }
  }
  w.divider();
  if (!z) w.center('Smena ochiq', bold: true);
  w.feed();
  w.text('Kassir imzosi: ____________');
  w.cut();
  return w.build(title: '${r.kind.title} ${r.shiftNumber}');
}

/// Navbat raqami talonchasi → qatorlar.
PrintLayout layoutQueueSlip(QueueSlip s, PaperWidth paper) {
  final PrintWriter w = PrintWriter(paper);
  w.center(s.header.storeName, bold: true);
  w.divider();
  w.center('Navbat raqamingiz');
  w.feed();
  w.center(s.number, bold: true, size: PrintTextSize.huge);
  w.feed();
  if (s.orderLabel != null) w.center(s.orderLabel!, bold: true);
  // P6-34: a scheduled pickup says when to come
  if (s.pickupAt != null) {
    w.center(
      ' Olib ketish: ${formatReceiptTime(s.pickupAt!)} ',
      bold: true,
      size: PrintTextSize.tall,
      inverted: true,
    );
  }
  w.center(
    <String>[
      if (s.orderCode != null) s.orderCode!,
      formatReceiptDateTime(s.createdAt),
    ].join('  '),
  );
  if (s.items.isNotEmpty) {
    w.divider();
    for (final String item in s.items) {
      w.text(item);
    }
  }
  w.divider();
  w.center(s.message);
  w.cut();
  return w.build(title: 'Navbat ${s.number}');
}
