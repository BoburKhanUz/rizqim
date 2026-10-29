import 'package:pos_core/pos_core.dart';

import '../json.dart';
import '../menu_day/menu_day_models.dart';

/// POS kanali: zal (`pos_hall`) yoki olib ketish (`pos_takeaway`). Narx kanal bo'yicha.
enum PosChannel {
  hall('pos_hall', 'Zal'),
  takeaway('pos_takeaway', 'Olib ketish');

  const PosChannel(this.wire, this.label);

  final String wire;
  final String label;

  static PosChannel? fromWire(String? value) {
    for (final PosChannel c in values) {
      if (c.wire == value) return c;
    }
    return null;
  }
}

/// Nega sotib bo'lmaydi: `stopped` (stop-list) yoki `sold_out` (qozon/partiya tugadi).
enum PosUnavailableReason {
  stopped('stopped', "Stop-listda"),
  soldOut('sold_out', 'Tugadi');

  const PosUnavailableReason(this.wire, this.label);

  final String wire;
  final String label;

  static PosUnavailableReason? fromWire(String? value) {
    for (final PosUnavailableReason r in values) {
      if (r.wire == value) return r;
    }
    return null;
  }
}

/// `GET /catalog?channel=pos_hall|pos_takeaway`: bitta filialning POS menyusi.
class PosCatalog {
  const PosCatalog({
    required this.channel,
    required this.menuVersion,
    required this.branchId,
    required this.categories,
    required this.products,
    required this.availability,
    this.fetchedAt,
  });

  factory PosCatalog.fromJson(PosJson j, {DateTime? fetchedAt}) {
    final PosChannel? channel = PosChannel.fromWire(j.strOrNull('channel'));
    if (channel == null) {
      throw FormatException('Not a POS catalog', j['channel']);
    }
    return PosCatalog(
      channel: channel,
      menuVersion: j.str('menu_version'),
      branchId: j.obj('branch').integer('id'),
      categories: j.objects('categories', PosCategory.fromJson)
        ..sort(
          (PosCategory a, PosCategory b) => a.sortOrder.compareTo(b.sortOrder),
        ),
      products: j.objects('products', PosProduct.fromJson),
      availability: j.has('availability')
          ? PosAvailability.fromJson(j.obj('availability'))
          : const PosAvailability(),
      fetchedAt: fetchedAt,
    );
  }

  final PosChannel channel;
  final String menuVersion;
  final int branchId;
  final List<PosCategory> categories;
  final List<PosProduct> products;

  /// Stop-list, tugagan variantlar va ochiq qozonlar (menyu versiyasini o'zgartirmaydi).
  final PosAvailability availability;
  final DateTime? fetchedAt;

  PosCatalog withAvailability(PosAvailability next) => PosCatalog(
    channel: channel,
    menuVersion: menuVersion,
    branchId: branchId,
    categories: categories,
    products: products,
    availability: next,
    fetchedAt: fetchedAt,
  );

  /// Kategoriyadagi taomlar (o'z tartibida).
  List<PosProduct> productsIn(int categoryId) =>
      products.where((PosProduct p) => p.categoryId == categoryId).toList();

  PosProduct? product(int id) {
    for (final PosProduct p in products) {
      if (p.id == id) return p;
    }
    return null;
  }

  /// Variant uuid bo'yicha (buyurtma qatori `variant_id` uuid bilan yuboriladi).
  PosVariant? variantByUuid(String uuid) {
    for (final PosProduct p in products) {
      for (final PosVariant v in p.variants) {
        if (v.uuid == uuid) return v;
      }
    }
    return null;
  }

  /// Nom bo'yicha qidiruv (katta-kichik harf farqsiz).
  List<PosProduct> search(String query) {
    final String q = query.trim().toLowerCase();
    if (q.isEmpty) return products;
    return products
        .where((PosProduct p) => p.name.toLowerCase().contains(q))
        .toList();
  }

  /// [availability] ni hisobga olib: taom sotiladimi (mahsulotdagi belgi + stop-list + tugagan variantlar).
  bool isProductAvailable(PosProduct p) {
    if (availability.stoppedProducts.contains(p.id)) return false;
    return p.variants.any(isVariantAvailable);
  }

  bool isVariantAvailable(PosVariant v) => unavailableReasonOf(v) == null;

  /// Variant nega sotilmaydi (joriy [availability] bo'yicha; sotiladi — `null`).
  PosUnavailableReason? unavailableReasonOf(PosVariant v) {
    if (availability.stoppedProducts.contains(v.productId) ||
        availability.stoppedVariants.contains(v.id)) {
      return PosUnavailableReason.stopped;
    }
    if (availability.soldOutVariants.contains(v.id)) {
      return PosUnavailableReason.soldOut;
    }
    return null;
  }

  /// Qozon qoldig'i: variantning o'z partiyasi, bo'lmasa mahsulotniki.
  PosBatch? batchFor(PosVariant v) =>
      availability.batchFor(productId: v.productId, variantId: v.id);

  /// P6-15: kunlik taom bugungi menyuda emas (server uni stop-listga "Bugungi menyuda yo'q" sababi bilan qo'yadi).
  bool isOffMenu(PosProduct p) =>
      availability.stoppedProducts.contains(p.id) &&
      availability.stopReasons[p.id] == posOffMenuReason;

  /// P6-15: tugagan taomning "keyingi partiya 12:30": avval joriy `availability.next_batches` (menyu versiyasini
  /// o'zgartirmay yangilanadi), keyin katalogdagi taom va variantlar; sotuvdagi yoki menyuda yo'q taom uchun `null`.
  PosNextBatch? nextBatchOf(PosProduct p) {
    if (isProductAvailable(p) || isOffMenu(p)) return null;
    final PosNextBatch? fresh = availability.nextBatchFor(p.id);
    if (fresh != null) return fresh;
    if (p.nextBatch != null) return p.nextBatch;
    for (final PosVariant v in p.variants) {
      if (v.nextBatch != null) return v.nextBatch;
    }
    return null;
  }
}

class PosCategory {
  const PosCategory({
    required this.id,
    required this.name,
    this.parentId,
    this.sortOrder = 0,
    this.imageUrl,
  });

  factory PosCategory.fromJson(PosJson j) => PosCategory(
    id: j.integer('id'),
    name: j.str('name'),
    parentId: j.intOrNull('parent_id'),
    sortOrder: j.intOrNull('sort_order') ?? 0,
    imageUrl: j.strOrNull('image_url'),
  );

  final int id;
  final String name;
  final int? parentId;
  final int sortOrder;
  final String? imageUrl;
}

class PosProduct {
  const PosProduct({
    required this.id,
    required this.uuid,
    required this.categoryId,
    required this.name,
    required this.variants,
    this.description,
    this.kind = 'dish',
    this.sortOrder = 0,
    this.isAvailable = true,
    this.unavailableReason,
    this.stationId,
    this.batchTracked = false,
    this.quickAdd = false,
    this.imageUrl,
    this.modifierGroups = const <PosModifierGroup>[],
    this.nextBatch,
    this.setParts,
  });

  factory PosProduct.fromJson(PosJson j) => PosProduct(
    id: j.integer('id'),
    uuid: j.strOrNull('uuid') ?? '',
    categoryId: j.intOrNull('category_id') ?? 0,
    name: j.str('name'),
    description: j.strOrNull('description'),
    kind: j.strOrNull('kind') ?? 'dish',
    sortOrder: j.intOrNull('sort_order') ?? 0,
    isAvailable: j.boolean('is_available', fallback: true),
    unavailableReason: PosUnavailableReason.fromWire(
      j.strOrNull('unavailable_reason'),
    ),
    stationId: j.intOrNull('station_id'),
    batchTracked: j.boolean('batch_tracked'),
    quickAdd: j.boolean('quick_add'),
    imageUrl: j.strOrNull('image_url'),
    variants: j.objects('variants', PosVariant.fromJson),
    modifierGroups: j.objects('modifier_groups', PosModifierGroup.fromJson),
    nextBatch: PosNextBatch.maybe(j['next_batch']),
    setParts: PosSetPart.listOf(j['set_parts']),
  );

  final int id;
  final String uuid;
  final int categoryId;
  final String name;
  final String? description;

  /// `dish` yoki `service`.
  final String kind;
  final int sortOrder;

  /// Server hisoblagan (katalog olingan paytdagi) holat; joriy holat — [PosCatalog.isProductAvailable].
  final bool isAvailable;
  final PosUnavailableReason? unavailableReason;

  /// Sexi (taomning yo'naltirishi, bo'lmasa kategoriyaniki).
  final int? stationId;

  /// Qozon/partiya bilan sotiladi (qoldiq ko'rsatiladi).
  final bool batchTracked;

  /// Menyuda "tez qo'shish" belgilangan (P3-26): kassa va ofitsiant bir bosishda qo'shadi.
  final bool quickAdd;
  final String? imageUrl;
  final List<PosVariant> variants;
  final List<PosModifierGroup> modifierGroups;

  /// P6-15: tugagan taomning "keyingi partiya 12:30" (sotuvdagi taomda `null`).
  final PosNextBatch? nextBatch;

  /// P6-15: kompleks tarkibi (kompleks emas — `null`): bitta qator, oshxona tarkibni ko'radi.
  final List<PosSetPart>? setParts;

  bool get hasModifiers => modifierGroups.isNotEmpty;

  /// Kompleks (tarkibli to'plam).
  bool get isSet => setParts != null && setParts!.isNotEmpty;

  /// Bitta variant bo'lsa — u (tez qo'shish).
  PosVariant? get singleVariant =>
      variants.length == 1 ? variants.single : null;
}

class PosVariant {
  const PosVariant({
    required this.id,
    required this.uuid,
    required this.productId,
    required this.name,
    required this.price,
    PosMoney? basePrice,
    this.unit = PosUnit.piece,
    this.unitLabel,
    this.quantityStep,
    this.minQuantity,
    this.batchConsumption,
    this.stock,
    this.isAvailable = true,
    this.unavailableReason,
    this.nextBatch,
  }) : basePrice = basePrice ?? price;

  factory PosVariant.fromJson(PosJson j) => PosVariant(
    id: j.integer('id'),
    uuid: j.str('uuid'),
    productId: j.integer('product_id'),
    name: j.strOrNull('name') ?? '',
    price: j.money('price'),
    basePrice: j.moneyOrNull('base_price'),
    unit: PosUnit.fromWire(j.strOrNull('unit')),
    unitLabel: j.strOrNull('unit_label'),
    quantityStep: j.quantityOrNull('quantity_step'),
    minQuantity: j.quantityOrNull('min_quantity'),
    batchConsumption: j.quantityOrNull('batch_consumption'),
    stock: j.intOrNull('stock'),
    isAvailable: j.boolean('is_available', fallback: true),
    unavailableReason: PosUnavailableReason.fromWire(
      j.strOrNull('unavailable_reason'),
    ),
    nextBatch: PosNextBatch.maybe(j['next_batch']),
  );

  final int id;
  final String uuid;
  final int productId;
  final String name;

  /// Kanal narxi (bir dona yoki 1 kg / 1 l).
  final PosMoney price;

  /// Variantning o'z narxi (kanal narxisiz).
  final PosMoney basePrice;
  final PosUnit unit;

  /// Chekdagi birlik, masalan "kg (guruch)".
  final String? unitLabel;
  final PosQuantity? quantityStep;
  final PosQuantity? minQuantity;

  /// Bir birlik qozondan qancha oladi.
  final PosQuantity? batchConsumption;
  final int? stock;
  final bool isAvailable;
  final PosUnavailableReason? unavailableReason;

  /// P6-15: variant tugagan bo'lsa uning "keyingi partiya"si.
  final PosNextBatch? nextBatch;

  /// Miqdor qadami: dona — 1; kg va l — `quantity_step` yoki 0.1 (docs/MENU.md).
  PosQuantity get step => unit.isMeasured
      ? (quantityStep ?? const PosQuantity.milli(100))
      : PosQuantity.one;

  /// Eng kam miqdor: `min_quantity` yoki bitta qadam.
  PosQuantity get minimum =>
      unit.isMeasured ? (minQuantity ?? step) : PosQuantity.one;

  /// [q] shu variant uchun to'g'rimi (kamida [minimum], [step] karrali, dona — butun).
  bool accepts(PosQuantity q) =>
      q >= minimum && q.isMultipleOf(step) && (unit.isMeasured || q.isWhole);
}

class PosModifierGroup {
  const PosModifierGroup({
    required this.id,
    required this.name,
    required this.modifiers,
    this.uuid,
    this.minSelect = 0,
    this.maxSelect,
    this.isRequired = false,
    this.sortOrder = 0,
  });

  factory PosModifierGroup.fromJson(PosJson j) => PosModifierGroup(
    id: j.integer('id'),
    uuid: j.strOrNull('uuid'),
    name: j.str('name'),
    minSelect: j.intOrNull('min_select') ?? 0,
    maxSelect: j.intOrNull('max_select'),
    isRequired: j.boolean('is_required'),
    sortOrder: j.intOrNull('sort_order') ?? 0,
    modifiers: j.objects('modifiers', PosModifier.fromJson),
  );

  final int id;
  final String? uuid;
  final String name;
  final int minSelect;

  /// `null` — cheksiz.
  final int? maxSelect;
  final bool isRequired;
  final int sortOrder;
  final List<PosModifier> modifiers;
}

class PosModifier {
  const PosModifier({
    required this.id,
    required this.uuid,
    required this.name,
    this.priceDelta = PosMoney.zero,
    this.maxQuantity = 1,
    this.isAvailable = true,
  });

  factory PosModifier.fromJson(PosJson j) => PosModifier(
    id: j.integer('id'),
    uuid: j.str('uuid'),
    name: j.str('name'),
    priceDelta: j.money('price_delta'),
    maxQuantity: j.intOrNull('max_quantity') ?? 1,
    isAvailable: j.boolean('is_available', fallback: true),
  );

  final int id;

  /// Buyurtmada shu uuid yuboriladi (`modifiers: [{id: uuid, quantity}]`).
  final String uuid;
  final String name;
  final PosMoney priceDelta;
  final int maxQuantity;
  final bool isAvailable;
}

/// Filialning stop-listi, tugagan variantlar va ochiq qozonlar.
class PosAvailability {
  const PosAvailability({
    this.stoppedProducts = const <int>{},
    this.stoppedVariants = const <int>{},
    this.stoppedModifiers = const <int>{},
    this.soldOutVariants = const <int>{},
    this.batches = const <PosBatch>[],
    this.stopReasons = const <int, String>{},
    this.nextBatches = const <PosPlannedBatch>[],
  });

  factory PosAvailability.fromJson(PosJson j) {
    final PosJson stopped =
        j.objOrNull('stopped') ?? const PosJson(<String, Object?>{});
    final PosJson soldOut =
        j.objOrNull('sold_out') ?? const PosJson(<String, Object?>{});
    return PosAvailability(
      stoppedProducts: stopped.ints('products').toSet(),
      stoppedVariants: stopped.ints('variants').toSet(),
      stoppedModifiers: stopped.ints('modifiers').toSet(),
      soldOutVariants: soldOut.ints('variants').toSet(),
      batches: j.objects('batches', PosBatch.fromJson),
      stopReasons: _stopReasons(j),
      nextBatches: j.objects('next_batches', PosPlannedBatch.fromJson),
    );
  }

  /// `stop_list[]` (`{type: product, subject_id, reason}`) dan taom → sabab ("Bugungi menyuda yo'q" ...).
  static Map<int, String> _stopReasons(PosJson j) {
    final Map<int, String> out = <int, String>{};
    for (final Object? e in j.list('stop_list')) {
      if (e is! Map || e['type'] != 'product') continue;
      final Object? id = e['subject_id'];
      final Object? reason = e['reason'];
      if (id is num && reason is String && reason.isNotEmpty) {
        out[id.toInt()] = reason;
      }
    }
    return out;
  }

  final Set<int> stoppedProducts;
  final Set<int> stoppedVariants;
  final Set<int> stoppedModifiers;
  final Set<int> soldOutVariants;
  final List<PosBatch> batches;

  /// P6-15: taom id → stop-list sababi (menyuda yo'qligi ham shu yerda).
  final Map<int, String> stopReasons;

  /// P6-15: rejalashtirilgan partiyalar (eng yaqini birinchi).
  final List<PosPlannedBatch> nextBatches;

  /// Taomning eng yaqin rejalashtirilgan partiyasi.
  PosNextBatch? nextBatchFor(int productId) {
    for (final PosPlannedBatch b in nextBatches) {
      if (b.productId == productId) return b.next;
    }
    return null;
  }

  /// Variantning o'z partiyasi, bo'lmasa mahsulotning umumiy qozoni.
  PosBatch? batchFor({required int productId, int? variantId}) {
    PosBatch? productWide;
    for (final PosBatch b in batches) {
      if (b.productId != productId) continue;
      if (variantId != null && b.variantId == variantId) return b;
      if (b.variantId == null) productWide ??= b;
    }
    return productWide;
  }
}

/// Ochiq qozon yoki partiya (`menu_batches`).
class PosBatch {
  const PosBatch({
    required this.id,
    required this.productId,
    required this.quantityProduced,
    required this.quantityLeft,
    this.uuid,
    this.variantId,
    this.label,
    this.startedAt,
  });

  factory PosBatch.fromJson(PosJson j) => PosBatch(
    id: j.integer('id'),
    uuid: j.strOrNull('uuid'),
    productId: j.integer('product_id'),
    variantId: j.intOrNull('product_variant_id'),
    label: j.strOrNull('label'),
    quantityProduced: j.quantity('quantity_produced'),
    quantityLeft: j.quantity('quantity_left'),
    startedAt: j.dateOrNull('started_at'),
  );

  final int id;
  final String? uuid;
  final int productId;
  final int? variantId;
  final String? label;
  final PosQuantity quantityProduced;
  final PosQuantity quantityLeft;
  final DateTime? startedAt;

  bool get isEmpty => !quantityLeft.isPositive;
}
