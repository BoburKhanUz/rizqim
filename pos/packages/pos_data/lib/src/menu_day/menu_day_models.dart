import 'package:pos_core/pos_core.dart';

import '../bootstrap/bootstrap_models.dart';
import '../json.dart';

/// "Menyuda yo'q" sababi: server stop-listga shu matn bilan yozadi (`DailyMenuService::OFF_MENU_REASON`).
const String posOffMenuReason = "Bugungi menyuda yo'q";

/// P6-15: `GET /pos/bootstrap` → `daily_menu`: bugungi menyu (`daily_menu` moduli yoqilgan filialda). Menyusi
/// belgilanmagan kunda ([set] `false`) hamma narsa sotiladi — kassa faqat ogohlantiradi.
class PosDailyMenu {
  const PosDailyMenu({
    required this.businessDate,
    this.enforced = true,
    this.set = false,
    this.productIds = const <int>{},
  });

  factory PosDailyMenu.fromJson(PosJson j) => PosDailyMenu(
    businessDate: j.strOrNull('business_date') ?? '',
    enforced: j.boolean('enforced'),
    set: j.boolean('set'),
    productIds: j.ints('product_ids').toSet(),
  );

  /// Menyu qaysi biznes kunniki, `YYYY-MM-DD`.
  final String businessDate;

  /// Qoida kuchda (modul yoqilgan).
  final bool enforced;

  /// Shu kun uchun menyu qo'yilgan.
  final bool set;

  /// Menyudagi taomlar.
  final Set<int> productIds;

  /// Qoida kuchda, lekin menyu qo'yilmagan: kassir ogohlantiriladi (sotuv to'xtamaydi).
  bool get missing => enforced && !set;

  bool contains(int productId) => productIds.contains(productId);
}

/// "Keyingi partiya 12:30" (`next_batch` taomda yoki variantda): tugagan taom qachon yana chiqadi.
class PosNextBatch {
  const PosNextBatch({
    required this.time,
    this.plannedAt,
    this.quantity,
    this.label,
  });

  factory PosNextBatch.fromJson(PosJson j) => PosNextBatch(
    time: j.strOrNull('time') ?? '',
    plannedAt: j.dateOrNull('planned_at'),
    quantity: j.quantityOrNull('quantity'),
    label: j.strOrNull('label'),
  );

  static PosNextBatch? maybe(Object? raw) => raw is Map && raw['time'] != null
      ? PosNextBatch.fromJson(PosJson(raw.cast<String, Object?>()))
      : null;

  /// Do'kon vaqti bilan `HH:MM`.
  final String time;
  final DateTime? plannedAt;

  /// Rejadagi porsiya soni (bo'lsa).
  final PosQuantity? quantity;
  final String? label;

  /// Tugma ostidagi yozuv: "Keyingi partiya 12:30".
  String get text => 'Keyingi partiya $time';
}

/// Bootstrap'dagi `next_batches[]` elementi: filialning hali boshlanmagan rejalashtirilgan partiyasi.
class PosPlannedBatch {
  const PosPlannedBatch({
    required this.id,
    required this.productId,
    required this.next,
    this.uuid,
    this.variantId,
  });

  factory PosPlannedBatch.fromJson(PosJson j) => PosPlannedBatch(
    id: j.integer('id'),
    uuid: j.strOrNull('uuid'),
    productId: j.integer('product_id'),
    variantId: j.intOrNull('product_variant_id'),
    next: PosNextBatch.fromJson(j),
  );

  final int id;
  final String? uuid;
  final int productId;
  final int? variantId;
  final PosNextBatch next;
}

/// Kompleks tarkibidagi bitta taom (`set_parts[]`): katalogda, bootstrapdagi `meal_sets` da va buyurtma qatorida
/// (sotuv paytidagi nusxa).
class PosSetPart {
  const PosSetPart({
    required this.name,
    this.variantId,
    this.variantUuid,
    this.productId,
    this.variantName,
    this.quantity = PosQuantity.one,
    this.unit = PosUnit.piece,
    this.label,
  });

  factory PosSetPart.fromJson(PosJson j) => PosSetPart(
    variantId: j.intOrNull('variant_id'),
    variantUuid: j.strOrNull('variant_uuid'),
    productId: j.intOrNull('product_id'),
    name: j.strOrNull('name') ?? j.strOrNull('product_name') ?? '',
    variantName: j.strOrNull('variant_name'),
    quantity: j.quantityOrNull('quantity') ?? PosQuantity.one,
    unit: PosUnit.fromWire(j.strOrNull('unit')),
    label: j.strOrNull('label'),
  );

  /// `set_parts` ro'yxati; `null` yoki bo'sh — kompleks emas.
  static List<PosSetPart>? listOf(Object? raw) {
    if (raw is! List || raw.isEmpty) return null;
    return <PosSetPart>[
      for (final Object? e in raw)
        if (e is Map) PosSetPart.fromJson(PosJson(e.cast<String, Object?>())),
    ];
  }

  final int? variantId;
  final String? variantUuid;
  final int? productId;
  final String name;
  final String? variantName;
  final PosQuantity quantity;
  final PosUnit unit;

  /// "Birinchi", "Salat" (egasi bergan nom).
  final String? label;

  /// Ekranda: "Birinchi: Mastava" yoki "Mastava", bittadan ortiq bo'lsa "2 x Mastava".
  String get display {
    final String base = variantName == null || variantName!.isEmpty
        ? name
        : '$name ($variantName)';
    final String counted = quantity == PosQuantity.one
        ? base
        : '${quantity.label(unit)} $base';
    return label == null || label!.isEmpty ? counted : '$label: $counted';
  }
}

/// Bootstrap'dagi `meal_sets[]` elementi: kompleks mahsuloti va uning tarkibi.
class PosMealSet {
  const PosMealSet({required this.productId, required this.parts});

  factory PosMealSet.fromJson(PosJson j) => PosMealSet(
    productId: j.integer('product_id'),
    parts: PosSetPart.listOf(j['parts']) ?? const <PosSetPart>[],
  );

  final int productId;
  final List<PosSetPart> parts;
}

/// Bootstrap'ning P6-15 qismlari: konstruktorga tegmasdan [PosBootstrap.raw] dan o'qiladi (eski server — bo'sh).
extension PosBootstrapMenuDay on PosBootstrap {
  /// Bugungi menyu; `null` — modul o'chiq yoki eski server (qoida yo'q: hamma narsa sotiladi).
  PosDailyMenu? get dailyMenu {
    final Object? block = raw['daily_menu'];
    if (block is! Map) return null;
    try {
      return PosDailyMenu.fromJson(PosJson(block.cast<String, Object?>()));
    } on FormatException {
      return null;
    }
  }

  /// Hali boshlanmagan rejalashtirilgan partiyalar (eng yaqini birinchi).
  List<PosPlannedBatch> get nextBatches {
    try {
      return PosJson(raw).objects('next_batches', PosPlannedBatch.fromJson);
    } on FormatException {
      return const <PosPlannedBatch>[];
    }
  }

  /// Kompleks tarkiblari.
  List<PosMealSet> get mealSets {
    try {
      return PosJson(raw).objects('meal_sets', PosMealSet.fromJson);
    } on FormatException {
      return const <PosMealSet>[];
    }
  }

  /// Taomning kompleks tarkibi (kompleks emas — `null`).
  List<PosSetPart>? setPartsOf(int productId) {
    for (final PosMealSet s in mealSets) {
      if (s.productId == productId) return s.parts;
    }
    return null;
  }
}

/// Hisobdan chiqarish sababi (`POST /owner/batches/{id}/write-off` `reason`). `other` izoh talab qiladi.
enum PosWriteOffReason {
  unsold('unsold', 'Sotilmay qoldi'),
  spoiled('spoiled', 'Buzildi'),
  staffMeal('staff_meal', 'Xodimlar ovqati'),
  dropped('dropped', "To'kildi yoki tushib ketdi"),
  expired('expired', 'Muddati tugadi'),
  other('other', 'Boshqa sabab');

  const PosWriteOffReason(this.wire, this.label);

  final String wire;
  final String label;

  /// Sababni izohsiz yozib bo'lmaydi.
  bool get needsNote => this == PosWriteOffReason.other;

  static PosWriteOffReason? fromWire(String? wire) {
    for (final PosWriteOffReason r in values) {
      if (r.wire == wire) return r;
    }
    return null;
  }
}

/// Hisobdan chiqarish natijasi (`write_off` + partiyaning yangi qoldig'i).
class PosBatchWriteOff {
  const PosBatchWriteOff({
    required this.uuid,
    required this.quantity,
    required this.reason,
    this.unit,
    this.note,
    this.businessDate,
    this.shiftId,
    this.batch,
  });

  factory PosBatchWriteOff.fromJson(PosJson j) {
    final PosJson w = j.obj('write_off');
    return PosBatchWriteOff(
      uuid: w.str('uuid'),
      quantity: w.quantity('quantity'),
      unit: w.strOrNull('unit'),
      reason: w.str('reason'),
      note: w.strOrNull('note'),
      businessDate: w.strOrNull('business_date'),
      shiftId: w.intOrNull('shift_id'),
      batch: j.objOrNull('batch') == null
          ? null
          : PosBatchInfo.fromJson(j.obj('batch')),
    );
  }

  final String uuid;
  final PosQuantity quantity;
  final String? unit;
  final String reason;
  final String? note;
  final String? businessDate;

  /// Terminalning ochiq smenasiga bog'langan (X/Z da ko'rinadi); `null` — smena yo'q.
  final int? shiftId;
  final PosBatchInfo? batch;
}

/// `GET /owner/batches` va hisobdan chiqarish javobidagi partiya.
class PosBatchInfo {
  const PosBatchInfo({
    required this.id,
    required this.productId,
    required this.quantityLeft,
    this.productName,
    this.variantId,
    this.unit,
    this.quantityProduced = PosQuantity.zero,
    this.writtenOff = PosQuantity.zero,
    this.isOpen = true,
  });

  factory PosBatchInfo.fromJson(PosJson j) => PosBatchInfo(
    id: j.integer('id'),
    productId: j.integer('product_id'),
    productName: j.strOrNull('product_name'),
    variantId: j.intOrNull('product_variant_id'),
    unit: j.strOrNull('unit'),
    quantityProduced: j.quantity('quantity_produced'),
    quantityLeft: j.quantity('quantity_left'),
    writtenOff: j.quantity('written_off_quantity'),
    isOpen: j.boolean('is_open', fallback: true),
  );

  final int id;
  final int productId;
  final String? productName;
  final int? variantId;
  final String? unit;
  final PosQuantity quantityProduced;
  final PosQuantity quantityLeft;
  final PosQuantity writtenOff;
  final bool isOpen;
}

/// X/Z hisobotdagi `batch_writeoffs` bloki: shu smenada terminaldan hisobdan chiqarilgan porsiyalar.
class PosShiftWriteOffs {
  const PosShiftWriteOffs({
    this.count = 0,
    this.items = const <PosShiftWriteOffRow>[],
  });

  factory PosShiftWriteOffs.fromJson(PosJson j) => PosShiftWriteOffs(
    count: j.intOrNull('count') ?? 0,
    items: j.objects('items', PosShiftWriteOffRow.fromJson),
  );

  final int count;
  final List<PosShiftWriteOffRow> items;

  bool get isEmpty => count == 0 && items.isEmpty;
}

class PosShiftWriteOffRow {
  const PosShiftWriteOffRow({
    required this.productName,
    required this.quantity,
    required this.reason,
    this.productId,
    this.unit,
    this.reasonLabel,
  });

  factory PosShiftWriteOffRow.fromJson(PosJson j) => PosShiftWriteOffRow(
    productId: j.intOrNull('product_id'),
    productName: j.strOrNull('product_name') ?? '',
    unit: j.strOrNull('unit'),
    reason: j.strOrNull('reason') ?? '',
    reasonLabel: j.strOrNull('reason_label'),
    quantity: j.quantity('quantity'),
  );

  final int? productId;
  final String productName;
  final String? unit;
  final String reason;
  final String? reasonLabel;
  final PosQuantity quantity;

  /// Sabab nomi: serverning matni, bo'lmasa o'zimizniki.
  String get reasonText =>
      reasonLabel ?? PosWriteOffReason.fromWire(reason)?.label ?? reason;

  /// "3 porsiya", "1.5 kg".
  String get quantityText {
    final PosUnit u = PosUnit.fromWire(unit);
    return u.isMeasured ? quantity.label(u) : '${quantity.format()} porsiya';
  }
}
