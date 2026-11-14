import '../json.dart';

/// P6-55 / B9 (`ingredient_stoplist`): do'kon ingredienti (`GET /owner/ingredients`) — qaysi taomlar ishlatadi va
/// qaysi filiallarda to'xtatilgan. Retsept emas: miqdor yo'q.
class PosIngredient {
  const PosIngredient({
    required this.id,
    required this.name,
    this.uuid = '',
    this.unit,
    this.links = const <PosIngredientLink>[],
    this.stopped = const <PosIngredientStop>[],
    this.stoppedHere = false,
  });

  factory PosIngredient.fromJson(PosJson j) => PosIngredient(
    id: j.integer('id'),
    uuid: j.strOrNull('uuid') ?? '',
    name: j.strOrNull('name') ?? '',
    unit: j.strOrNull('unit'),
    links: j.objects('links', PosIngredientLink.fromJson),
    stopped: j.objects('stopped', PosIngredientStop.fromJson),
    stoppedHere: j.boolean('stopped_here'),
  );

  final int id;
  final String uuid;
  final String name;
  final String? unit;
  final List<PosIngredientLink> links;

  /// Hozir to'xtatilgan filiallar.
  final List<PosIngredientStop> stopped;

  /// So'ralgan filialda (`branch_id`) to'xtatilgan.
  final bool stoppedHere;

  /// To'xtatilsa yopiladigan taomlar soni (muhim bog'lanishlar).
  int get criticalCount =>
      links.where((PosIngredientLink l) => l.isCritical).length;
}

/// Ingredient ↔ taom bog'lanishi; [isCritical] — ingredient to'xtasa taom yopiladi.
class PosIngredientLink {
  const PosIngredientLink({
    required this.productId,
    required this.name,
    this.isCritical = true,
  });

  factory PosIngredientLink.fromJson(PosJson j) => PosIngredientLink(
    productId: j.integer('product_id'),
    name: j.strOrNull('name') ?? '',
    isCritical: j.boolean('is_critical', fallback: true),
  );

  final int productId;
  final String name;
  final bool isCritical;
}

/// Ingredientning bitta filialdagi to'xtatilishi (stop-list yozuvi).
class PosIngredientStop {
  const PosIngredientStop({
    required this.branchId,
    required this.entryId,
    this.reason,
    this.until,
  });

  factory PosIngredientStop.fromJson(PosJson j) => PosIngredientStop(
    branchId: j.integer('branch_id'),
    entryId: j.intOrNull('entry_id') ?? 0,
    reason: j.strOrNull('reason'),
    until: j.dateOrNull('until'),
  );

  final int branchId;
  final int entryId;
  final String? reason;
  final DateTime? until;
}

/// `POST /owner/ingredients/{id}/stop` natijasi: qaysi taomlar yopildi.
class PosIngredientStopResult {
  const PosIngredientStopResult({
    required this.entryId,
    required this.branchId,
    required this.ingredientId,
    this.reason,
    this.until,
    this.closedProductIds = const <int>[],
  });

  factory PosIngredientStopResult.fromJson(PosJson j) =>
      PosIngredientStopResult(
        entryId: j.integer('entry_id'),
        branchId: j.integer('branch_id'),
        ingredientId: j.integer('ingredient_id'),
        reason: j.strOrNull('reason'),
        until: j.dateOrNull('until'),
        closedProductIds: j.ints('closed_product_ids'),
      );

  final int entryId;
  final int branchId;
  final int ingredientId;
  final String? reason;
  final DateTime? until;
  final List<int> closedProductIds;
}
