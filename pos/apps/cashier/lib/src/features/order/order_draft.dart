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
    this.combo,
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

  /// P6-31: kombo tanlovi (tanlovlar, tarkib va farq); oddiy taom — `null`.
  ComboChoice? combo;

  String get name => product.variants.length > 1 && variant.name.isNotEmpty
      ? '${product.name} (${variant.name})'
      : product.name;

  /// Bitta porsiyaga qo'shimchalar va (kombo bo'lsa) tanlov farqlari — server ham farqni `modifiers_total` ga qo'shadi.
  PosMoney get modifiersPrice =>
      modifierSelectionPrice(modifierSpecs(product), modifiers) +
      (combo?.delta ?? PosMoney.zero);

  /// P6-31: kombo tarkibi, narxsiz ("Ichimlik — 0,4 l Pepsi").
  List<String> get comboParts => combo?.labels ?? const <String>[];

  /// Taxminiy summa (aniq narxni server hisoblaydi).
  PosMoney get estimate =>
      (variant.price + modifiersPrice).timesQuantity(quantity);

  List<String> get modifierLabels => <String>[
    for (final PosModifierGroup g in product.modifierGroups)
      for (final PosModifier m in g.modifiers)
        if ((modifiers[m.uuid] ?? 0) > 0)
          modifiers[m.uuid]! > 1 ? '${modifiers[m.uuid]} × ${m.name}' : m.name,
  ];

  /// P6-32: oyna guruhlarining tanlovi (tez guruhlar qator tugmalarida ko'rinadi).
  List<String> get dialogModifierLabels => <String>[
    for (final PosModifierGroup g in product.modifierGroups)
      if (!g.isQuick)
        for (final PosModifier m in g.modifiers)
          if ((modifiers[m.uuid] ?? 0) > 0)
            modifiers[m.uuid]! > 1
                ? '${modifiers[m.uuid]} × ${m.name}'
                : m.name,
  ];

  /// Bir xil taom (qo'shimchasiz, izohsiz) — yangi qator o'rniga miqdor oshadi.
  bool canMergeWith(
    PosVariant v,
    Map<String, int> mods, {
    ComboChoice? combo,
  }) =>
      variant.uuid == v.uuid &&
      _sameSelection(modifiers, mods) &&
      ComboChoice.same(this.combo, combo) &&
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
    combo: combo?.picks,
  );
}

/// P6-31: kassada tanlangan kombo: tanlovlar (serverga `combo[]`) va ularning tarkibi va farqi (internetsiz ham server
/// bilan bir xil — [PosComboPricing]).
class ComboChoice {
  const ComboChoice(this.picks, this.resolution);

  final List<PosComboPick> picks;
  final PosComboResolution resolution;

  PosMoney get delta => resolution.delta;

  List<String> get labels => <String>[
    for (final PosComboPart p in resolution.parts) p.label,
  ];

  /// Bir xil tanlov (tartibsiz): savatda bitta qator bo'ladi.
  static bool same(ComboChoice? a, ComboChoice? b) {
    if (a == null || b == null) return a == null && b == null;
    Map<String, int> count(ComboChoice c) {
      final Map<String, int> m = <String, int>{};
      for (final PosComboPick p in c.picks) {
        final String k = p.optionUuid.toLowerCase();
        m[k] = (m[k] ?? 0) + p.quantity;
      }
      return m;
    }

    final Map<String, int> x = count(a);
    final Map<String, int> y = count(b);
    return x.length == y.length &&
        x.entries.every((MapEntry<String, int> e) => y[e.key] == e.value);
  }
}

/// Kombo tanlovi katalog bo'yicha sotiladimi (internetsiz rejimdagi qoida bilan bir xil: katalog belgisi, taom
/// stop-listda emas, oldindan qo'yilgan modifikator to'xtatilmagan).
bool comboOptionAvailable(PosCatalog cat, PosComboOption o) {
  if (!o.isAvailable) return false;
  final PosVariant? part = cat.variantByUuid(o.variantUuid);
  if (part != null && !cat.isVariantAvailable(part)) return false;
  return !o.modifiers.any(
    (PosComboModifier m) => cat.availability.stoppedModifiers.contains(m.id),
  );
}

/// Tanlovlarni tekshiradi va narxlaydi ([PosComboPricing.resolve]); noto'g'ri bo'lsa [PosComboRejected].
ComboChoice resolveCombo(
  PosCatalog cat,
  PosProduct product,
  List<PosComboPick> picks,
) => ComboChoice(
  picks,
  PosComboPricing.resolve(
    product.combo!,
    picks,
    comboName: product.name,
    available: (PosComboOption o) => comboOptionAvailable(cat, o),
  ),
);

/// P6-32: guruhning oldindan belgilanadigan variantlari (standart, mavjud, stop-listda emas).
List<PosModifier> preselectedOf(
  PosModifierGroup g, {
  PosAvailability? availability,
}) => <PosModifier>[
  for (final PosModifier m in g.preselected)
    if (!(availability?.stoppedModifiers.contains(m.id) ?? false)) m,
];

/// P6-32: taomning standart tanlovi (hamma guruhlarning standart variantlari) — kassa oldindan qo'yadi, buyurtma
/// ularni aniq yuboradi.
Map<String, int> defaultModifiers(
  PosProduct product, {
  PosAvailability? availability,
}) => <String, int>{
  for (final PosModifierGroup g in product.modifierGroups)
    for (final PosModifier m in preselectedOf(g, availability: availability))
      m.uuid: 1,
};

/// P6-32: [selected] bilan ham oyna kerakmi: majburiy oyna guruhi bor yoki biror majburiy guruh to'lmagan.
bool needsDialogWith(PosProduct p, Map<String, int> selected) =>
    p.modifierGroups.any((PosModifierGroup g) {
      final int min = g.minSelect > 0 ? g.minSelect : (g.isRequired ? 1 : 0);
      if (min == 0) return false;
      if (!g.isQuick) return true;
      final int picked = g.modifiers
          .where((PosModifier m) => (selected[m.uuid] ?? 0) > 0)
          .length;
      return picked < min;
    });

/// P6-32: tez tanlanadigan guruhlar (hajm, tugma yonidagi variantlar), tartibi bilan.
List<PosModifierGroup> quickGroupsOf(PosProduct product) =>
    List<PosModifierGroup>.of(
      product.modifierGroups.where((PosModifierGroup g) => g.isQuick),
    )..sort(
      (PosModifierGroup a, PosModifierGroup b) =>
          a.sortOrder.compareTo(b.sortOrder),
    );

/// P6-32: menyu tugmasidagi hajm guruhi (birinchi `size` guruhi), yo'q — `null`.
PosModifierGroup? sizeGroupOf(PosProduct product) =>
    quickGroupsOf(product)
        .where((PosModifierGroup g) => g.displayMode == PosModifierDisplay.size)
        .firstOrNull;

/// [group] da [option] tanlanganda yangi tanlov: hajm (yoki bitta variantli guruh) — almashtiradi, boshqasi —
/// qo'shadi yoki olib tashlaydi (guruhning ko'pi bilan soni ichida; majburiy guruhning oxirgisi olinmaydi).
Map<String, int> toggleQuickModifier(
  Map<String, int> current,
  PosModifierGroup group,
  PosModifier option,
) {
  final Map<String, int> next = Map<String, int>.of(current);
  final Set<String> inGroup = <String>{
    for (final PosModifier m in group.modifiers) m.uuid,
  };
  final List<String> picked = <String>[
    for (final String k in next.keys)
      if (inGroup.contains(k) && (next[k] ?? 0) > 0) k,
  ];
  final bool single =
      group.displayMode == PosModifierDisplay.size || group.maxSelect == 1;
  final int min = group.minSelect > 0
      ? group.minSelect
      : (group.isRequired ? 1 : 0);
  if ((next[option.uuid] ?? 0) > 0) {
    if (single || picked.length <= min) {
      return min > 0 ? current : (next..remove(option.uuid));
    }
    return next..remove(option.uuid);
  }
  if (single) {
    for (final String k in picked) {
      next.remove(k);
    }
  } else if (group.maxSelect != null && picked.length >= group.maxSelect!) {
    return current;
  }
  return next..[option.uuid] = 1;
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

/// Taomni qo'shishda qo'shimchalar oynasi albatta kerakmi (majburiy guruh bor). P6-32: tez guruh (hajm, tugma yonidagi
/// variantlar) standart varianti bilan to'lsa — oynasiz.
bool needsModifierChoice(PosProduct p, {PosAvailability? availability}) =>
    p.modifierGroups.any((PosModifierGroup g) {
      final int min = g.minSelect > 0 ? g.minSelect : (g.isRequired ? 1 : 0);
      if (min == 0) return false;
      if (!g.isQuick) return true;
      return preselectedOf(g, availability: availability).length < min;
    });

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
