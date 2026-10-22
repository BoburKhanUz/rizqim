import '../documents/common.dart';
import '../documents/fiscal_receipt.dart';
import '../format.dart';
import '../money.dart';
import '../print/print_line.dart';
import '../print/print_writer.dart';

/// Qog'ozdagi summa: oddiy bo'shliq bilan (printer kodlash jadvalida ajralmas bo'shliq bo'lmasligi mumkin).
String paperAmount(PosMoney m) => m.formatAmount(separator: ' ');

/// Valyuta bilan: "450 000 so'm".
String paperMoney(PosMoney m) => m.format(separator: ' ');

/// Do'kon sarlavhasi: nom (qalin, katta), yuridik nom, STIR, manzil, telefon.
void writeHeader(PrintWriter w, ReceiptHeader h) {
  w.center(h.storeName, bold: true, size: PrintTextSize.large);
  if (h.legalName != null) w.center(h.legalName!);
  if (h.tin != null) w.center('STIR: ${h.tin}');
  if (h.address != null) w.center(h.address!);
  if (h.phone != null) w.center('Tel: ${h.phone}');
}

/// "NUSXA" belgisi (qayta chop etilgan hujjat).
void writeCopyMark(PrintWriter w) =>
    w.center(' NUSXA ', bold: true, size: PrintTextSize.large, inverted: true);

/// Fiskal chek (sotuv, qaytarish, bo'nak, kredit) → qatorlar.
PrintLayout layoutFiscalReceipt(FiscalReceipt r, PaperWidth paper) {
  final PrintWriter w = PrintWriter(paper);
  writeHeader(w, r.header);
  w.divider('=');
  w.center(r.type.title, bold: true, size: PrintTextSize.tall);
  if (r.isCopy) writeCopyMark(w);
  w.spread('Chek: ${r.number}', formatReceiptDateTime(r.issuedAt));
  if (r.cashier != null) w.text('Kassir: ${r.cashier}');
  if (r.tableLabel != null || r.guests != null) {
    w.spread(
      r.tableLabel ?? '',
      r.guests == null ? '' : 'Mehmonlar: ${r.guests}',
    );
  }
  if (r.orderCode != null) w.text('Buyurtma: ${r.orderCode}');
  if (r.customerName != null) w.text('Mijoz: ${r.customerName}');
  w.divider();

  for (final ReceiptLine line in r.lines) {
    w.text(line.name, bold: true);
    w.pair(
      line.quantity.priced(line.unitPrice),
      paperAmount(line.amount),
      indent: 2,
    );
    if (!line.discount.isZero) {
      w.pair('Chegirma', '-${paperAmount(line.discount)}', indent: 2);
    }
    if (line.hasVat) {
      w.pair(
        'QQS ${formatPercent(line.vatRate!)}%',
        paperAmount(line.vat),
        indent: 2,
      );
    } else if (r.type.hasFiscalSign) {
      w.text('QQSsiz', indent: 2);
    }
    if (line.mxikCode != null) w.text('MXIK: ${line.mxikCode}', indent: 2);
    if (line.packageCode != null) {
      w.text('Qadoq: ${line.packageCode}', indent: 2);
    }
  }
  w.divider();
  w.pair(
    r.type == FiscalReceiptType.refund ? 'QAYTARILDI:' : 'JAMI:',
    paperAmount(r.total),
    bold: true,
    size: PrintTextSize.tall,
  );
  for (final VatTotal vat in r.vatTotals) {
    w.pair('Shu jumladan QQS ${vat.rate}%:', paperAmount(vat.amount));
  }
  w.divider();

  for (final ReceiptPayment p in r.payments) {
    w.pair('${p.label}:', paperAmount(p.amount));
    if (p.reference != null) w.text('Tranzaksiya: ${p.reference}', indent: 2);
  }
  final FiscalPaymentForm? form = r.paymentForm;
  if (form != null) w.text("To'lov shakli: ${form.label}");
  if (!r.change.isZero) w.pair('Qaytim:', paperAmount(r.change));
  if (r.remainingDebt != null) {
    w.pair('Qolgan qarz:', paperAmount(r.remainingDebt!), bold: true);
  }
  if (r.note != null) {
    w.divider();
    w.text(r.note!);
  }

  w.divider();
  if (r.type.hasFiscalSign) {
    final FiscalInfo? f = r.fiscal;
    if (f == null || f.fiscalSign == null) {
      w.center("Fiskal ma'lumot kutilmoqda", bold: true);
      w.center("Chek fiskal belgisi keyinroq beriladi");
    } else {
      if (f.terminalId != null) w.text('Kassa: ${f.terminalId}');
      if (f.fiscalModuleId != null) w.text('FM: ${f.fiscalModuleId}');
      if (f.fiscalNumber != null) w.text('Fiskal chek: ${f.fiscalNumber}');
      w.text('Fiskal belgi: ${f.fiscalSign}');
      if (f.qrUrl != null) {
        w.feed();
        w.qr(f.qrUrl!, moduleSize: paper == PaperWidth.mm58 ? 5 : 6);
      }
    }
  } else if (r.type == FiscalReceiptType.advance) {
    // Bo'nak va kredit chekida ham kassa (fiskal modul) ko'rsatiladi, agar server aytgan bo'lsa
    if (r.fiscal?.terminalId != null) w.text('Kassa: ${r.fiscal!.terminalId}');
    w.center('Avans yakuniy hisobda ayiriladi.');
    w.center("Bo'nak chekida fiskal belgi va QR kod bo'lmaydi.");
  } else {
    if (r.fiscal?.terminalId != null) w.text('Kassa: ${r.fiscal!.terminalId}');
    w.center("Kredit chekida fiskal belgi va QR kod bo'lmaydi.");
  }
  w.feed();
  w.center(r.footer);
  w.cut();
  return w.build(title: '${r.type.title} ${r.number}');
}
