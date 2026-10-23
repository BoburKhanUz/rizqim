import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';

import '../billing/cashier_ui.dart';

/// X/Z hisobotining to'liq o'qilishi (`report` / `summary`, docs/API.md "Smena, kassa, X va Z"): ekranda ko'rsatish va
/// `pos_core` [ShiftReport] ga aylantirish.
class ShiftReportView {
  ShiftReportView(this.report) : _j = PosJson(report.raw);

  final PosShiftReport report;
  final PosJson _j;

  PosJson _obj(String key) =>
      _j.objOrNull(key) ?? const PosJson(<String, Object?>{});

  PosJson get sales => _obj('sales');
  PosJson get cash => _obj('cash');

  PosMoney get received => sales.money('received');
  PosMoney get refunded => sales.money('refunded');
  PosMoney get net => sales.money('net');
  int get bills => sales.intOrNull('bills') ?? 0;
  PosMoney get billsTotal => sales.money('bills_total', fallback: received);
  PosMoney get averageBill => sales.money('average_bill');
  PosMoney get roomFee => sales.money('room_fee');
  PosMoney get serviceCharge => sales.money('service_charge');
  PosMoney get discount =>
      _obj('discounts').money('amount', fallback: sales.money('discount'));
  int get voidsCount => _obj('voids').intOrNull('count') ?? 0;
  PosMoney get voidsAmount => _obj('voids').money('amount');
  int get refundsCount => _obj('refunds').intOrNull('count') ?? 0;

  /// To'lov turi → (soni, summa).
  List<({String tender, int count, PosMoney amount})> get byTender =>
      <({String tender, int count, PosMoney amount})>[
        for (final PosJson t in _j.objects('by_tender', (PosJson x) => x))
          (
            tender: t.str('tender'),
            count: t.intOrNull('count') ?? 0,
            amount: t.money('amount'),
          ),
      ];

  /// Karta turlari.
  List<({String type, PosMoney amount})> get cards =>
      <({String type, PosMoney amount})>[
        for (final PosJson t in _j.objects('cards', (PosJson x) => x))
          (
            type: t.strOrNull('card_type') ?? t.strOrNull('type') ?? 'other',
            amount: t.money('amount'),
          ),
      ];

  List<({String channel, int orders, PosMoney amount})> get byChannel =>
      <({String channel, int orders, PosMoney amount})>[
        for (final PosJson t in _j.objects('by_channel', (PosJson x) => x))
          (
            channel: t.str('channel'),
            orders: t.intOrNull('orders') ?? 0,
            amount: t.money('amount'),
          ),
      ];

  List<({String name, PosMoney amount, PosMoney service})> get byWaiter =>
      <({String name, PosMoney amount, PosMoney service})>[
        for (final PosJson t in _j.objects('by_waiter', (PosJson x) => x))
          (
            name: t.strOrNull('name') ?? 'Ofitsiantsiz',
            amount: t.money('amount'),
            service: t.money('service_charge'),
          ),
      ];

  PosJson get _deposits => _obj('deposits');
  PosMoney get depositsTaken =>
      (_deposits.objOrNull('taken') ?? const PosJson(<String, Object?>{}))
          .money('amount');
  PosMoney get depositsApplied =>
      (_deposits.objOrNull('applied') ?? const PosJson(<String, Object?>{}))
          .money('amount');
  PosMoney get depositsRefunded =>
      (_deposits.objOrNull('refunded') ?? const PosJson(<String, Object?>{}))
          .money('amount');

  PosJson get _credit => _obj('credit');
  PosMoney get creditSales =>
      (_credit.objOrNull('sales') ?? const PosJson(<String, Object?>{})).money(
        'amount',
      );
  PosMoney get creditRepayments =>
      (_credit.objOrNull('repayments') ?? const PosJson(<String, Object?>{}))
          .money('amount');

  PosMoney get openingCash => cash.money('opening');
  PosMoney get cashSales => cash.money('sales');
  PosMoney get cashRefunds => cash.money('refunds');
  PosMoney get cashIn => cash.money('cash_in');
  PosMoney get cashOut => cash.money('cash_out');
  PosMoney get wages => cash.money('wages');
  PosMoney get purchases => cash.money('purchases');
  PosMoney get cashDeposits => cash.money('deposits');
  PosMoney get cashDepositRefunds => cash.money('deposit_refunds');
  PosMoney get cashRepayments => cash.money('repayments');
  PosMoney get expectedCash =>
      cash.money('expected', fallback: report.expectedCash);

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
