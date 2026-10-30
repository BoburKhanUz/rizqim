import '../documents/label_documents.dart';
import '../format.dart';
import '../labels/label_layout.dart';

/// Stakan stikeri (P6-32): tepada navbat raqami katta (qalin), o'ngda vaqt va "2/3"; pastda taom (qalin), hajm,
/// modifikatorlar (joy qolgancha), eng pastda mijoz ismi yoki "Olib ketish". Joy yetmasa modifikatorlar kesiladi (".."),
/// taom nomi 2 qatorgacha.
LabelLayout layoutCupSticker(CupSticker s, LabelSize size) {
  final LabelWriter w = LabelWriter(size);

  // Sarlavha: raqam 2x (2 qator), o'ngda vaqt va stakan tartibi
  final String time = formatReceiptTime(s.printedAt);
  final String order = s.total > 1 ? '${s.index}/${s.total}' : '';
  final String number = s.number.trim().isEmpty ? '' : '#${s.number.trim()}';
  final int headRow = w.row;
  if (number.isNotEmpty) {
    w.text(number, scale: 2, bold: true, maxRows: 2);
  } else {
    w.text(time, scale: 2, bold: true, maxRows: 2);
  }
  // Raqamning o'ngidagi bo'sh joy: vaqt (yuqori qator) va stakan tartibi (pastki qator)
  final LabelText? head = w.texts.isEmpty ? null : w.texts.last;
  if (head != null && number.isNotEmpty) {
    final int free = size.cols - (head.col + head.cells) - 1;
    if (free >= time.length) {
      w.texts.add(LabelText(headRow, size.cols - time.length, time));
      if (order.isNotEmpty && free >= order.length) {
        w.texts.add(LabelText(headRow + 1, size.cols - order.length, order));
      }
    }
  }

  w.reserved = 1; // mijoz qatori
  w.text(s.itemName, bold: true, maxRows: 2);
  final String? variant = s.variantName?.trim();
  if (variant != null && variant.isNotEmpty) w.text(variant, maxRows: 1);
  if (s.modifiers.isNotEmpty) {
    w.text(
      '+ ${s.modifiers.map((String m) => m.trim()).join(', ')}',
      maxRows: w.rowsLeft,
    );
  }
  w.reserved = 0;
  final String? customer = s.customerName?.trim();
  w.footer(
    customer == null || customer.isEmpty ? s.takeawayLabel : customer,
    bold: true,
  );

  return w.build(
    title:
        'Stiker${s.number.isEmpty ? '' : ' ${s.number}'}: ${s.itemName}'
        '${s.total > 1 ? ' (${s.index}/${s.total})' : ''}',
  );
}

/// Olib ketish paketiga yorliq (P6-32): buyurtma raqami katta, buyurtma turi, mahsulotlar soni, mijoz, olib ketish
/// vaqti (oldindan buyurtmada teskari rangda), eng pastda chop etilgan vaqt.
LabelLayout layoutBagLabel(BagLabel b, LabelSize size) {
  final LabelWriter w = LabelWriter(size);
  final String number = b.number.trim().startsWith('#')
      ? b.number.trim()
      : '#${b.number.trim()}';
  w.text(number, scale: 2, bold: true, maxRows: 2);
  w.bar();
  w.reserved = 1;
  w.text(b.orderLabel, bold: true, maxRows: 1);
  w.text('Mahsulot: ${b.itemCount} ta', maxRows: 1);
  final String? customer = b.customerName?.trim();
  if (customer != null && customer.isNotEmpty) w.text(customer, maxRows: 2);
  final DateTime? pickup = b.pickupAt;
  if (pickup != null) {
    w.text(
      " Olib ketish: ${formatReceiptTime(pickup)} ",
      bold: true,
      inverted: true,
      maxRows: 1,
    );
  }
  w.reserved = 0;
  w.footer(formatReceiptDateTime(b.printedAt));

  return w.build(title: 'Paket ${b.number}');
}
