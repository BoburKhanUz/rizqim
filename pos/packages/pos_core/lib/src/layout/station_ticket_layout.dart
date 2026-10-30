import '../documents/station_ticket.dart';
import '../format.dart';
import '../print/print_line.dart';
import '../print/print_writer.dart';
import 'receipt_layout.dart';

/// Sex markasi → qatorlar. Oshxonada uzoqdan o'qilishi uchun xona va taomlar katta shriftda; narx yo'q.
PrintLayout layoutStationTicket(StationTicket t, PaperWidth paper) {
  final PrintWriter w = PrintWriter(paper);
  w.center(t.stationName.toUpperCase(), bold: true, size: PrintTextSize.large);
  if (t.isVoid) {
    w.center(
      " O'CHIRILDI ",
      bold: true,
      size: PrintTextSize.large,
      inverted: true,
    );
    w.center('Bu taomlarni tayyorlamang!', bold: true);
  }
  if (t.isCopy) writeCopyMark(w);
  w.divider('=');
  final String place = t.tableLabel ?? t.orderLabel ?? '';
  if (place.isNotEmpty) w.text(place, bold: true, size: PrintTextSize.large);
  if (t.tableLabel != null && t.orderLabel != null) w.text(t.orderLabel!);
  w.spread(t.round == null ? '' : 'Bosqich: ${t.round}', t.orderCode ?? '');
  w.spread(
    t.waiter == null ? '' : 'Ofitsiant: ${t.waiter}',
    formatReceiptTime(t.sentAt),
  );
  if (t.guests != null) w.text('Mehmonlar: ${t.guests}');
  w.divider();

  const PrintTextSize itemSize = PrintTextSize.tall;
  for (final StationTicketItem item in t.items) {
    final String qty = item.quantity.unit.isMeasured
        ? item.quantity.label
        : '${item.quantity.label} x';
    final String seat = item.seat == null ? '' : ' (${item.seat}-o\'rin)';
    w.text(
      '${t.isVoid ? '- ' : ''}$qty ${item.name}$seat',
      bold: true,
      size: itemSize,
    );
    for (final String part in item.parts) {
      w.text('- ${paperPart(part)}', indent: 4);
    }
    for (final String m in item.modifiers) {
      w.text('+ $m', indent: 4);
    }
    if (item.note != null && item.note!.trim().isNotEmpty) {
      w.text('! ${item.note}', indent: 4, bold: true);
    }
  }
  w.divider();
  if (t.held) {
    w.center(
      ' USHLAB TURING ',
      bold: true,
      size: PrintTextSize.tall,
      inverted: true,
    );
    if (t.dueAt != null) {
      w.center(
        "Tayyor bo'lsin: ${formatReceiptTime(t.dueAt!)}",
        bold: true,
        size: PrintTextSize.tall,
      );
    }
  } else if (t.dueAt != null) {
    w.center("Tayyor bo'lsin: ${formatReceiptTime(t.dueAt!)}", bold: true);
  }
  if (t.orderNote != null && t.orderNote!.trim().isNotEmpty) {
    w.text('Izoh: ${t.orderNote}', bold: true);
  }
  if (t.isVoid) {
    if (t.voidReason != null) w.text('Sabab: ${t.voidReason}');
    if (t.voidedBy != null) w.text("O'chirdi: ${t.voidedBy}");
  }
  w.cut();
  return w.build(
    title:
        '${t.isVoid ? "O'CHIRILDI" : 'Marka'}: ${t.stationName}'
        '${t.tableLabel == null ? '' : ', ${t.tableLabel}'}',
  );
}
