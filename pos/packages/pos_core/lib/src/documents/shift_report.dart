import '../money.dart';
import 'common.dart';

enum ShiftReportKind {
  /// Oraliq hisobot: smena ochiq qoladi.
  x('X-HISOBOT'),

  /// Smenani yopish hisoboti.
  z('Z-HISOBOT');

  const ShiftReportKind(this.title);

  final String title;
}

/// Hisobotdagi nomlangan summa ("Bozorlik", "Ish haqi: Bekzod").
final class ReportAmount {
  const ReportAmount(this.label, this.amount, {this.count});

  final String label;
  final PosMoney amount;

  /// Nechta (cheklar, to'lovlar).
  final int? count;
}

/// Sanalgan kupyura: nominal (so'm) × soni.
final class CashDenomination {
  const CashDenomination(this.nominal, this.count);

  final int nominal;
  final int count;

  PosMoney get amount => PosMoney.som(nominal * count);
}

/// Smena X yoki Z hisoboti. Summalar serverdan keladi (backend hisoblaydi); bu yerda faqat qog'ozga chiqariladi va
/// oddiy hosilalar (sof tushum, o'rtacha chek, kutilgan naqd, farq) hisoblanadi.
final class ShiftReport {
  const ShiftReport({
    required this.kind,
    required this.header,
    required this.shiftNumber,
    required this.printedAt,
    required this.openedAt,
    this.openedBy,
    this.closedAt,
    this.closedBy,
    this.businessDate,
    this.receiptsCount = 0,
    this.sales = PosMoney.zero,
    this.refunds = PosMoney.zero,
    this.discounts = PosMoney.zero,
    this.serviceCharges = PosMoney.zero,
    this.roomFees = PosMoney.zero,
    this.packaging = PosMoney.zero,
    this.tenders = const <ReportAmount>[],
    this.advancesReceived = PosMoney.zero,
    this.advancesApplied = PosMoney.zero,
    this.creditSales = PosMoney.zero,
    this.creditRepayments = PosMoney.zero,
    this.openingCash = PosMoney.zero,
    this.cashSales = PosMoney.zero,
    this.cashRefunds = PosMoney.zero,
    this.cashIn = const <ReportAmount>[],
    this.cashOut = const <ReportAmount>[],
    this.countedCash,
    this.denominations = const <CashDenomination>[],
    this.voidedItems = 0,
    this.voidedAmount = PosMoney.zero,
    this.fiscalPending = 0,
    this.openOrders = 0,
    this.tips = const <ReportAmount>[],
  });

  final ShiftReportKind kind;
  final ReceiptHeader header;
  final String shiftNumber;
  final DateTime printedAt;
  final DateTime openedAt;
  final String? openedBy;
  final DateTime? closedAt;
  final String? closedBy;

  /// Biznes kun (kechasi 05:00 gacha oldingi kun).
  final DateTime? businessDate;
  final int receiptsCount;

  /// Sotuv cheklari jami (xona puli va xizmat haqi bilan).
  final PosMoney sales;

  /// Qaytarishlar (musbat son).
  final PosMoney refunds;
  final PosMoney discounts;
  final PosMoney serviceCharges;
  final PosMoney roomFees;

  /// P6-34: sotuvdagi qadoq qatorlari (o'z fiskal qatorlari bilan; [sales] ichida).
  final PosMoney packaging;

  /// To'lov turlari bo'yicha: Naqd, Karta, QR, Avans hisobidan, Nasiyaga.
  final List<ReportAmount> tenders;

  /// Olingan avanslar (bo'nak cheklari).
  final PosMoney advancesReceived;

  /// Sotuvda ayirilgan avanslar.
  final PosMoney advancesApplied;

  /// Nasiyaga yopilgan sotuvlar.
  final PosMoney creditSales;

  /// Nasiya to'lovlari (kredit cheklari).
  final PosMoney creditRepayments;
  final PosMoney openingCash;

  /// Naqd tushum (sotuv, avans, nasiya to'lovi naqd qismi).
  final PosMoney cashSales;
  final PosMoney cashRefunds;

  /// Kassaga kirim (kategoriya bo'yicha).
  final List<ReportAmount> cashIn;

  /// Kassadan chiqim: bozorlik, ish haqi, xodimga avans.
  final List<ReportAmount> cashOut;

  /// Z: kassir sanagan naqd.
  final PosMoney? countedCash;
  final List<CashDenomination> denominations;
  final int voidedItems;
  final PosMoney voidedAmount;

  /// Fiskal ma'lumoti kelmagan cheklar.
  final int fiscalPending;

  /// Yopilmagan buyurtmalar (X hisobotda ogohlantirish).
  final int openOrders;

  /// P6-22: choy puli qatorlari (jami, usul, shaxsiy/qozon, xodim bo'yicha) — tushum emas, savdodan alohida bo'limda.
  final List<ReportAmount> tips;

  PosMoney get netSales => sales - refunds;

  PosMoney get averageCheck => receiptsCount <= 0
      ? PosMoney.zero
      : PosMoney(divideHalfUp(netSales.tiyin, receiptsCount)).roundToSom();

  PosMoney get totalCashIn =>
      PosMoney.sum(cashIn.map((ReportAmount a) => a.amount));

  PosMoney get totalCashOut =>
      PosMoney.sum(cashOut.map((ReportAmount a) => a.amount));

  /// Kassada bo'lishi kerak bo'lgan naqd.
  PosMoney get expectedCash =>
      openingCash + cashSales - cashRefunds + totalCashIn - totalCashOut;

  /// Sanalgan − kutilgan (kamomad manfiy).
  PosMoney? get difference =>
      countedCash == null ? null : countedCash! - expectedCash;
}
