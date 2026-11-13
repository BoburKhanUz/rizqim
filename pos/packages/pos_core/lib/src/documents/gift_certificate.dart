import '../money.dart';
import 'common.dart';

/// P6-43: sovg'a sertifikati varag'i (chek qog'ozida): do'kon, qiymat, kod QR va yozuvda, amal qilish muddati.
/// Sertifikat sotilganda bo'nak (avans) chekidan keyin chop etiladi; kassada kod skanerlanadi yoki terib kiritiladi.
/// Qoldiq serverda: varaq "pul" emas, yo'qolsa yoki nusxalansa ham faqat qolgan summa ishlatiladi (menejer bloklaydi).
final class GiftCertificate {
  const GiftCertificate({
    required this.header,
    required this.code,
    required this.qr,
    required this.value,
    required this.validFrom,
    required this.printedAt,
    this.validUntil,
    this.buyerName,
    this.isCopy = false,
  });

  final ReceiptHeader header;

  /// `XXXX-XXXX-XXXX`.
  final String code;

  /// QR ichidagi matn (server `print.qr`: kodning o'zi).
  final String qr;

  /// Nominal qiymat.
  final PosMoney value;

  /// Biznes kun.
  final DateTime validFrom;

  /// Oxirgi biznes kun (`null` — muddatsiz).
  final DateTime? validUntil;

  /// Kimga (ixtiyoriy, xaridor ismi).
  final String? buyerName;
  final DateTime printedAt;

  /// Qayta chop etilgan varaq.
  final bool isCopy;
}
