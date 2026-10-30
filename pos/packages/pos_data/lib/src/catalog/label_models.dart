import 'package:pos_core/pos_core.dart';

import '../bootstrap/bootstrap_models.dart';
import '../json.dart';

/// P6-32: filialning stiker va yorliq sozlamalari (`labels` bootstrap'da va
/// `GET /owner/branches/{id}/label-settings`). Chop etish kassada: `pos_core` shablonlari + `pos_hardware` TSPL drayveri.
class PosLabelSettings {
  const PosLabelSettings({
    this.size = LabelSize.mm40x30,
    this.allItems = true,
    this.categoryIds = const <int>{},
    this.perUnit = true,
    this.bagLabel = true,
    this.configured = false,
  });

  factory PosLabelSettings.fromJson(PosJson j) => PosLabelSettings(
    configured: j.boolean('configured'),
    size: LabelSize.fromWire(j.strOrNull('size')),
    allItems: (j.strOrNull('scope') ?? 'all') != 'categories',
    categoryIds: j.ints('category_ids').toSet(),
    perUnit: j.boolean('per_unit', fallback: true),
    bagLabel: j.boolean('bag_label', fallback: true),
  );

  /// Hech narsa saqlanmagan: 40x30, hamma taom, har dona uchun, paket yorlig'i bor.
  final bool configured;

  /// Yorliq qog'ozi o'lchami.
  final LabelSize size;

  /// `true` — hamma taomga stiker; `false` — faqat [categoryIds] kategoriyalariga.
  final bool allItems;
  final Set<int> categoryIds;

  /// Qatorda 3 ta stakan — 3 stiker (aks holda qatorga bitta).
  final bool perUnit;

  /// Olib ketish buyurtmasiga paket yorlig'i.
  final bool bagLabel;

  /// Shu kategoriyadagi taomga stiker chiqadimi (kategoriyasiz taom faqat "hamma taom" rejimida).
  bool appliesTo(int? categoryId) =>
      allItems || (categoryId != null && categoryIds.contains(categoryId));

  /// Qator uchun nechta stiker: [perUnit] bo'lsa dona soni (o'lchanadigan taom — bitta), aks holda bitta.
  int stickersFor(PosQuantity quantity) {
    if (!perUnit || !quantity.isWhole) return 1;
    final int count = quantity.wholePart;
    return count < 1 ? 1 : count;
  }
}

/// Bootstrap'ning `labels` qismi: konstruktorga tegmasdan [PosBootstrap.raw] dan o'qiladi.
extension PosBootstrapLabels on PosBootstrap {
  /// Filialning stiker sozlamalari; `labels` moduli yo'q yoki eski server bo'lsa — `null` (stiker chiqmaydi).
  PosLabelSettings? get labelSettings {
    final Object? block = raw['labels'];
    if (block is! Map) return null;
    try {
      return PosLabelSettings.fromJson(PosJson.of(block, 'labels'));
    } on FormatException {
      return null;
    }
  }
}
