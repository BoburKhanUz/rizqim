import '../money.dart';
import 'common.dart';

/// Fiskal chekdagi bitta qator: taom yoki xizmat (xona puli, xizmat haqi, minimal summaga to'ldirish, avans). Har
/// qatorda MXIK, birlik va QQS (backend `FiscalLine`).
final class ReceiptLine {
  const ReceiptLine({
    required this.name,
    required this.quantity,
    required this.unitPrice,
    required this.amount,
    this.vatRate,
    this.vatAmount,
    this.mxikCode,
    this.packageCode,
    this.discount = PosMoney.zero,
    this.parts = const <String>[],
  });

  final String name;
  final ItemQuantity quantity;
  final PosMoney unitPrice;

  /// Qator jami, chegirmadan keyin.
  final PosMoney amount;

  /// QQS stavkasi foizda (`"12"`, `"6"`); `null` yoki `"0"` — QQSsiz.
  final String? vatRate;

  /// Qatordagi QQS; `null` bo'lsa [amount] dan hisoblanadi (narx ichida).
  final PosMoney? vatAmount;
  final String? mxikCode;
  final String? packageCode;

  /// Qatorga tushgan chegirma (musbat son).
  final PosMoney discount;

  /// P6-31: kombo tarkibi, narxsiz ("1 ta lavash — katta, mol go'shtli", "Ichimlik — 0,4 l Pepsi"); qator nomi
  /// ostida chiqadi. Oddiy taomda bo'sh.
  final List<String> parts;

  bool get hasVat => vatRate != null && percentHundredths(vatRate!) > 0;

  PosMoney get vat =>
      vatAmount ?? (hasVat ? amount.vatIncluded(vatRate!) : PosMoney.zero);
}

/// Bitta stavka bo'yicha QQS jami.
final class VatTotal {
  const VatTotal(this.rate, this.amount);

  final String rate;
  final PosMoney amount;
}

/// Provayder qaytargan fiskal ma'lumot (backend `FiscalIssueResult`).
final class FiscalInfo {
  const FiscalInfo({
    this.fiscalModuleId,
    this.fiscalSign,
    this.fiscalNumber,
    this.qrUrl,
    this.terminalId,
  });

  /// Fiskal modul (FM) raqami.
  final String? fiscalModuleId;

  /// Fiskal belgi.
  final String? fiscalSign;

  /// Fiskal chek raqami (provayder tartib raqami).
  final String? fiscalNumber;

  /// Chekni tekshirish havolasi (QR ichida).
  final String? qrUrl;

  /// Virtual kassa yoki terminal identifikatori.
  final String? terminalId;
}

/// Fiskal chek: sotuv, qaytarish, bo'nak (avans), kredit (nasiya to'lovi).
///
/// Bo'nak va kredit chekida QR va fiskal belgi bo'lmaydi ([FiscalReceiptType.hasFiscalSign]); sotuv yoki qaytarish
/// chekining fiskal ma'lumoti hali kelmagan bo'lsa ([fiscal] `null`) chekda "Fiskal ma'lumot kutilmoqda" yoziladi.
final class FiscalReceipt {
  const FiscalReceipt({
    required this.type,
    required this.header,
    required this.number,
    required this.issuedAt,
    required this.lines,
    required this.payments,
    this.cashier,
    this.tableLabel,
    this.orderCode,
    this.guests,
    this.change = PosMoney.zero,
    this.fiscal,
    this.note,
    this.remainingDebt,
    this.customerName,
    this.isCopy = false,
    this.footer = 'Xaridingiz uchun rahmat!',
    this.discount = PosMoney.zero,
    this.discountPercent,
  });

  final FiscalReceiptType type;
  final ReceiptHeader header;

  /// Kassadagi chek raqami ("000123").
  final String number;
  final DateTime issuedAt;
  final List<ReceiptLine> lines;
  final List<ReceiptPayment> payments;
  final String? cashier;

  /// "Xona 3", "Tapchan 2", "Stol 5".
  final String? tableLabel;

  /// Buyurtma kodi ("#E101").
  final String? orderCode;
  final int? guests;

  /// Qaytim (naqd).
  final PosMoney change;
  final FiscalInfo? fiscal;

  /// Qo'shimcha izoh: bron sanasi, qaytarish sababi.
  final String? note;

  /// Kredit chekida: shu to'lovdan keyin qolgan qarz.
  final PosMoney? remainingDebt;

  /// Nasiya yoki bron egasi.
  final String? customerName;

  /// Qayta chop etilgan nusxa ("NUSXA").
  final bool isCopy;
  final String footer;

  /// Chekdagi buyurtma chegirmasi (musbat; qatorlar summasi ichida, [ReceiptLine.amount] chegirmadan keyin). Qatorlar
  /// o'z chegirmasini ko'rsatmasa chek oxirida alohida aytiladi.
  final PosMoney discount;

  /// Foizli chegirmaning foizi (`"10"`); summa bo'lsa `null`.
  final String? discountPercent;

  /// Qatorlarda chegirma ko'rsatilmagan, lekin chekda chegirma bor: oxirida "Berilgan chegirma" qatori chiqadi.
  bool get showsDiscountTotal =>
      discount.isPositive && lines.every((ReceiptLine l) => l.discount.isZero);

  /// Qatorlar jami.
  PosMoney get total => PosMoney.sum(lines.map((ReceiptLine l) => l.amount));

  /// QQS stavka bo'yicha (kattasi birinchi). QQSsiz qatorlar kirmaydi.
  List<VatTotal> get vatTotals {
    final Map<int, (String, PosMoney)> byRate = <int, (String, PosMoney)>{};
    for (final ReceiptLine line in lines.where((ReceiptLine l) => l.hasVat)) {
      final int key = percentHundredths(line.vatRate!);
      final (String, PosMoney)? prev = byRate[key];
      byRate[key] = (
        formatPercent(line.vatRate!),
        (prev?.$2 ?? PosMoney.zero) + line.vat,
      );
    }
    final List<int> keys = byRate.keys.toList()
      ..sort((int a, int b) => b.compareTo(a));
    return <VatTotal>[
      for (final int k in keys) VatTotal(byRate[k]!.$1, byRate[k]!.$2),
    ];
  }

  /// Hozir olingan pul (avans va nasiyasiz).
  PosMoney get received => PosMoney.sum(
    payments
        .where((ReceiptPayment p) => p.tender.isMoneyReceived)
        .map((ReceiptPayment p) => p.amount),
  );

  FiscalPaymentForm? get paymentForm => FiscalPaymentForm.of(payments);
}
