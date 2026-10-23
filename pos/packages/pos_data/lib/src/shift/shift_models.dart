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

/// X (yoki Z `summary`) hisobot. Asosiy raqamlar tiplangan, qolgani [raw] da (P3-22 chop etish uchun to'liq o'qiydi).
class PosShiftReport {
  const PosShiftReport({
    this.shiftUuid,
    this.generatedAt,
    this.received = PosMoney.zero,
    this.refunded = PosMoney.zero,
    this.net = PosMoney.zero,
    this.bills = 0,
    this.byTender = const <String, PosMoney>{},
    this.expectedCash = PosMoney.zero,
    this.movements = const <PosCashMovement>[],
    this.raw = const <String, Object?>{},
  });

  factory PosShiftReport.fromJson(PosJson j) {
    final PosJson sales =
        j.objOrNull('sales') ?? const PosJson(<String, Object?>{});
    final PosJson cash =
        j.objOrNull('cash') ?? const PosJson(<String, Object?>{});
    return PosShiftReport(
      shiftUuid: j.strOrNull('shift_uuid'),
      generatedAt: j.dateOrNull('generated_at'),
      received: sales.money('received'),
      refunded: sales.money('refunded'),
      net: sales.money('net'),
      bills: sales.intOrNull('bills') ?? 0,
      byTender: <String, PosMoney>{
        for (final PosJson t in j.objects('by_tender', (PosJson x) => x))
          t.str('tender'): t.money('amount'),
      },
      expectedCash: cash.money('expected'),
      movements: j.objects('movements', PosCashMovement.fromJson),
      raw: j.map,
    );
  }

  final String? shiftUuid;
  final DateTime? generatedAt;
  final PosMoney received;
  final PosMoney refunded;
  final PosMoney net;
  final int bills;

  /// `cash`, `card`, `qr`, `prepayment`, `account` → summa.
  final Map<String, PosMoney> byTender;

  /// Kutilgan naqd (boshlang'ich + naqd sotuv + kirim − chiqim ...).
  final PosMoney expectedCash;
  final List<PosCashMovement> movements;
  final Map<String, Object?> raw;
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
