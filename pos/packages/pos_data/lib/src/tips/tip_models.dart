import 'package:pos_core/pos_core.dart';

import '../billing/billing_models.dart';
import '../bootstrap/bootstrap_models.dart';
import '../json.dart';
import '../orders/order_models.dart';
import '../shift/shift_models.dart';

/// P6-22: choy puli kimga boradi (`tips.mode`): buyurtma ofitsiantiga (yoki kassir to'lovda tanlagan xodimga) yoki
/// umumiy qozonga.
enum PosTipMode {
  personal('personal'),
  pool('pool');

  const PosTipMode(this.wire);

  final String wire;

  static PosTipMode fromWire(String? wire) =>
      wire == 'pool' ? PosTipMode.pool : PosTipMode.personal;
}

/// `GET /pos/bootstrap` → `tips` (P6-22): to'lov ekrani choy pulini so'raydimi ([enabled] — `tips` moduli) va filial
/// rejimi. Shaxsiy rejimda kassir xodimni tanlashi mumkin (sukut bo'yicha buyurtma ofitsianti); qozonni kassa
/// hisoblamaydi.
class PosTipSettings {
  const PosTipSettings({
    this.enabled = false,
    this.mode = PosTipMode.personal,
    this.poolRule = 'equal',
    this.poolPeriod = 'day',
  });

  factory PosTipSettings.fromJson(PosJson j) => PosTipSettings(
    enabled: j.boolean('enabled'),
    mode: PosTipMode.fromWire(j.strOrNull('mode')),
    poolRule: j.strOrNull('pool_rule') ?? 'equal',
    poolPeriod: j.strOrNull('pool_period') ?? 'day',
  );

  final bool enabled;
  final PosTipMode mode;

  /// `equal`, `hours`, `sales`, `role_weights`.
  final String poolRule;

  /// `day`, `shift`.
  final String poolPeriod;

  /// To'lovda xodim tanlanadi (shaxsiy rejim).
  bool get picksStaff => enabled && mode == PosTipMode.personal;
}

/// Bootstrap'ning `tips` qismi: konstruktorga tegmasdan [PosBootstrap.raw] dan o'qiladi.
extension PosBootstrapTips on PosBootstrap {
  /// Choy puli sozlamasi; eski server yoki o'qib bo'lmasa — o'chiq.
  PosTipSettings get tips {
    final Object? block = raw['tips'];
    if (block is! Map) return const PosTipSettings();
    try {
      return PosTipSettings.fromJson(PosJson(block.cast<String, Object?>()));
    } on FormatException {
      return const PosTipSettings();
    }
  }
}

/// Choy pulining hozirgi egasi (tuzatishdan keyin bir nechta bo'lishi mumkin).
class PosTipHolder {
  const PosTipHolder({
    required this.mode,
    this.staff,
    this.amount = PosMoney.zero,
  });

  factory PosTipHolder.fromJson(PosJson j) => PosTipHolder(
    mode: PosTipMode.fromWire(j.strOrNull('mode')),
    staff: PosRef.maybe(j, 'staff'),
    amount: j.money('amount'),
  );

  final PosTipMode mode;

  /// Shaxsiy choy puli kimga (`null` — qozon yoki hali hech kimga).
  final PosRef? staff;
  final PosMoney amount;
}

/// Menejer tuzatishi (asl yozuv qoladi, ishorali qator sabab bilan).
class PosTipCorrection {
  const PosTipCorrection({
    required this.amount,
    required this.mode,
    this.staffId,
    this.reason,
    this.by,
    this.createdAt,
  });

  factory PosTipCorrection.fromJson(PosJson j) => PosTipCorrection(
    amount: j.money('amount'),
    mode: PosTipMode.fromWire(j.strOrNull('mode')),
    staffId: j.intOrNull('staff_id'),
    reason: j.strOrNull('reason'),
    by: PosRef.maybe(j, 'by'),
    createdAt: j.dateOrNull('created_at'),
  );

  final PosMoney amount;
  final PosTipMode mode;
  final int? staffId;
  final String? reason;
  final PosRef? by;
  final DateTime? createdAt;
}

/// Bitta choy puli (`GET /pos/tips`, schyotning `tips`). [amount] — hozirgi (tuzatishlar bilan), [originalAmount] —
/// to'lovda olingani. Choy puli tushum emas: fiskal chekda yo'q, qaytarish unga tegmaydi.
class PosTip {
  const PosTip({
    required this.uuid,
    required this.method,
    required this.amount,
    required this.originalAmount,
    required this.mode,
    this.source,
    this.staff,
    this.holders = const <PosTipHolder>[],
    this.businessDate,
    this.billUuid,
    this.paymentUuid,
    this.shiftUuid,
    this.closed = false,
    this.corrections = const <PosTipCorrection>[],
    this.createdAt,
  });

  factory PosTip.fromJson(PosJson j) => PosTip(
    uuid: j.str('uuid'),
    method: j.strOrNull('method') ?? 'cash',
    source: j.strOrNull('source'),
    amount: j.money('amount'),
    originalAmount: j.money('original_amount'),
    mode: PosTipMode.fromWire(j.strOrNull('mode')),
    staff: PosRef.maybe(j, 'staff'),
    holders: j.objects('holders', PosTipHolder.fromJson),
    businessDate: j.strOrNull('business_date'),
    billUuid: j.strOrNull('bill_uuid'),
    paymentUuid: j.strOrNull('payment_uuid'),
    shiftUuid: j.strOrNull('shift_uuid'),
    closed: j.boolean('closed'),
    corrections: j.objects('corrections', PosTipCorrection.fromJson),
    createdAt: j.dateOrNull('created_at'),
  );

  final String uuid;

  /// `cash`, `card`, `qr`.
  final String method;

  /// `tender` (ustiga) yoki `change` (qaytimdan).
  final String? source;
  final PosMoney amount;
  final PosMoney originalAmount;
  final PosTipMode mode;
  final PosRef? staff;
  final List<PosTipHolder> holders;
  final String? businessDate;
  final String? billUuid;
  final String? paymentUuid;
  final String? shiftUuid;

  /// Yopilgan qozon bo'linishi qamraydi: tuzatib bo'lmaydi.
  final bool closed;
  final List<PosTipCorrection> corrections;
  final DateTime? createdAt;
}

/// Smena (X/Z) yoki ro'yxatning choy puli jami: tushumdan alohida.
class PosTipsSummary {
  const PosTipsSummary({
    this.count = 0,
    this.amount = PosMoney.zero,
    this.byMethod = const <String, PosMoney>{},
    this.personal = PosMoney.zero,
    this.pool = PosMoney.zero,
    this.unassigned = PosMoney.zero,
    this.byStaff = const <PosTipStaffTotal>[],
    this.corrections = PosMoney.zero,
  });

  factory PosTipsSummary.fromJson(PosJson j) => PosTipsSummary(
    count: j.intOrNull('count') ?? 0,
    amount: j.money('amount'),
    byMethod: <String, PosMoney>{
      for (final PosJson row in j.objects('by_method', (PosJson r) => r))
        row.str('method'): row.money('amount'),
    },
    personal: j.money('personal'),
    pool: j.money('pool'),
    unassigned: j.money('unassigned'),
    byStaff: j.objects('by_staff', PosTipStaffTotal.fromJson),
    corrections:
        (j.objOrNull('corrections') ?? const PosJson(<String, Object?>{}))
            .money('amount'),
  );

  final int count;
  final PosMoney amount;

  /// `cash` / `card` / `qr` → summa.
  final Map<String, PosMoney> byMethod;
  final PosMoney personal;
  final PosMoney pool;

  /// Shaxsiy, lekin hali hech kimga berilmagan (menejer beradi).
  final PosMoney unassigned;
  final List<PosTipStaffTotal> byStaff;
  final PosMoney corrections;

  PosMoney get cash => byMethod['cash'] ?? PosMoney.zero;
}

class PosTipStaffTotal {
  const PosTipStaffTotal({
    required this.staffId,
    this.name,
    this.amount = PosMoney.zero,
  });

  factory PosTipStaffTotal.fromJson(PosJson j) => PosTipStaffTotal(
    staffId: j.integer('staff_id'),
    name: j.strOrNull('name'),
    amount: j.money('amount'),
  );

  final int staffId;
  final String? name;
  final PosMoney amount;
}

/// `GET /pos/tips`: smenaning choy pullari (ofitsiant — faqat o'ziniki, [ownOnly]).
class PosShiftTips {
  const PosShiftTips({
    this.shiftUuid,
    this.businessDate,
    this.ownOnly = false,
    this.summary = const PosTipsSummary(),
    this.tips = const <PosTip>[],
  });

  factory PosShiftTips.fromJson(PosJson j) => PosShiftTips(
    shiftUuid: j.strOrNull('shift_uuid'),
    businessDate: j.strOrNull('business_date'),
    ownOnly: j.boolean('own_only'),
    summary: j.objOrNull('summary') == null
        ? const PosTipsSummary()
        : PosTipsSummary.fromJson(j.obj('summary')),
    tips: j.objects('tips', PosTip.fromJson),
  );

  final String? shiftUuid;
  final String? businessDate;
  final bool ownOnly;
  final PosTipsSummary summary;
  final List<PosTip> tips;
}

/// Schyotning choy puli (P6-22): schyot jamiga kirmaydi, chekka chiqmaydi. Konstruktorga tegmasdan [PosBill.raw] dan.
extension PosBillTips on PosBill {
  /// Shu schyot to'lovlari bilan berilgan choy puli (tuzatishlar bilan).
  PosMoney get tipsAmount => PosJson(raw).money('tips_amount');

  /// Mijoz bergan jami: schyot + choy puli.
  PosMoney get totalWithTips => total + tipsAmount;

  List<PosTip> get tips => PosJson(raw).objects('tips', PosTip.fromJson);

  /// Chekdagi xizmat haqi e'loni (xizmat haqi yoki kuver bo'lsa).
  String? get serviceChargeNotice =>
      PosJson(raw).strOrNull('service_charge_notice');

  /// [payment] bilan olingan choy puli (to'lovdagicha).
  PosMoney tipOf(PosPayment payment) {
    for (final PosJson p in PosJson(
      raw,
    ).objects('payments', (PosJson r) => r)) {
      if (p.strOrNull('uuid') == payment.uuid) return p.money('tip_amount');
    }
    return PosMoney.zero;
  }
}

/// X/Z ning choy puli qismi (`tips`, `cash.tips`, `cash.tip_payouts`): [PosShiftReport.raw] dan.
extension PosShiftReportTips on PosShiftReport {
  PosTipsSummary get tips {
    final Object? block = raw['tips'];
    if (block is! Map) return const PosTipsSummary();
    return PosTipsSummary.fromJson(PosJson(block.cast<String, Object?>()));
  }

  /// Kassadagi naqd choy puli (kutilgan naqdga kiradi).
  PosMoney get cashTips => _cash.money('tips');

  /// Xodimga berilgan choy puli (`wage` / `tips` chiqimi).
  PosMoney get tipPayouts => _cash.money('tip_payouts');

  PosJson get _cash {
    final Object? block = raw['cash'];
    return block is Map
        ? PosJson(block.cast<String, Object?>())
        : const PosJson(<String, Object?>{});
  }
}

/// P6-22 (C1): buyurtma xizmat haqi qaysi qoida bilan olingani va chekdagi e'lon — `charges` dan, konstruktorga tegmasdan
/// [PosOrder.raw] dan o'qiladi.
extension PosOrderServiceCharge on PosOrder {
  PosJson get _charges {
    final Object? block = raw['charges'];
    return block is Map
        ? PosJson(block.cast<String, Object?>())
        : const PosJson(<String, Object?>{});
  }

  /// `base`, `order_type`, `large_group`, `none`.
  String get serviceChargeRule =>
      _charges.strOrNull('service_charge_rule') ?? 'none';

  /// Joy narxi qatori restoran kuveri (mehmon boshiga).
  bool get placeFeeIsCover => _charges.strOrNull('place_fee_kind') == 'cover';

  /// Chekda ko'rsatiladigan xizmat haqi e'loni (`null` — yo'q).
  String? get serviceChargeNotice =>
      _charges.strOrNull('service_charge_notice');
}
