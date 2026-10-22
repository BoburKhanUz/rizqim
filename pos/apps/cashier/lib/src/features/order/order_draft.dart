import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';

/// Yangi bosqichdagi (hali serverga yuborilmagan) qator.
class DraftLine {
  DraftLine({
    required this.key,
    required this.product,
    required this.variant,
    required this.quantity,
    this.modifiers = const <String, int>{},
    this.note,
    this.seatNo,
    this.fireMode,
  });

  /// Ekrandagi kalit (qurilmada).
  final int key;
  final PosProduct product;
  final PosVariant variant;
  PosQuantity quantity;

  /// Modifikator uuid → soni.
  Map<String, int> modifiers;
  String? note;
  int? seatNo;

  /// `null` — bosqichning rejimi ("hozir" yoki "ushlab turish").
  PosFireMode? fireMode;

  String get name => product.variants.length > 1 && variant.name.isNotEmpty
      ? '${product.name} (${variant.name})'
      : product.name;

  PosMoney get modifiersPrice =>
      modifierSelectionPrice(modifierSpecs(product), modifiers);

  /// Taxminiy summa (aniq narxni server hisoblaydi).
  PosMoney get estimate =>
      (variant.price + modifiersPrice).timesQuantity(quantity);

  List<String> get modifierLabels => <String>[
    for (final PosModifierGroup g in product.modifierGroups)
      for (final PosModifier m in g.modifiers)
        if ((modifiers[m.uuid] ?? 0) > 0)
          modifiers[m.uuid]! > 1 ? '${modifiers[m.uuid]} × ${m.name}' : m.name,
  ];

  /// Bir xil taom (qo'shimchasiz, izohsiz) — yangi qator o'rniga miqdor oshadi.
  bool canMergeWith(PosVariant v, Map<String, int> mods) =>
      variant.uuid == v.uuid &&
      _sameSelection(modifiers, mods) &&
      (note == null || note!.trim().isEmpty) &&
      seatNo == null &&
      fireMode == null;

  PosLineInput toInput() => PosLineInput(
    variantUuid: variant.uuid,
    quantity: quantity,
    modifiers: <PosModifierChoice>[
      for (final MapEntry<String, int> m in modifiers.entries)
        if (m.value > 0) PosModifierChoice(m.key, quantity: m.value),
    ],
    note: note,
    seatNo: seatNo,
    fireMode: fireMode,
  );
}

/// Menyu guruhlari → `pos_ui` qo'shimchalar oynasi modeli (stop-listdagi qo'shimcha tanlanmaydi).
List<PosModifierGroupSpec> modifierSpecs(
  PosProduct product, {
  PosAvailability? availability,
}) => <PosModifierGroupSpec>[
  for (final PosModifierGroup g
      in List<PosModifierGroup>.of(product.modifierGroups)..sort(
        (PosModifierGroup a, PosModifierGroup b) =>
            a.sortOrder.compareTo(b.sortOrder),
      ))
    PosModifierGroupSpec(
      id: '${g.id}',
      name: g.name,
      min: g.minSelect,
      max: g.maxSelect,
      required: g.isRequired,
      options: <PosModifierOptionSpec>[
        for (final PosModifier m in g.modifiers)
          PosModifierOptionSpec(
            id: m.uuid,
            name: m.name,
            priceDelta: m.priceDelta,
            maxQuantity: m.maxQuantity < 1 ? 1 : m.maxQuantity,
            available:
                m.isAvailable &&
                !(availability?.stoppedModifiers.contains(m.id) ?? false),
          ),
      ],
    ),
];

/// Taomni qo'shishda qo'shimchalar oynasi albatta kerakmi (majburiy guruh bor).
bool needsModifierChoice(PosProduct p) => p.modifierGroups.any(
  (PosModifierGroup g) => g.isRequired || g.minSelect > 0,
);

bool _sameSelection(Map<String, int> a, Map<String, int> b) {
  final Iterable<MapEntry<String, int>> ea = a.entries.where(
    (MapEntry<String, int> e) => e.value > 0,
  );
  final Iterable<MapEntry<String, int>> eb = b.entries.where(
    (MapEntry<String, int> e) => e.value > 0,
  );
  if (ea.length != eb.length) return false;
  return ea.every((MapEntry<String, int> e) => b[e.key] == e.value);
}
