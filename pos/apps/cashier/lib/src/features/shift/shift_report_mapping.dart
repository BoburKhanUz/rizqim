import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';

import '../billing/cashier_ui.dart';

/// X/Z hisobotining ekrandagi va chop etiladigan ko'rinishi (`report` / `summary`, docs/API.md "Smena, kassa, X va Z";
/// tiplangan [PosShiftReport] dan) va `pos_core` [ShiftReport] ga aylantirish.
class ShiftReportView {
  ShiftReportView(this.report);

  final PosShiftReport report;

  PosShiftSales get _sales => report.sales;
  PosShiftCash get _cash => report.cash;

  PosMoney get received => report.received;
  PosMoney get refunded => report.refunded;
  PosMoney get net => report.net;
  int get bills => report.bills;
  PosMoney get billsTotal => _sales.billsTotal;
  PosMoney get averageBill => _sales.averageBill;
  PosMoney get roomFee => _sales.roomFee;
  PosMoney get serviceCharge => _sales.serviceCharge;
  PosMoney get discount => report.discounts.amount.isZero
      ? _sales.discount
      : report.discounts.amount;
  int get voidsCount => report.voids.count;
  PosMoney get voidsAmount => report.voids.amount;
  int get refundsCount => report.refunds.count;

  /// To'lov turi → (soni, summa).
  List<({String tender, int count, PosMoney amount})> get byTender =>
      <({String tender, int count, PosMoney amount})>[
        for (final PosReportRow t in report.tenders)
          (tender: t.key, count: t.count, amount: t.amount),
      ];

  /// Karta turlari.
  List<({String type, PosMoney amount})> get cards =>
      <({String type, PosMoney amount})>[
        for (final PosReportRow t in report.cards)
          (type: t.key, amount: t.amount),
      ];

  List<({String channel, int orders, PosMoney amount})> get byChannel =>
      <({String channel, int orders, PosMoney amount})>[
        for (final PosReportRow t in report.byChannel)
          (channel: t.key, orders: t.count, amount: t.amount),
      ];

  List<({String name, PosMoney amount, PosMoney service})> get byWaiter =>
      <({String name, PosMoney amount, PosMoney service})>[
        for (final PosWaiterTotal t in report.byWaiter)
          (
            name: t.name ?? 'Ofitsiantsiz',
            amount: t.amount,
            service: t.serviceCharge,
          ),
      ];

  PosMoney get depositsTaken => report.deposits.taken.amount;
  PosMoney get depositsApplied => report.deposits.applied.amount;
  PosMoney get depositsRefunded => report.deposits.refunded.amount;

  PosMoney get creditSales => report.credit.sales.amount;
  PosMoney get creditRepayments => report.credit.repayments.amount;

  PosMoney get openingCash => _cash.opening;
  PosMoney get cashSales => _cash.sales;
  PosMoney get cashRefunds => _cash.refunds;
  PosMoney get cashIn => _cash.cashIn;
  PosMoney get cashOut => _cash.cashOut;
  PosMoney get wages => _cash.wages;
  PosMoney get purchases => _cash.purchases;
  PosMoney get cashDeposits => _cash.deposits;
  PosMoney get cashDepositRefunds => _cash.depositRefunds;
  PosMoney get cashRepayments => _cash.repayments;
  PosMoney get expectedCash => report.expectedCash;

  /// `pos_core` hujjati (chop etish uchun). [counted] va [denominations] — Z uchun.
  ShiftReport toDocument({
    required ShiftReportKind kind,
    required ReceiptHeader header,
    required PosShift shift,
    required DateTime printedAt,
    PosMoney? counted,
    Map<int, int>? denominations,
    int fiscalPending = 0,
    int openOrders = 0,
  }) {
    final DateTime? business = shift.businessDate == null
        ? null
        : DateTime.tryParse(shift.businessDate!);
    return ShiftReport(
      kind: kind,
      header: header,
      shiftNumber: shift.zNumber != null
          ? 'Z-${shift.zNumber}'
          : shift.uuid.substring(0, 8),
      printedAt: printedAt.toLocal(),
      openedAt: (shift.openedAt ?? printedAt).toLocal(),
      openedBy: shift.openedBy?.name,
      closedAt: shift.closedAt?.toLocal(),
      closedBy: shift.closedBy?.name,
      businessDate: business,
      receiptsCount: bills,
      sales: billsTotal,
      refunds: refunded,
      discounts: discount,
      serviceCharges: serviceCharge,
      roomFees: roomFee,
      tenders: <ReportAmount>[
        for (final ({String tender, int count, PosMoney amount}) t in byTender)
          if (t.count > 0 || t.amount.isPositive)
            ReportAmount(tenderLabel(t.tender), t.amount, count: t.count),
      ],
      advancesReceived: depositsTaken,
      advancesApplied: depositsApplied,
      creditSales: creditSales,
      creditRepayments: creditRepayments,
      openingCash: openingCash,
      // pos_core: naqd tushum — sotuv, avans va qarz to'lovining naqd qismi
      cashSales: cashSales + cashDeposits + cashRepayments,
      cashRefunds: cashRefunds + cashDepositRefunds,
      cashIn: <ReportAmount>[
        if (cashIn.isPositive) ReportAmount('Kirim', cashIn),
      ],
      cashOut: <ReportAmount>[
        if (purchases.isPositive) ReportAmount('Bozorlik', purchases),
        if (wages.isPositive) ReportAmount('Ish haqi', wages),
        if (cashOut.isPositive) ReportAmount('Chiqim', cashOut),
      ],
      countedCash: counted,
      denominations: <CashDenomination>[
        if (denominations != null)
          for (final MapEntry<int, int> e in denominations.entries)
            if (e.value > 0) CashDenomination(e.key, e.value),
      ],
      voidedItems: voidsCount,
      voidedAmount: voidsAmount,
      fiscalPending: fiscalPending,
      openOrders: openOrders,
    );
  }
}

/// Kassa harakati turi va kategoriyalari (docs/API.md): ekrandagi nom.
const Map<PosCashMovementType, Map<String, String>> movementCategories =
    <PosCashMovementType, Map<String, String>>{
      PosCashMovementType.purchase: <String, String>{
        'bazaar': 'Bozorlik',
        'meat': "Go'sht",
        'bread': 'Non',
        'supplies': "Xo'jalik mollari",
        'other': 'Boshqa',
      },
      PosCashMovementType.wage: <String, String>{
        'daily_wage': 'Kunlik ish haqi',
        'advance': 'Avans (xodimga)',
        'bonus': 'Mukofot',
      },
      PosCashMovementType.cashOut: <String, String>{
        'collection': 'Inkassatsiya',
        'owner': 'Egaga berildi',
        'supplies': "Xo'jalik",
        'utilities': 'Kommunal',
        'other': 'Boshqa',
      },
      PosCashMovementType.cashIn: <String, String>{
        'change_fund': 'Maydalash puli',
        'owner': 'Egadan',
        'other': 'Boshqa',
      },
    };

/// Harakat nomi: "Bozorlik · Go'sht".
String movementLabel(PosCashMovement m) {
  final PosCashMovementType? type = PosCashMovementType.values
      .where((PosCashMovementType t) => t.wire == m.type)
      .firstOrNull;
  final String base = switch (m.type) {
    'opening' => "Boshlang'ich naqd",
    'sale' => 'Sotuv',
    'refund' => 'Qaytarish',
    _ => type?.label ?? m.type,
  };
  final String? cat = type == null
      ? null
      : movementCategories[type]?[m.category];
  return cat == null || cat == base ? base : '$base · $cat';
}

/// Pul kassaga kiradimi.
bool movementIn(PosCashMovement m) =>
    m.type == 'opening' || m.type == 'sale' || m.type == 'cash_in';

/// Kupyuralar (so'm): 200 000 dan 100 gacha.
const List<int> uzsDenominations = <int>[
  200000,
  100000,
  50000,
  20000,
  10000,
  5000,
  2000,
  1000,
  500,
  200,
  100,
];
