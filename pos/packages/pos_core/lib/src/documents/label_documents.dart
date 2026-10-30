/// Ichimlik stakaniga stiker (P6-32): navbat raqami, taom, hajm, modifikatorlar, mijoz ismi yoki "Olib ketish", vaqt.
/// Bitta stakan — bitta stiker; qatorda 3 ta bo'lsa [index]/[total] ("2/3") bilan 3 ta.
final class CupSticker {
  const CupSticker({
    required this.number,
    required this.itemName,
    required this.printedAt,
    this.variantName,
    this.modifiers = const <String>[],
    this.customerName,
    this.takeawayLabel = 'Olib ketish',
    this.index = 1,
    this.total = 1,
  }) : assert(index >= 1 && total >= 1 && index <= total);

  /// Navbat raqami ("57"); bo'sh qator — raqamsiz buyurtma (sarlavhada faqat vaqt chiqadi).
  final String number;
  final String itemName;

  /// Hajm yoki variant ("0,4 l", "Katta").
  final String? variantName;

  /// "Sut 0%", "Vanil sirop".
  final List<String> modifiers;

  /// Mijoz ismi; `null` — [takeawayLabel].
  final String? customerName;
  final String takeawayLabel;
  final DateTime printedAt;

  /// Qatordagi nechanchi stakan va jami.
  final int index;
  final int total;
}

/// Olib ketish paketiga yorliq (P6-32): buyurtma raqami, mahsulotlar soni, mijoz va olib ketish vaqti.
final class BagLabel {
  const BagLabel({
    required this.number,
    required this.itemCount,
    required this.printedAt,
    this.orderLabel = 'Olib ketish',
    this.customerName,
    this.pickupAt,
  });

  /// Navbat raqami yoki buyurtma kodi ("57", "#T057").
  final String number;

  /// Paketdagi mahsulotlar soni (donada).
  final int itemCount;
  final DateTime printedAt;

  /// "Olib ketish", "Yetkazish".
  final String orderLabel;
  final String? customerName;

  /// Oldindan buyurtma: mijoz olib ketadigan vaqt (`null` — hozir).
  final DateTime? pickupAt;
}
