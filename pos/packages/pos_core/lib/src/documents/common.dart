import '../money.dart';
import '../quantity.dart';

/// Chek boshidagi do'kon ma'lumoti.
final class ReceiptHeader {
  const ReceiptHeader({
    required this.storeName,
    this.legalName,
    this.tin,
    this.address,
    this.phone,
  });

  /// Brend nomi ("Choyxona Bog'bon").
  final String storeName;

  /// Yuridik nomi ("Bog'bon Savdo" MChJ yoki YaTT F.I.Sh.).
  final String? legalName;

  /// STIR.
  final String? tin;
  final String? address;
  final String? phone;
}

/// To'lov turi (backend `PosTender`): naqd, karta, QR, avans hisobidan, nasiyaga.
enum PosTender {
  cash('cash', 'Naqd'),
  card('card', 'Karta'),
  qr('qr', "QR orqali"),
  prepayment('prepayment', 'Avans hisobidan'),
  account('account', 'Nasiyaga');

  const PosTender(this.wire, this.label);

  final String wire;
  final String label;

  /// Pul hozir kassaga tushdimi (avans va nasiya — yo'q).
  bool get isMoneyReceived => this == cash || this == card || this == qr;

  static PosTender? fromWire(String? value) {
    for (final PosTender t in values) {
      if (t.wire == value) return t;
    }
    return null;
  }
}

/// Chek turi (backend `FiscalReceiptType`, VM 943: sotuv, qaytarish, bo'nak, kredit).
enum FiscalReceiptType {
  sale('sale', 'SOTUV CHEKI'),
  refund('refund', 'QAYTARISH CHEKI'),

  /// Avans (bron, oldindan buyurtma): QR va fiskal belgisiz.
  advance('advance', "BO'NAK CHEKI"),

  /// Nasiya (qarz) to'lovi: QR va fiskal belgisiz.
  credit('credit', 'KREDIT CHEKI');

  const FiscalReceiptType(this.wire, this.title);

  final String wire;
  final String title;

  /// Chekda fiskal belgi va QR kod bo'ladimi (bo'nak va kredit chekida — yo'q).
  bool get hasFiscalSign => this == sale || this == refund;

  static FiscalReceiptType? fromWire(String? value) {
    for (final FiscalReceiptType t in values) {
      if (t.wire == value) return t;
    }
    return null;
  }
}

/// Chekning umumiy to'lov shakli (backend `FiscalPaymentForm`).
enum FiscalPaymentForm {
  cash('cash', 'Naqd pul'),
  card('card', 'Bank kartasi'),
  qr('qr', "QR-kod orqali"),
  mixed('mixed', 'Aralash');

  const FiscalPaymentForm(this.wire, this.label);

  final String wire;
  final String label;

  /// To'lovlardan shakl: pul olingan turlar bitta bo'lsa o'sha, bir nechta bo'lsa aralash, hech biri bo'lmasa
  /// (to'liq avans hisobidan) — naqd emas, `null`.
  static FiscalPaymentForm? of(Iterable<ReceiptPayment> payments) {
    final Set<PosTender> tenders = payments
        .where(
          (ReceiptPayment p) => p.tender.isMoneyReceived && !p.amount.isZero,
        )
        .map((ReceiptPayment p) => p.tender)
        .toSet();
    if (tenders.isEmpty) return null;
    if (tenders.length > 1) return mixed;
    return switch (tenders.single) {
      PosTender.cash => cash,
      PosTender.card => card,
      _ => qr,
    };
  }
}

/// Chek yoki schyotdagi bitta to'lov.
final class ReceiptPayment {
  const ReceiptPayment(
    this.tender,
    this.amount, {
    this.cardType,
    this.reference,
  });

  final PosTender tender;
  final PosMoney amount;

  /// "Uzcard", "Humo", "Visa".
  final String? cardType;

  /// Tranzaksiya raqami yoki RRN.
  final String? reference;

  String get label =>
      cardType == null ? tender.label : '${tender.label} ($cardType)';
}

/// Taom qatorining narx ko'rinishidagi umumiy qismi: nom, miqdor, birlik.
final class ItemQuantity {
  const ItemQuantity(
    this.quantity, {
    this.unit = PosUnit.piece,
    this.unitLabel,
  });

  final PosQuantity quantity;
  final PosUnit unit;

  /// Variantning birlik yorlig'i ("kg (guruch)"); `null` — [PosUnit.label].
  final String? unitLabel;

  /// "1.5 kg", "2".
  String get label => quantity.label(unit, unitLabel: unitLabel);

  /// Narx qatori uchun: "2 x 25 000" yoki "1.5 kg x 160 000".
  String priced(PosMoney unitPrice, {String separator = ' '}) =>
      '$label x ${unitPrice.formatAmount(separator: separator)}';
}
