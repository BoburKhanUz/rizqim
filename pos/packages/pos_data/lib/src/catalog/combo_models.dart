import 'package:pos_core/pos_core.dart';

import '../json.dart';
import '../offline/offline_pricing.dart';

/// P6-31: kombo (`App\Domain\Pos\Menu\Combos`, docs/MENU.md "Kombo"): menyu mahsuloti (narxi, MXIK, kanal narxlari
/// oddiy taomdek), xaridor uning tarkibini tartiblangan slotlarda tanlaydi ("Asosiy", "Ichimlik", "Sous"). Katalogda
/// mahsulotning `combo` maydoni (kombo emas — `null`).
///
/// Bitta kombo narxi = kombo mahsulotining kanal narxi + Σ(tanlov farqi × soni) + slot ruxsat bersa xaridor tarkibga
/// QO'SHGAN modifikatorlar farqi (oldindan qo'yilgani tanlov narxiga kiradi). Qator: `unit_price` — kombo narxi,
/// `modifiers_total` — o'z modifikatorlari + tarkib farqlari, shuning uchun hamma eski formula o'zgarmay ishlaydi.
class PosCombo {
  const PosCombo({
    required this.id,
    required this.uuid,
    this.slots = const <PosComboSlot>[],
  });

  factory PosCombo.fromJson(PosJson j) => PosCombo(
    id: j.intOrNull('id') ?? 0,
    uuid: j.strOrNull('uuid') ?? '',
    slots: j.objects('slots', PosComboSlot.fromJson),
  );

  /// Katalogdagi `combo` (kombo emas — `null`).
  static PosCombo? maybe(Object? raw) => raw is Map
      ? PosCombo.fromJson(PosJson(raw.cast<String, Object?>()))
      : null;

  final int id;
  final String uuid;
  final List<PosComboSlot> slots;

  /// Uuid bo'yicha tanlov va uning sloti.
  ({PosComboSlot slot, PosComboOption option})? find(String optionUuid) {
    for (final PosComboSlot s in slots) {
      for (final PosComboOption o in s.options) {
        if (o.uuid == optionUuid.toLowerCase()) return (slot: s, option: o);
      }
    }
    return null;
  }

  /// Majburiy slotida sotiladigan tanlov qolmagan kombo yopiq (server buni stop-listga ham qo'yadi).
  bool isOpen({bool Function(PosComboOption option)? available}) {
    for (final PosComboSlot s in slots) {
      if (!s.isRequired) continue;
      final bool any = s.options.any(
        (PosComboOption o) => available?.call(o) ?? o.isAvailable,
      );
      if (!any) return false;
    }
    return true;
  }
}

class PosComboSlot {
  const PosComboSlot({
    required this.id,
    required this.uuid,
    required this.name,
    this.minPicks = 1,
    this.maxPicks = 1,
    this.allowModifiers = false,
    this.options = const <PosComboOption>[],
  });

  factory PosComboSlot.fromJson(PosJson j) => PosComboSlot(
    id: j.intOrNull('id') ?? 0,
    uuid: j.strOrNull('uuid') ?? '',
    name: j.str('name'),
    minPicks: j.intOrNull('min_picks') ?? 1,
    maxPicks: j.intOrNull('max_picks') ?? 1,
    allowModifiers: j.boolean('allow_modifiers'),
    options: j.objects('options', PosComboOption.fromJson),
  );

  final int id;
  final String uuid;
  final String name;

  /// 0 — ixtiyoriy slot.
  final int minPicks;
  final int maxPicks;

  /// Xaridor tarkibning o'z modifikatorlarini o'zgartira oladi (qo'shilganlar narxi qo'shiladi).
  final bool allowModifiers;
  final List<PosComboOption> options;

  bool get isRequired => minPicks > 0;

  /// Standart tanlov (bir bosishda kombo).
  PosComboOption? get defaultOption {
    for (final PosComboOption o in options) {
      if (o.isDefault) return o;
    }
    return null;
  }
}

class PosComboOption {
  const PosComboOption({
    required this.id,
    required this.uuid,
    required this.variantId,
    required this.variantUuid,
    required this.name,
    this.productId,
    this.variantName,
    this.quantity = 1,
    this.priceDelta = PosMoney.zero,
    this.modifiers = const <PosComboModifier>[],
    this.isDefault = false,
    this.label,
    this.receiptLabel = '',
    this.isAvailable = true,
  });

  factory PosComboOption.fromJson(PosJson j) => PosComboOption(
    id: j.intOrNull('id') ?? 0,
    uuid: (j.strOrNull('uuid') ?? '').toLowerCase(),
    variantId: j.intOrNull('variant_id') ?? 0,
    variantUuid: (j.strOrNull('variant_uuid') ?? '').toLowerCase(),
    productId: j.intOrNull('product_id'),
    name: j.str('name'),
    variantName: j.strOrNull('variant_name'),
    quantity: j.intOrNull('quantity') ?? 1,
    priceDelta: j.money('price_delta'),
    modifiers: j.objects('modifiers', PosComboModifier.fromJson),
    isDefault: j.boolean('is_default'),
    label: j.strOrNull('label'),
    receiptLabel: j.strOrNull('receipt_label') ?? '',
    isAvailable: j.boolean('is_available', fallback: true),
  );

  final int id;
  final String uuid;
  final int variantId;
  final String variantUuid;
  final int? productId;
  final String name;
  final String? variantName;

  /// Bitta komboda nechta dona.
  final int quantity;
  final PosMoney priceDelta;

  /// Oldindan qo'yilgan modifikatorlar ("katta, mol go'shtli"), tanlov narxiga kiradi.
  final List<PosComboModifier> modifiers;
  final bool isDefault;
  final String? label;

  /// Chekdagi matn (narxsiz).
  final String receiptLabel;

  /// Katalog olingan paytda filialda sotiladimi (sotilmaydigani ko'rsatilmaydi).
  final bool isAvailable;
}

class PosComboModifier {
  const PosComboModifier({
    required this.uuid,
    required this.name,
    this.id = 0,
    this.groupId,
    this.groupName,
    this.quantity = 1,
    this.priceDelta = PosMoney.zero,
  });

  factory PosComboModifier.fromJson(PosJson j) => PosComboModifier(
    id: j.intOrNull('id') ?? 0,
    uuid: (j.strOrNull('uuid') ?? '').toLowerCase(),
    groupId: j.intOrNull('group_id'),
    groupName: j.strOrNull('group_name'),
    name: j.str('name'),
    quantity: j.intOrNull('quantity') ?? 1,
    priceDelta: j.money('price_delta'),
  );

  final int id;
  final String uuid;
  final int? groupId;
  final String? groupName;
  final String name;
  final int quantity;
  final PosMoney priceDelta;

  Map<String, Object?> toJson() => <String, Object?>{
    'id': id,
    'uuid': uuid,
    'group_id': groupId,
    'group_name': groupName,
    'name': name,
    'quantity': quantity,
    'price_delta': priceDelta.toApiString(),
  };
}

/// Kassa tanlovi (qatorning `combo[]` elementi): tanlov uuid'si, soni va (slot ruxsat bersa) tarkib modifikatorlari.
class PosComboPick {
  const PosComboPick(this.optionUuid, {this.quantity = 1, this.modifiers});

  final String optionUuid;
  final int quantity;

  /// Modifikator uuid → soni; `null` — tanlovning oldindan qo'yilgani.
  final Map<String, int>? modifiers;

  Map<String, Object?> toJson() => <String, Object?>{
    'option_id': optionUuid,
    if (quantity != 1) 'quantity': quantity,
    if (modifiers != null)
      'modifiers': <Map<String, Object?>>[
        for (final MapEntry<String, int> m in modifiers!.entries)
          <String, Object?>{'id': m.key, 'quantity': m.value},
      ],
  };
}

/// Sotilgan kombo tarkibi (`order_items.combo_parts`, sotuv paytidagi nusxa).
class PosComboPart {
  const PosComboPart({
    required this.slotName,
    required this.name,
    required this.label,
    this.optionUuid,
    this.variantId,
    this.variantUuid,
    this.productId,
    this.variantName,
    this.quantity = 1,
    this.modifiers = const <PosComboModifier>[],
    this.priceDelta = PosMoney.zero,
    this.listPrice = PosMoney.zero,
    this.itemId,
  });

  factory PosComboPart.fromJson(PosJson j) => PosComboPart(
    slotName: j.strOrNull('slot_name') ?? '',
    optionUuid: j.strOrNull('option_uuid'),
    variantId: j.intOrNull('variant_id'),
    variantUuid: j.strOrNull('variant_uuid'),
    productId: j.intOrNull('product_id'),
    name: j.strOrNull('name') ?? '',
    variantName: j.strOrNull('variant_name'),
    quantity: j.intOrNull('quantity') ?? 1,
    modifiers: j.objects('modifiers', PosComboModifier.fromJson),
    priceDelta: j.money('price_delta'),
    listPrice: j.money('list_price'),
    label: j.strOrNull('label') ?? '',
    itemId: j.intOrNull('item_id'),
  );

  /// `combo_parts` ro'yxati; `null` yoki bo'sh — kombo emas.
  static List<PosComboPart>? listOf(Object? raw) {
    if (raw is! List || raw.isEmpty) return null;
    return <PosComboPart>[
      for (final Object? e in raw)
        if (e is Map) PosComboPart.fromJson(PosJson(e.cast<String, Object?>())),
    ];
  }

  final String slotName;
  final String? optionUuid;
  final int? variantId;
  final String? variantUuid;
  final int? productId;
  final String name;
  final String? variantName;

  /// Bitta komboda nechta dona.
  final int quantity;
  final List<PosComboModifier> modifiers;

  /// Bu tarkib bitta kombo narxiga qo'shgani.
  final PosMoney priceDelta;

  /// Tarkibning o'z menyu narxi (split fiskal ulushining og'irligi).
  final PosMoney listPrice;

  /// Chek va talonchadagi matn, narxsiz ("Ichimlik — 0,4 l Pepsi").
  final String label;

  /// Oshxona qatori (server bergan; internetsiz qatorda `null`).
  final int? itemId;

  Map<String, Object?> toJson() => <String, Object?>{
    'slot_name': slotName,
    'option_uuid': optionUuid,
    'variant_id': variantId,
    'variant_uuid': variantUuid,
    'product_id': productId,
    'name': name,
    'variant_name': variantName,
    'quantity': quantity,
    'modifiers': <Map<String, Object?>>[
      for (final PosComboModifier m in modifiers) m.toJson(),
    ],
    'price_delta': priceDelta.toApiString(),
    'list_price': listPrice.toApiString(),
    'label': label,
    'item_id': itemId,
  };
}

/// Kombo tanlovini kassa rad etgani (server bilan bir xil sabab: `combo_option_invalid`, `combo_option_unavailable`,
/// `combo_slot_required`, `combo_slot_limit`).
class PosComboRejected implements Exception {
  const PosComboRejected(this.reason, this.message);

  final String reason;
  final String message;

  @override
  String toString() => message;
}

/// [PosComboPricing.resolve] natijasi: sotiladigan tarkib va bitta kombo narxiga qo'shiladigan farq.
class PosComboResolution {
  const PosComboResolution(this.parts, this.delta);

  final List<PosComboPart> parts;
  final PosMoney delta;
}

/// P6-31: kombo tanlovini internetsiz tekshirish va narxlash — backend `Combos::resolve` bilan bir xil qoida.
abstract final class PosComboPricing {
  /// [picks] ni [combo] bo'yicha tekshiradi: tanlov shu komboniki, (katalogga ko'ra) sotiladi, slotdagi soni min/max
  /// ichida (qoldirilgan majburiy slot — standart tanlov); tarkibni va farqni qaytaradi. [partPrice] — tarkib
  /// taomining o'z narxi (split ulushi uchun; bo'lmasa 0), [modifierPrice] — slot ruxsat bergan qo'shimcha
  /// modifikatorning narxi (katalogdan).
  static PosComboResolution resolve(
    PosCombo combo,
    List<PosComboPick> picks, {
    String comboName = 'Kombo',
    bool Function(PosComboOption option)? available,
    PosMoney Function(PosComboOption option)? partPrice,
    ({String name, PosMoney priceDelta, String? groupName})? Function(
      String modifierUuid,
    )?
    modifierInfo,
  }) {
    // Uuid → [soni, modifikatorlar], yuborilgan tartibda
    final Map<String, (int, Map<String, int>?)> chosen =
        <String, (int, Map<String, int>?)>{};
    for (final PosComboPick p in picks) {
      final String uuid = p.optionUuid.toLowerCase();
      if (p.quantity < 1 || p.quantity > 10) {
        throw PosComboRejected(
          'combo_option_invalid',
          "'$comboName': tanlov noto'g'ri.",
        );
      }
      final (int, Map<String, int>?)? had = chosen[uuid];
      chosen[uuid] = had == null
          ? (p.quantity, p.modifiers)
          : (had.$1 + p.quantity, had.$2);
    }

    final Map<int, List<(PosComboOption, int, Map<String, int>?)>> bySlot =
        <int, List<(PosComboOption, int, Map<String, int>?)>>{};
    for (final MapEntry<String, (int, Map<String, int>?)> e in chosen.entries) {
      final ({PosComboSlot slot, PosComboOption option})? found = combo.find(
        e.key,
      );
      if (found == null) {
        throw PosComboRejected(
          'combo_option_invalid',
          "'$comboName': tanlangan taom bu komboga tegishli emas. Menyuni yangilang.",
        );
      }
      if (!(available?.call(found.option) ?? found.option.isAvailable)) {
        throw PosComboRejected(
          'combo_option_unavailable',
          "'$comboName': '${found.option.name}' hozir mavjud emas.",
        );
      }
      (bySlot[found.slot.id] ??= <(PosComboOption, int, Map<String, int>?)>[])
          .add((found.option, e.value.$1, e.value.$2));
    }

    final List<PosComboPart> parts = <PosComboPart>[];
    PosMoney delta = PosMoney.zero;
    for (final PosComboSlot slot in combo.slots) {
      List<(PosComboOption, int, Map<String, int>?)> slotPicks =
          bySlot[slot.id] ?? <(PosComboOption, int, Map<String, int>?)>[];
      final int max = slot.maxPicks < 1 ? 1 : slot.maxPicks;
      if (slotPicks.isEmpty && slot.minPicks > 0) {
        final PosComboOption? def = slot.defaultOption;
        if (def != null && (available?.call(def) ?? def.isAvailable)) {
          slotPicks = <(PosComboOption, int, Map<String, int>?)>[
            (def, slot.minPicks < max ? slot.minPicks : max, null),
          ];
        }
      }
      final int count = slotPicks.fold<int>(
        0,
        (int sum, (PosComboOption, int, Map<String, int>?) p) => sum + p.$2,
      );
      if (count < slot.minPicks) {
        throw PosComboRejected(
          'combo_slot_required',
          slot.minPicks == 1
              ? "'$comboName': '${slot.name}' tanlovi majburiy."
              : "'$comboName': '${slot.name}' dan kamida ${slot.minPicks} ta tanlang.",
        );
      }
      if (count > max) {
        throw PosComboRejected(
          'combo_slot_limit',
          "'$comboName': '${slot.name}' dan ko'pi bilan $max ta tanlash mumkin.",
        );
      }

      for (final (PosComboOption option, int picks, Map<String, int>? mods)
          in slotPicks) {
        final int pieces = option.quantity * picks;
        List<PosComboModifier> snapshot = option.modifiers;
        PosMoney extra = PosMoney.zero;
        if (slot.allowModifiers && mods != null) {
          final Map<String, int> preset = <String, int>{
            for (final PosComboModifier m in option.modifiers)
              m.uuid: m.quantity,
          };
          snapshot = <PosComboModifier>[];
          for (final MapEntry<String, int> m in mods.entries) {
            final String uuid = m.key.toLowerCase();
            final ({String name, PosMoney priceDelta, String? groupName})?
            info = modifierInfo?.call(uuid);
            PosComboModifier? fromPreset;
            for (final PosComboModifier p in option.modifiers) {
              if (p.uuid == uuid) fromPreset = p;
            }
            final PosMoney price =
                info?.priceDelta ?? fromPreset?.priceDelta ?? PosMoney.zero;
            snapshot.add(
              PosComboModifier(
                uuid: uuid,
                id: fromPreset?.id ?? 0,
                name: info?.name ?? fromPreset?.name ?? "Qo'shimcha",
                groupName: info?.groupName ?? fromPreset?.groupName,
                quantity: m.value,
                priceDelta: price,
              ),
            );
            final int added = m.value - (preset[uuid] ?? 0);
            if (added > 0) extra = extra + price * added;
          }
        }
        final PosMoney partDelta = option.priceDelta * picks + extra * pieces;
        final PosMoney perPiece = PosMoney.sum(<PosMoney>[
          for (final PosComboModifier m in snapshot) m.priceDelta * m.quantity,
        ]);
        delta = delta + partDelta;
        parts.add(
          PosComboPart(
            slotName: slot.name,
            optionUuid: option.uuid,
            variantId: option.variantId,
            variantUuid: option.variantUuid,
            productId: option.productId,
            name: option.name,
            variantName: option.variantName,
            quantity: pieces,
            modifiers: snapshot,
            priceDelta: partDelta,
            listPrice:
                ((partPrice?.call(option) ?? PosMoney.zero) + perPiece) *
                pieces,
            label: label(
              slot.name,
              option.name,
              option.variantName,
              pieces,
              snapshot,
              option.label,
            ),
          ),
        );
      }
    }
    return PosComboResolution(parts, delta);
  }

  /// Bitta kombo qatorining jami: `(kombo narxi + o'z modifikatorlari + farq) × soni` (backend `lineTotal`).
  static PosMoney lineTotal(
    PosMoney comboPrice,
    PosMoney ownModifiers,
    PosComboResolution resolution,
    PosQuantity quantity,
  ) => OfflinePricing.lineTotal(
    comboPrice,
    quantity,
    ownModifiers + resolution.delta,
    PosUnit.piece,
  );

  /// Chekdagi matn (backend `Combos::partLabel`): egasining yorlig'i, bo'lmasa `<slot> — <taom> (<variant>), <modifikatorlar>`.
  static String label(
    String slot,
    String name,
    String? variant,
    int pieces,
    List<PosComboModifier> modifiers,
    String? ownLabel,
  ) {
    final String? own = ownLabel?.trim();
    if (own != null && own.isNotEmpty) {
      return pieces > 1 && !RegExp(r'^\d').hasMatch(own)
          ? '$pieces ta $own'
          : own;
    }
    var text =
        '${pieces > 1 ? '$pieces ta ' : ''}$name${variant != null && variant.isNotEmpty ? ' ($variant)' : ''}';
    if (modifiers.isNotEmpty) {
      text +=
          ', ${modifiers.map((PosComboModifier m) => m.quantity > 1 ? '${m.name} x${m.quantity}' : m.name).join(', ')}';
    }
    return slot.trim().isNotEmpty ? '${slot.trim()} — $text' : text;
  }
}
