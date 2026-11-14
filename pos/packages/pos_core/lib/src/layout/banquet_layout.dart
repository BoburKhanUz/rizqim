import '../documents/banquet_documents.dart';
import '../format.dart';
import '../print/print_line.dart';
import '../print/print_writer.dart';
import 'receipt_layout.dart';

String _qty(BanquetDishLine dish) =>
    '${dish.quantity.format()} ${banquetUnitLabel(dish.unit)}';

void _dishes(PrintWriter w, List<BanquetDishLine> dishes) {
  for (final BanquetDishLine dish in dishes) {
    w.pair(paperPart(dish.name), _qty(dish), bold: true);
    if (dish.kg != null && dish.unit != 'kg' && dish.unit != 'l') {
      w.pair('', '${dish.kg!.format()} kg', indent: 2);
    }
  }
}

void _ingredients(PrintWriter w, List<BanquetIngredientLine> ingredients) {
  for (final BanquetIngredientLine ingredient in ingredients) {
    w.pair(
      paperPart(ingredient.name),
      '${ingredient.quantity.format()} ${banquetUnitLabel(ingredient.unit)}',
    );
  }
}

/// P6-54: ishlab chiqarish varag'i → qatorlar: tadbir, mehmonlar (zaxira bilan), sexlar bo'yicha taomlar, osh
/// qozonlari va masalliqlari, ogohlantirishlar.
PrintLayout layoutBanquetProductionSheet(
  BanquetProductionSheetDoc d,
  PaperWidth paper,
) {
  final PrintWriter w = PrintWriter(paper);
  w.center(d.header.storeName, bold: true);
  w.divider();
  w.center("ISHLAB CHIQARISH VARAG'I", bold: true);
  w.center(paperPart(d.title), bold: true);
  w.center(paperPart('${d.eventDate} ${d.localTime}'));
  if (d.halls.isNotEmpty) w.center(paperPart(d.halls.join(', ')));
  w.divider();
  w.pair('Mehmonlar:', '${d.guestsBase}');
  w.pair(
    'Zaxira ${d.reservePercent}% bilan:',
    '${d.guestsPlanned}',
    bold: true,
  );
  for (final (String station, List<BanquetDishLine> dishes) in d.stations) {
    w.divider();
    w.text(paperPart(station).toUpperCase(), bold: true);
    _dishes(w, dishes);
  }
  if (d.oshKg != null) {
    w.divider();
    w.text(
      'OSH: ${d.oshPersons ?? 0} kishi, ${d.oshKg!.format()} kg',
      bold: true,
    );
    if (d.kazans.isNotEmpty) {
      w.text(
        'Qozonlar: ${d.kazans.map(((int, int) k) => '${k.$2} x ${k.$1} kg').join(', ')}',
      );
    }
    _ingredients(w, d.ingredients);
  }
  if (d.warnings.isNotEmpty) {
    w.divider();
    for (final String warning in d.warnings) {
      w.text('! ${paperPart(warning)}');
    }
  }
  w.divider();
  w.center(formatReceiptDateTime(d.printedAt));
  w.cut();
  return w.build(title: "Ishlab chiqarish varag'i ${d.eventDate}");
}

/// P6-54: bozorlik ro'yxati → qatorlar: davr, osh masalliqlari, taomlar, tadbirlar.
PrintLayout layoutBanquetShoppingList(
  BanquetShoppingListDoc d,
  PaperWidth paper,
) {
  final PrintWriter w = PrintWriter(paper);
  w.center(d.header.storeName, bold: true);
  w.divider();
  w.center("BOZORLIK RO'YXATI", bold: true);
  w.center(paperPart('${d.from} - ${d.to}'));
  if (d.ingredients.isNotEmpty) {
    w.divider();
    w.text('OSH MASALLIQLARI', bold: true);
    _ingredients(w, d.ingredients);
  }
  if (d.dishes.isNotEmpty) {
    w.divider();
    w.text('TAOMLAR', bold: true);
    _dishes(w, d.dishes);
  }
  if (d.events.isNotEmpty) {
    w.divider();
    w.text('TADBIRLAR', bold: true);
    for (final (String date, String title, int guests) in d.events) {
      w.pair(paperPart('$date $title'), '$guests kishi');
    }
  }
  w.divider();
  w.center(formatReceiptDateTime(d.printedAt));
  w.cut();
  return w.build(title: "Bozorlik ro'yxati ${d.from} - ${d.to}");
}

/// P6-54: tadbir shartnomasi → qatorlar: serverda to'ldirilgan matn qatorma-qator (bo'sh qator — bo'sh joy).
PrintLayout layoutBanquetContract(BanquetContractDoc d, PaperWidth paper) {
  final PrintWriter w = PrintWriter(paper);
  w.center(d.header.storeName, bold: true);
  w.divider();
  for (final String line in d.lines) {
    if (line.trim().isEmpty) {
      w.feed();
    } else {
      w.text(paperPart(line));
    }
  }
  w.feed(2);
  w.center(formatReceiptDateTime(d.printedAt));
  w.cut();
  return w.build(title: 'Shartnoma ${d.contractNo ?? ''}'.trim());
}
