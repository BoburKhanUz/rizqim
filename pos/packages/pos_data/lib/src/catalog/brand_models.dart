import '../json.dart';

/// P6-55 (`multi_brand`): bitta oshxonadagi brend — katalogda (`brands[]`), taomda (`brand_id`) va buyurtma qatorida
/// (`brand`: sotilgan paytdagi nusxa). Chipta va yorliqda qisqa kod ([code]) va rang ([color]) ko'rsatiladi.
/// Brendsiz do'konda ro'yxat bo'sh, qatorda `null` (asosiy brend).
class PosBrand {
  const PosBrand({
    required this.id,
    required this.name,
    required this.code,
    this.color = '#64748B',
    this.uuid,
    this.logoUrl,
  });

  factory PosBrand.fromJson(PosJson j) => PosBrand(
    id: j.integer('id'),
    uuid: j.strOrNull('uuid'),
    name: j.strOrNull('name') ?? '',
    code: j.strOrNull('code') ?? '',
    color: j.strOrNull('color') ?? '#64748B',
    logoUrl: j.strOrNull('logo_url'),
  );

  /// Qatordagi `brand` (yo'q yoki noto'g'ri bo'lsa `null`).
  static PosBrand? maybe(Object? raw) => raw is Map
      ? PosBrand.fromJson(PosJson(raw.cast<String, Object?>()))
      : null;

  final int id;
  final String? uuid;
  final String name;

  /// Qisqa kod ("BB"): chipta va yorliqda.
  final String code;

  /// `#RRGGBB`.
  final String color;
  final String? logoUrl;

  /// Rang ARGB butun son sifatida (`Color(argb)`); noto'g'ri qiymatda kulrang.
  int get argb {
    final String hex = color.startsWith('#') ? color.substring(1) : color;
    final int? rgb = hex.length == 6 ? int.tryParse(hex, radix: 16) : null;
    return 0xFF000000 | (rgb ?? 0x64748B);
  }

  @override
  bool operator ==(Object other) =>
      other is PosBrand &&
      other.id == id &&
      other.code == code &&
      other.color == color &&
      other.name == name;

  @override
  int get hashCode => Object.hash(id, code, color, name);
}

/// P6-55 / B9: filialda to'xtatilgan ingredient (`availability.stopped_ingredients[]`): "Tovuq tugadi" — unga bog'langan
/// taomlar `stopped.products` da (sababi bilan) yopiq.
class PosStoppedIngredient {
  const PosStoppedIngredient({
    required this.entryId,
    required this.ingredientId,
    required this.name,
    this.reason,
    this.until,
  });

  factory PosStoppedIngredient.fromJson(PosJson j) => PosStoppedIngredient(
    entryId: j.intOrNull('id') ?? 0,
    ingredientId: j.integer('ingredient_id'),
    name: j.strOrNull('name') ?? '',
    reason: j.strOrNull('reason'),
    until: j.dateOrNull('until'),
  );

  final int entryId;
  final int ingredientId;
  final String name;
  final String? reason;

  /// O'zi ochiladigan payt (`null` — qo'lda ochilguncha).
  final DateTime? until;

  /// Ko'rsatiladigan sabab: o'zi yozilgani, bo'lmasa "Tovuq tugadi".
  String get label {
    final String? r = reason?.trim();
    if (r != null && r.isNotEmpty) return r;
    return name.trim().isEmpty ? 'Ingredient tugadi' : '${name.trim()} tugadi';
  }
}
