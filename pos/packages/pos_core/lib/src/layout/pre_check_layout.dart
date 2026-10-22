import '../documents/pre_check.dart';
import '../format.dart';
import '../print/print_line.dart';
import '../print/print_writer.dart';
import 'receipt_layout.dart';

/// Schyot → qatorlar. Pastida doim "Bu fiskal chek emas"; qayta chop etilsa "NUSXA".
PrintLayout layoutPreCheck(PreCheck c, PaperWidth paper) {
  final PrintWriter w = PrintWriter(paper);
  writeHeader(w, c.header);
  w.divider('=');
  w.center(
    c.billLabel == null ? 'SCHYOT' : 'SCHYOT (${c.billLabel})',
    bold: true,
    size: PrintTextSize.tall,
  );
  if (c.isCopy) writeCopyMark(w);
  if (c.tableLabel != null || c.guests != null) {
    w.spread(
      c.tableLabel ?? '',
      c.guests == null ? '' : 'Mehmonlar: ${c.guests}',
    );
  }
  if (c.waiter != null) w.text('Ofitsiant: ${c.waiter}');
  if (c.orderCode != null) w.text('Buyurtma: ${c.orderCode}');
  w.spread(
    c.openedAt == null ? '' : 'Ochildi: ${formatReceiptTime(c.openedAt!)}',
    formatReceiptDateTime(c.printedAt),
  );
  w.divider();

  for (final PreCheckItem item in c.items) {
    w.text(item.name);
    for (final String m in item.modifiers) {
      w.text('+ $m', indent: 2);
    }
    w.pair(
      item.quantity.priced(item.unitPrice),
      paperAmount(item.amount),
      indent: 2,
    );
  }
  w.divider();
  w.pair('Taomlar:', paperAmount(c.itemsTotal));
  for (final BillCharge charge in c.charges) {
    w.pair('${charge.title}:', paperAmount(charge.amount));
  }
  w.divider('=');
  w.pair('JAMI:', paperAmount(c.total), bold: true, size: PrintTextSize.tall);
  if (!c.prepaid.isZero) {
    w.pair('Avans:', '-${paperAmount(c.prepaid)}');
    w.pair(
      "TO'LASH KERAK:",
      paperAmount(c.toPay),
      bold: true,
      size: PrintTextSize.tall,
    );
  }
  if (c.perPerson != null) {
    w.pair(
      'Kishi boshiga (${c.shares}):',
      paperAmount(c.perPerson!),
      bold: true,
    );
  }
  w.divider();
  w.center('*** Bu fiskal chek emas ***', bold: true);
  w.center("Fiskal chek to'lovdan keyin beriladi");
  w.cut();
  return w.build(title: 'Schyot ${c.tableLabel ?? c.orderCode ?? ''}'.trim());
}
