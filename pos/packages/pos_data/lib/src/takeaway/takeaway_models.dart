import 'package:pos_core/pos_core.dart';

import '../billing/billing_models.dart';
import '../json.dart';
import '../orders/order_models.dart';

/// P6-34: qadoq qoidasi (backend `PackagingRules::RULE`, bootstrap `takeaway.packaging`): bitta idish — o'z narxi va
/// MXIK'i bilan, buyurtmaga (`order`) yoki kategoriya/taom porsiyasiga (`item`), qaysi buyurtma turlarida
/// (`takeaway`, `pickup`, `delivery`; zalda hech qachon).
class PosPackagingRule {
  const PosPackagingRule({
    required this.id,
    required this.name,
    required this.basis,
    required this.unitPrice,
    this.categoryId,
    this.productId,
    this.quantity = 1,
    this.mxik,
    this.packageCode,
    this.unit = 'dona',
    this.orderTypes = const <String>[],
    this.isActive = true,
    this.sortOrder = 0,
  });

  factory PosPackagingRule.fromJson(PosJson j) => PosPackagingRule(
    id: j.integer('id'),
    name: j.strOrNull('name') ?? '',
    basis: j.strOrNull('basis') ?? 'order',
    categoryId: j.intOrNull('category_id'),
    productId: j.intOrNull('product_id'),
    quantity: j.intOrNull('quantity') ?? 1,
    unitPrice: j.money('unit_price'),
    mxik: j.strOrNull('mxik'),
    packageCode: j.strOrNull('package_code'),
    unit: j.strOrNull('unit') ?? 'dona',
    orderTypes: j.strings('order_types'),
    isActive: j.boolean('is_active', fallback: true),
    sortOrder: j.intOrNull('sort_order') ?? 0,
  );

  final int id;
  final String name;

  /// `order` (buyurtmaga) yoki `item` (taom porsiyasiga).
  final String basis;
  final int? categoryId;
  final int? productId;
  final int quantity;
  final PosMoney unitPrice;
  final String? mxik;
  final String? packageCode;
  final String unit;
  final List<String> orderTypes;
  final bool isActive;
  final int sortOrder;

  bool get perOrder => basis == 'order';
}

/// P6-34: buyurtmadagi qadoq qatori (backend `PackagingRules::LINE`, `charges.packaging_lines`): olib tashlangani
/// (`removed`) ro'yxatda qoladi, summasi 0.
class PosPackagingLine {
  const PosPackagingLine({
    required this.ruleId,
    required this.name,
    required this.quantity,
    required this.amount,
    this.basis = 'order',
    this.unit = 'dona',
    this.unitPrice = PosMoney.zero,
    this.mxik,
    this.packageCode,
    this.removed = false,
    this.removedReason,
  });

  factory PosPackagingLine.fromJson(PosJson j) => PosPackagingLine(
    ruleId: j.integer('rule_id'),
    name: j.strOrNull('name') ?? '',
    basis: j.strOrNull('basis') ?? 'order',
    quantity: j.intOrNull('quantity') ?? 1,
    unit: j.strOrNull('unit') ?? 'dona',
    unitPrice: j.money('unit_price'),
    amount: j.money('amount'),
    mxik: j.strOrNull('mxik'),
    packageCode: j.strOrNull('package_code'),
    removed: j.boolean('removed'),
    removedReason: j.strOrNull('removed_reason'),
  );

  final int ruleId;
  final String name;
  final String basis;
  final int quantity;
  final String unit;
  final PosMoney unitPrice;
  final PosMoney amount;
  final String? mxik;
  final String? packageCode;
  final bool removed;
  final String? removedReason;

  Map<String, Object?> toJson() => <String, Object?>{
    'rule_id': ruleId,
    'name': name,
    'basis': basis,
    'quantity': quantity,
    'unit': unit,
    'unit_price': unitPrice.toApiString(),
    'amount': amount.toApiString(),
    'mxik': mxik,
    'package_code': packageCode,
    'removed': removed,
    'removed_reason': removedReason,
  };
}

/// P6-34: buyurtmaning olib ketish vaqti (backend `ScheduledPickup::PICKUP`, buyurtmaning `pickup`): oshxona uni
/// [kitchenFireAt] da oladi; [waiting] — hali olmagan.
class PosPickupTime {
  const PosPickupTime({
    required this.pickupAt,
    this.kitchenFireAt,
    this.kitchenFiredAt,
    this.waiting = false,
    this.localTime = '',
  });

  factory PosPickupTime.fromJson(PosJson j) => PosPickupTime(
    pickupAt: j.date('pickup_at'),
    kitchenFireAt: j.dateOrNull('kitchen_fire_at'),
    kitchenFiredAt: j.dateOrNull('kitchen_fired_at'),
    waiting: j.boolean('waiting'),
    localTime: j.strOrNull('local_time') ?? '',
  );

  final DateTime pickupAt;
  final DateTime? kitchenFireAt;
  final DateTime? kitchenFiredAt;
  final bool waiting;

  /// Do'kon vaqtida, `dd.MM HH:mm` (talon va chek uchun).
  final String localTime;
}

/// P6-34: olib ketish vaqti qoidalari (bootstrap `takeaway.pickup`; modul o'chiq — `null`).
class PosPickupRules {
  const PosPickupRules({
    this.minLeadMinutes = 20,
    this.slotMinutes = 15,
    this.maxDaysAhead = 1,
    this.prepMinutes = 15,
  });

  factory PosPickupRules.fromJson(PosJson j) => PosPickupRules(
    minLeadMinutes: j.intOrNull('min_lead_minutes') ?? 20,
    slotMinutes: j.intOrNull('slot_minutes') ?? 15,
    maxDaysAhead: j.intOrNull('max_days_ahead') ?? 1,
    prepMinutes: j.intOrNull('prep_minutes') ?? 15,
  );

  final int minLeadMinutes;
  final int slotMinutes;
  final int maxDaysAhead;
  final int prepMinutes;

  /// Hozirgi vaqtdan eng erta olib ketish vaqti (server bir daqiqa sabr qiladi).
  DateTime earliest(DateTime now) =>
      now.toUtc().add(Duration(minutes: minLeadMinutes));
}

/// P6-34: bitta olib ketish vaqti (`GET /pos/pickup-slots`, Mini App `.../pickup-slots`).
class PosPickupSlot {
  const PosPickupSlot({
    required this.at,
    required this.time,
    required this.businessDate,
  });

  factory PosPickupSlot.fromJson(PosJson j) => PosPickupSlot(
    at: j.date('at'),
    time: j.strOrNull('time') ?? '',
    businessDate: j.strOrNull('business_date') ?? '',
  );

  final DateTime at;

  /// Do'kon vaqtida `HH:mm`.
  final String time;
  final String businessDate;
}

/// P6-34 (24/7): biznes kun (backend `RoundTheClock::BUSINESS_DAY`, bootstrap `takeaway.business_day`): sana, qachon
/// boshlangan va keyingisi qachon boshlanadi. Kassa [nextStartsAt] dan keyin navbat bloklarini yangi kunga o'tkazadi
/// (talon raqami biznes kun boshida 1 dan, smena o'rtasida ham).
class PosBusinessDay {
  const PosBusinessDay({
    required this.businessDate,
    required this.startsAt,
    required this.startedAt,
    required this.nextStartsAt,
    this.roundTheClock = false,
  });

  factory PosBusinessDay.fromJson(PosJson j) => PosBusinessDay(
    businessDate: j.str('business_date'),
    startsAt: j.strOrNull('starts_at') ?? '05:00',
    startedAt: j.date('started_at'),
    nextStartsAt: j.date('next_starts_at'),
    roundTheClock: j.boolean('round_the_clock'),
  );

  final String businessDate;

  /// Filialning kun boshlanishi, `HH:mm`.
  final String startsAt;
  final DateTime startedAt;
  final DateTime nextStartsAt;

  /// `round_the_clock` moduli.
  final bool roundTheClock;

  /// [now] yangi biznes kunga o'tganmi (bootstrap eskirgan: bloklar va kun sanasi yangilanadi).
  bool isOverAt(DateTime now) => !now.toUtc().isBefore(nextStartsAt.toUtc());

  /// [at] qaysi biznes kunga tegishli, faqat shu kun va keyingisi uchun (bootstrapni qayta olmasdan): kun boshidan
  /// oldin — shu kun, keyin — ertangi sana.
  String dateAt(DateTime at) {
    if (!isOverAt(at)) return businessDate;
    final DateTime d = DateTime.parse('${businessDate}T00:00:00Z').add(
      Duration(days: 1 + at.toUtc().difference(nextStartsAt.toUtc()).inDays),
    );
    return '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
  }
}

/// P6-34: bootstrapning `takeaway` qismi: qadoq qoidalari (internetsiz narx uchun), olib ketish vaqti qoidalari va
/// biznes kun.
class PosTakeaway {
  const PosTakeaway({
    this.packaging = const <PosPackagingRule>[],
    this.pickup,
    this.businessDay,
  });

  factory PosTakeaway.fromJson(PosJson j) => PosTakeaway(
    packaging: j.objects('packaging', PosPackagingRule.fromJson),
    pickup: j.objOrNull('pickup') == null
        ? null
        : PosPickupRules.fromJson(j.obj('pickup')),
    businessDay: j.objOrNull('business_day') == null
        ? null
        : PosBusinessDay.fromJson(j.obj('business_day')),
  );

  /// Bootstrap javobidan (`takeaway` bo'lmasa — bo'sh).
  static PosTakeaway of(Map<String, Object?> bootstrapRaw) {
    final Object? t = bootstrapRaw['takeaway'];
    return t is Map ? PosTakeaway.fromJson(PosJson.of(t)) : const PosTakeaway();
  }

  final List<PosPackagingRule> packaging;

  /// `scheduled_pickup` moduli o'chiq — `null`.
  final PosPickupRules? pickup;
  final PosBusinessDay? businessDay;
}

/// P6-34: buyurtmadagi qadoq va olib ketish vaqti (server JSON'idan, model o'zgarmasdan).
extension PosOrderTakeaway on PosOrder {
  PosMoney get packagingAmount =>
      PosJson.of(raw).money('packaging_amount', fallback: PosMoney.zero);

  List<PosPackagingLine> get packagingLines {
    final Object? charges = raw['charges'];
    return charges is Map
        ? PosJson.of(charges)
              .objects('packaging_lines', PosPackagingLine.fromJson)
        : const <PosPackagingLine>[];
  }

  PosPickupTime? get pickup {
    final Object? p = raw['pickup'];
    return p is Map ? PosPickupTime.fromJson(PosJson.of(p)) : null;
  }
}

/// P6-34: schyotdagi qadoq qatorlari (chekda har biri alohida qator).
extension PosBillTakeaway on PosBill {
  PosMoney get packagingAmount =>
      PosJson.of(raw).money('packaging_amount', fallback: PosMoney.zero);

  List<PosPackagingLine> get packagingLines =>
      PosJson.of(raw).objects('packaging_lines', PosPackagingLine.fromJson);
}
