import 'package:pos_core/pos_core.dart';

import '../bootstrap/bootstrap_models.dart';
import '../json.dart';

/// Kassa smenasi (`/pos/shifts*`).
class PosShift {
  const PosShift({
    required this.uuid,
    required this.status,
    this.branchId,
    this.deviceUuid,
    this.deviceName,
    this.businessDate,
    this.openedAt,
    this.openedBy,
    this.openingCash = PosMoney.zero,
    this.closedAt,
    this.closedBy,
    this.expectedCash,
    this.countedCash,
    this.countedBreakdown,
    this.difference,
    this.zNumber,
    this.forceClosed = false,
    this.closeNote,
    this.summary,
    this.raw = const <String, Object?>{},
  });

  factory PosShift.fromJson(PosJson j) {
    final PosJson? device = j.objOrNull('device');
    final PosJson? breakdown = j.objOrNull('counted_breakdown');
    return PosShift(
      uuid: j.str('uuid'),
      status: j.str('status'),
      branchId: j.intOrNull('branch_id'),
      deviceUuid: device?.strOrNull('uuid'),
      deviceName: device?.strOrNull('name'),
      businessDate: j.strOrNull('business_date'),
      openedAt: j.dateOrNull('opened_at'),
      openedBy: PosRef.maybe(j, 'opened_by'),
      openingCash: j.money('opening_cash'),
      closedAt: j.dateOrNull('closed_at'),
      closedBy: PosRef.maybe(j, 'closed_by'),
      expectedCash: j.moneyOrNull('expected_cash'),
      countedCash: j.moneyOrNull('counted_cash'),
      countedBreakdown: breakdown == null
          ? null
          : <int, int>{
              for (final MapEntry<String, Object?> e in breakdown.map.entries)
                if (int.tryParse(e.key) != null && e.value is num)
                  int.parse(e.key): (e.value! as num).toInt(),
            },
      difference: j.moneyOrNull('difference'),
      zNumber: j.intOrNull('z_number'),
      forceClosed: j.boolean('force_closed'),
      closeNote: j.strOrNull('close_note'),
      summary: j.objOrNull('summary') == null
          ? null
          : PosShiftReport.fromJson(j.obj('summary')),
      raw: j.map,
    );
  }

  final String uuid;

  /// `open`, `closed`.
  final String status;
  final int? branchId;
  final String? deviceUuid;
  final String? deviceName;
  final String? businessDate;
  final DateTime? openedAt;
  final PosRef? openedBy;
  final PosMoney openingCash;
  final DateTime? closedAt;
  final PosRef? closedBy;
  final PosMoney? expectedCash;
  final PosMoney? countedCash;

  /// Kupyura (so'm) → soni.
  final Map<int, int>? countedBreakdown;
  final PosMoney? difference;
  final int? zNumber;
  final bool forceClosed;
  final String? closeNote;

  /// Yopilgan smenaning Z hisoboti (yopish javobida va `GET /pos/shifts/{uuid}` da).
  final PosShiftReport? summary;
  final Map<String, Object?> raw;

  bool get isOpen => status == 'open';

  /// Bootstrap'dagi qisqa ko'rinish (yuqori panel uchun).
  PosShiftSummary toSummary() => PosShiftSummary(
    uuid: uuid,
    status: status,
    businessDate: businessDate,
    openedAt: openedAt,
    openedBy: openedBy?.id,
    openingCash: openingCash,
    deviceId: deviceUuid,
  );
}

/// X (yoki Z `summary`) hisobot (docs/API.md "Smena, kassa, X va Z"; backend `ShiftReport`): sotuv, to'lov turlari,
/// kartalar, kanallar, ofitsiantlar, chegirma, o'chirilgan taomlar, qaytarishlar, bron avanslari, nasiya va naqd pul
/// hisobi tiplangan; [raw] — serverning o'z javobi.
class PosShiftReport {
  const PosShiftReport({
    this.shiftUuid,
    this.generatedAt,
    this.periodFrom,
    this.periodTo,
    this.received = PosMoney.zero,
    this.refunded = PosMoney.zero,
    this.net = PosMoney.zero,
    this.bills = 0,
    this.sales = const PosShiftSales(),
    this.byTender = const <String, PosMoney>{},
    this.tenders = const <PosReportRow>[],
    this.cards = const <PosReportRow>[],
    this.byChannel = const <PosReportRow>[],
    this.byWaiter = const <PosWaiterTotal>[],
    this.discounts = const PosReportTotal(),
    this.voids = const PosReportTotal(),
    this.refunds = const PosShiftRefunds(),
    this.deposits = const PosShiftDeposits(),
    this.credit = const PosShiftCredit(),
    this.cash = const PosShiftCash(),
    this.expectedCash = PosMoney.zero,
    this.movements = const <PosCashMovement>[],
    this.raw = const <String, Object?>{},
  });

  factory PosShiftReport.fromJson(PosJson j) {
    const PosJson none = PosJson(<String, Object?>{});
    final PosJson sales = j.objOrNull('sales') ?? none;
    final PosJson cash = j.objOrNull('cash') ?? none;
    final PosJson period = j.objOrNull('period') ?? none;
    final List<PosReportRow> tenders = j.objects(
      'by_tender',
      (PosJson t) => PosReportRow.fromJson(t, 'tender'),
    );
    return PosShiftReport(
      shiftUuid: j.strOrNull('shift_uuid'),
      generatedAt: j.dateOrNull('generated_at'),
      periodFrom: period.dateOrNull('from'),
      periodTo: period.dateOrNull('to'),
      received: sales.money('received'),
      refunded: sales.money('refunded'),
      net: sales.money('net'),
      bills: sales.intOrNull('bills') ?? 0,
      sales: PosShiftSales.fromJson(sales),
      byTender: <String, PosMoney>{
        for (final PosReportRow t in tenders) t.key: t.amount,
      },
      tenders: tenders,
      cards: j.objects(
        'cards',
        (PosJson t) => PosReportRow.fromJson(t, 'card_type'),
      ),
      byChannel: j.objects(
        'by_channel',
        (PosJson t) => PosReportRow.fromJson(t, 'channel', countKey: 'orders'),
      ),
      byWaiter: j.objects('by_waiter', PosWaiterTotal.fromJson),
      discounts: PosReportTotal.of(j, 'discounts'),
      voids: PosReportTotal.of(j, 'voids'),
      refunds: PosShiftRefunds.fromJson(j.objOrNull('refunds') ?? none),
      deposits: PosShiftDeposits.fromJson(j.objOrNull('deposits') ?? none),
      credit: PosShiftCredit.fromJson(j.objOrNull('credit') ?? none),
      cash: PosShiftCash.fromJson(cash),
      expectedCash: cash.money('expected'),
      movements: j.objects('movements', PosCashMovement.fromJson),
      raw: j.map,
    );
  }

  final String? shiftUuid;
  final DateTime? generatedAt;

  /// Smena ochilgan payt va (Z da) yopilgan payt.
  final DateTime? periodFrom;
  final DateTime? periodTo;
  final PosMoney received;
  final PosMoney refunded;
  final PosMoney net;
  final int bills;

  /// Sotuv: schyotlar jami, xizmatlar, chegirma, o'rtacha chek.
  final PosShiftSales sales;

  /// `cash`, `card`, `qr`, `prepayment`, `account` → summa.
  final Map<String, PosMoney> byTender;

  /// To'lov turlari (soni bilan): [PosReportRow.key] — `cash`, `card` ...
  final List<PosReportRow> tenders;

  /// Karta turlari: [PosReportRow.key] — `uzcard`, `humo`, `visa` ...
  final List<PosReportRow> cards;

  /// Kanallar: [PosReportRow.key] — `pos_hall`, `pos_takeaway`, `telegram` ...; [PosReportRow.count] — buyurtmalar.
  final List<PosReportRow> byChannel;
  final List<PosWaiterTotal> byWaiter;
  final PosReportTotal discounts;

  /// Oshxonaga yuborilgandan keyin o'chirilgan taomlar.
  final PosReportTotal voids;
  final PosShiftRefunds refunds;
  final PosShiftDeposits deposits;
  final PosShiftCredit credit;

  /// Naqd pul hisobi (boshlang'ich, sotuv, kirim, chiqim ... kutilgan).
  final PosShiftCash cash;

  /// Kutilgan naqd (boshlang'ich + naqd sotuv + kirim − chiqim ...) — [cash] dagi `expected`.
  final PosMoney expectedCash;
  final List<PosCashMovement> movements;
  final Map<String, Object?> raw;
}

/// Hisobotdagi soni va summasi (ba'zida naqd qismi bilan).
class PosReportTotal {
  const PosReportTotal({
    this.count = 0,
    this.amount = PosMoney.zero,
    this.cash = PosMoney.zero,
  });

  factory PosReportTotal.fromJson(PosJson j) => PosReportTotal(
    count: j.intOrNull('count') ?? 0,
    amount: j.money('amount'),
    cash: j.money('cash'),
  );

  static PosReportTotal of(PosJson parent, String key) {
    final PosJson? j = parent.objOrNull(key);
    return j == null ? const PosReportTotal() : PosReportTotal.fromJson(j);
  }

  final int count;
  final PosMoney amount;

  /// Naqd qismi (avans, qarz to'lovi).
  final PosMoney cash;
}

/// Hisobot jadvalining qatori: [key] (to'lov turi, karta turi, kanal, qaytarish usuli), soni va summasi.
class PosReportRow {
  const PosReportRow({
    required this.key,
    this.count = 0,
    this.amount = PosMoney.zero,
  });

  factory PosReportRow.fromJson(
    PosJson j,
    String keyName, {
    String countKey = 'count',
  }) => PosReportRow(
    key: j.strOrNull(keyName) ?? 'other',
    count: j.intOrNull(countKey) ?? 0,
    amount: j.money('amount'),
  );

  final String key;
  final int count;
  final PosMoney amount;
}

/// Hisobotning `sales` qismi.
class PosShiftSales {
  const PosShiftSales({
    this.bills = 0,
    this.received = PosMoney.zero,
    this.refunded = PosMoney.zero,
    this.net = PosMoney.zero,
    this.billsTotal = PosMoney.zero,
    this.subtotal = PosMoney.zero,
    this.discount = PosMoney.zero,
    this.roomFee = PosMoney.zero,
    this.minSpendTopup = PosMoney.zero,
    this.serviceCharge = PosMoney.zero,
    this.placeFee = PosMoney.zero,
    this.averageBill = PosMoney.zero,
  });

  factory PosShiftSales.fromJson(PosJson j) => PosShiftSales(
    bills: j.intOrNull('bills') ?? 0,
    received: j.money('received'),
    refunded: j.money('refunded'),
    net: j.money('net'),
    billsTotal: j.money('bills_total', fallback: j.money('received')),
    subtotal: j.money('subtotal'),
    discount: j.money('discount'),
    roomFee: j.money('room_fee'),
    minSpendTopup: j.money('min_spend_topup'),
    serviceCharge: j.money('service_charge'),
    placeFee: j.money('place_fee'),
    averageBill: j.money('average_bill'),
  );

  /// To'langan schyotlar soni.
  final int bills;

  /// Olingan pul (hamma to'lov turi).
  final PosMoney received;
  final PosMoney refunded;
  final PosMoney net;

  /// To'langan schyotlar jami.
  final PosMoney billsTotal;
  final PosMoney subtotal;
  final PosMoney discount;
  final PosMoney roomFee;
  final PosMoney minSpendTopup;
  final PosMoney serviceCharge;
  final PosMoney placeFee;
  final PosMoney averageBill;
}

/// Ofitsiant bo'yicha (`by_waiter`): ofitsiantsiz buyurtmalar — [waiterId] `null`.
class PosWaiterTotal {
  const PosWaiterTotal({
    this.waiterId,
    this.name,
    this.orders = 0,
    this.bills = 0,
    this.amount = PosMoney.zero,
    this.serviceCharge = PosMoney.zero,
  });

  factory PosWaiterTotal.fromJson(PosJson j) => PosWaiterTotal(
    waiterId: j.intOrNull('waiter_id'),
    name: j.strOrNull('name'),
    orders: j.intOrNull('orders') ?? 0,
    bills: j.intOrNull('bills') ?? 0,
    amount: j.money('amount'),
    serviceCharge: j.money('service_charge'),
  );

  final int? waiterId;
  final String? name;
  final int orders;
  final int bills;
  final PosMoney amount;

  /// Shu ofitsiant schyotlaridagi xizmat haqi.
  final PosMoney serviceCharge;
}

/// Qaytarishlar: jami va usul bo'yicha ([PosReportRow.key] — `cash`, `card`, `qr`).
class PosShiftRefunds {
  const PosShiftRefunds({
    this.count = 0,
    this.amount = PosMoney.zero,
    this.byMethod = const <PosReportRow>[],
  });

  factory PosShiftRefunds.fromJson(PosJson j) => PosShiftRefunds(
    count: j.intOrNull('count') ?? 0,
    amount: j.money('amount'),
    byMethod: j.objects(
      'by_method',
      (PosJson t) => PosReportRow.fromJson(t, 'method'),
    ),
  );

  final int count;
  final PosMoney amount;
  final List<PosReportRow> byMethod;
}

/// Bron avanslari: olingan (naqd qismi bilan), schyotga ishlatilgan, qaytarilgan.
class PosShiftDeposits {
  const PosShiftDeposits({
    this.taken = const PosReportTotal(),
    this.applied = const PosReportTotal(),
    this.refunded = const PosReportTotal(),
  });

  factory PosShiftDeposits.fromJson(PosJson j) => PosShiftDeposits(
    taken: PosReportTotal.of(j, 'taken'),
    applied: PosReportTotal.of(j, 'applied'),
    refunded: PosReportTotal.of(j, 'refunded'),
  );

  final PosReportTotal taken;
  final PosReportTotal applied;
  final PosReportTotal refunded;
}

/// Nasiya: nasiyaga yopilgan schyotlar va qarz to'lovlari (naqd qismi bilan).
class PosShiftCredit {
  const PosShiftCredit({
    this.sales = const PosReportTotal(),
    this.repayments = const PosReportTotal(),
  });

  factory PosShiftCredit.fromJson(PosJson j) => PosShiftCredit(
    sales: PosReportTotal.of(j, 'sales'),
    repayments: PosReportTotal.of(j, 'repayments'),
  );

  final PosReportTotal sales;
  final PosReportTotal repayments;
}

/// Naqd pul hisobi (`cash`): kutilgan = boshlang'ich + sotuv − qaytarish + kirim − chiqim − ish haqi − bozorlik
/// + avans − avans qaytarish + qarz to'lovi.
class PosShiftCash {
  const PosShiftCash({
    this.opening = PosMoney.zero,
    this.sales = PosMoney.zero,
    this.refunds = PosMoney.zero,
    this.cashIn = PosMoney.zero,
    this.cashOut = PosMoney.zero,
    this.wages = PosMoney.zero,
    this.purchases = PosMoney.zero,
    this.deposits = PosMoney.zero,
    this.depositRefunds = PosMoney.zero,
    this.repayments = PosMoney.zero,
    this.expected = PosMoney.zero,
  });

  factory PosShiftCash.fromJson(PosJson j) => PosShiftCash(
    opening: j.money('opening'),
    sales: j.money('sales'),
    refunds: j.money('refunds'),
    cashIn: j.money('cash_in'),
    cashOut: j.money('cash_out'),
    wages: j.money('wages'),
    purchases: j.money('purchases'),
    deposits: j.money('deposits'),
    depositRefunds: j.money('deposit_refunds'),
    repayments: j.money('repayments'),
    expected: j.money('expected'),
  );

  final PosMoney opening;
  final PosMoney sales;
  final PosMoney refunds;
  final PosMoney cashIn;
  final PosMoney cashOut;
  final PosMoney wages;
  final PosMoney purchases;
  final PosMoney deposits;
  final PosMoney depositRefunds;
  final PosMoney repayments;
  final PosMoney expected;
}

class PosCashMovement {
  const PosCashMovement({
    required this.uuid,
    required this.type,
    required this.amount,
    this.category,
    this.note,
    this.employee,
    this.user,
    this.approvedBy,
    this.createdAt,
  });

  factory PosCashMovement.fromJson(PosJson j) => PosCashMovement(
    uuid: j.str('uuid'),
    type: j.str('type'),
    category: j.strOrNull('category'),
    amount: j.money('amount'),
    note: j.strOrNull('note'),
    employee: PosRef.maybe(j, 'employee'),
    user: PosRef.maybe(j, 'user'),
    approvedBy: PosRef.maybe(j, 'approved_by'),
    createdAt: j.dateOrNull('created_at'),
  );

  final String uuid;

  /// `opening`, `sale`, `cash_in`, `cash_out`, `wage`, `purchase`, `refund`, ...
  final String type;
  final String? category;
  final PosMoney amount;
  final String? note;
  final PosRef? employee;
  final PosRef? user;
  final PosRef? approvedBy;
  final DateTime? createdAt;
}

/// Smena va uning hisoboti (`current`, `x-report`, `show`).
class PosShiftWithReport {
  const PosShiftWithReport({this.shift, this.report});

  factory PosShiftWithReport.fromJson(PosJson j) => PosShiftWithReport(
    shift: j.has('shift') ? PosShift.fromJson(j.obj('shift')) : null,
    report: j.has('report')
        ? PosShiftReport.fromJson(j.obj('report'))
        : (j.has('summary') ? PosShiftReport.fromJson(j.obj('summary')) : null),
  );

  /// Ochiq smena yo'q — `null`.
  final PosShift? shift;
  final PosShiftReport? report;
}

/// Kassa harakati turi (`type`) — kategoriyalari docs/API.md da.
enum PosCashMovementType {
  cashIn('cash_in', 'Kirim'),
  cashOut('cash_out', 'Chiqim'),
  wage('wage', 'Ish haqi'),
  purchase('purchase', 'Bozorlik');

  const PosCashMovementType(this.wire, this.label);

  final String wire;
  final String label;
}
