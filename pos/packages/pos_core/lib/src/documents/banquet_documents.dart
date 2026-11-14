import '../quantity.dart';
import 'common.dart';

/// P6-54: tadbir taomi qog'ozda — nomi, jami miqdor (birligi bilan), kg (ma'lum bo'lsa).
final class BanquetDishLine {
  const BanquetDishLine({
    required this.name,
    required this.quantity,
    required this.unit,
    this.kg,
  });

  final String name;
  final PosQuantity quantity;

  /// Backend birligi: `portion`, `piece`, `kg`, `l` (yoki osh masallig'ida `dona`).
  final String unit;
  final PosQuantity? kg;
}

/// Osh masallig'i qog'ozda: nomi, miqdori, birligi (`kg`, `l`, `dona`).
final class BanquetIngredientLine {
  const BanquetIngredientLine({
    required this.name,
    required this.quantity,
    required this.unit,
  });

  final String name;
  final PosQuantity quantity;
  final String unit;
}

/// Backend birligining qog'ozdagi nomi.
String banquetUnitLabel(String unit) => switch (unit) {
  'portion' => 'porsiya',
  'piece' || 'dona' => 'dona',
  'kg' => 'kg',
  'l' => 'l',
  _ => unit,
};

/// P6-54 (E4): oshxonaning ishlab chiqarish varag'i: paket taomlari × rejalangan mehmonlar sexlar bo'yicha, osh
/// qozonlari va masalliqlari, ogohlantirishlar. Mijoz ma'lumoti yo'q.
final class BanquetProductionSheetDoc {
  const BanquetProductionSheetDoc({
    required this.header,
    required this.title,
    required this.eventDate,
    required this.localTime,
    required this.halls,
    required this.guestsBase,
    required this.guestsPlanned,
    required this.reservePercent,
    required this.printedAt,
    this.stations = const <(String, List<BanquetDishLine>)>[],
    this.oshPersons,
    this.oshKg,
    this.kazans = const <(int, int)>[],
    this.ingredients = const <BanquetIngredientLine>[],
    this.warnings = const <String>[],
  });

  final ReceiptHeader header;
  final String title;

  /// "20.01.2027".
  final String eventDate;

  /// "18:00–23:00".
  final String localTime;
  final List<String> halls;
  final int guestsBase;
  final int guestsPlanned;

  /// "10" (foiz).
  final String reservePercent;
  final DateTime printedAt;

  /// (sex nomi, taomlar).
  final List<(String, List<BanquetDishLine>)> stations;
  final int? oshPersons;
  final PosQuantity? oshKg;

  /// (qozon o'lchami kg, soni).
  final List<(int, int)> kazans;
  final List<BanquetIngredientLine> ingredients;
  final List<String> warnings;
}

/// P6-54 (E4): bozorlik ro'yxati: tanlangan kunlardagi tadbirlar uchun osh masalliqlari va taomlar jami.
final class BanquetShoppingListDoc {
  const BanquetShoppingListDoc({
    required this.header,
    required this.from,
    required this.to,
    required this.printedAt,
    this.events = const <(String, String, int)>[],
    this.ingredients = const <BanquetIngredientLine>[],
    this.dishes = const <BanquetDishLine>[],
  });

  final ReceiptHeader header;
  final String from;
  final String to;
  final DateTime printedAt;

  /// (sana, nomi, rejalangan mehmonlar).
  final List<(String, String, int)> events;
  final List<BanquetIngredientLine> ingredients;
  final List<BanquetDishLine> dishes;
}

/// P6-54 (E5): tadbir shartnomasi — do'kon shabloni serverda to'ldirilgan matn (qatorlari).
final class BanquetContractDoc {
  const BanquetContractDoc({
    required this.header,
    required this.lines,
    required this.printedAt,
    this.contractNo,
  });

  final ReceiptHeader header;
  final String? contractNo;
  final List<String> lines;
  final DateTime printedAt;
}
