import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/pos_scope.dart';
import 'order_draft.dart';

/// Kombo oynasining natijasi: tanlov va soni.
class ComboSheetResult {
  const ComboSheetResult(this.choice, this.quantity);

  final ComboChoice choice;
  final int quantity;
}

/// P6-31 kombo oynasi (docs/POS_SPEC.md "Kombo"): har slot bir qator katta tugma — standart tanlov belgilangan, slotda
/// bitta bosish tanlovni almashtiradi, farq (+3 000) tugmada, sotilmaydigan tanlov ko'rsatilmaydi. Standart kombo —
/// 2 bosish (kombo tugmasi, "Qo'shish"). Narx va tarkib [PosComboPricing] bilan (internetsiz ham server bilan bir xil).
/// [initial] — savatdagi qatorni o'zgartirish (tanlovi va soni bilan); natija `null` — bekor qilindi.
Future<ComboSheetResult?> showComboSheet(
  BuildContext context, {
  required PosProduct product,
  required PosVariant variant,
  required PosCatalog catalog,
  ComboChoice? initial,
  int quantity = 1,
}) {
  final PosScope? scope = PosScope.maybeOf(context);
  return showModalBottomSheet<ComboSheetResult>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    constraints: const BoxConstraints(maxWidth: 720),
    builder: (BuildContext sheet) {
      final Widget body = ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(sheet).height * 0.9,
        ),
        child: ComboSheet(
          product: product,
          variant: variant,
          catalog: catalog,
          initial: initial,
          quantity: quantity,
        ),
      );
      return scope == null
          ? body
          : PosScope(
              services: scope.services,
              realtime: scope.realtime,
              navigate: scope.navigate,
              child: body,
            );
    },
  );
}

class ComboSheet extends StatefulWidget {
  const ComboSheet({
    super.key,
    required this.product,
    required this.variant,
    required this.catalog,
    this.initial,
    this.quantity = 1,
  });

  final PosProduct product;
  final PosVariant variant;
  final PosCatalog catalog;
  final ComboChoice? initial;
  final int quantity;

  @override
  State<ComboSheet> createState() => _ComboSheetState();
}

class _ComboSheetState extends State<ComboSheet> {
  /// Slot id → tanlangan tanlov uuid'lari (bittadan).
  late final Map<int, List<String>> _picked = _start();
  late int _quantity = widget.quantity < 1 ? 1 : widget.quantity;

  PosCombo get _combo => widget.product.combo!;

  List<PosComboOption> _options(PosComboSlot slot) => <PosComboOption>[
    for (final PosComboOption o in slot.options)
      if (comboOptionAvailable(widget.catalog, o)) o,
  ];

  Map<int, List<String>> _start() {
    final Map<int, List<String>> picked = <int, List<String>>{};
    final ComboChoice? initial = widget.initial;
    for (final PosComboSlot slot in _combo.slots) {
      picked[slot.id] = <String>[];
    }
    if (initial != null) {
      for (final PosComboPick p in initial.picks) {
        final ({PosComboSlot slot, PosComboOption option})? found = _combo.find(
          p.optionUuid,
        );
        if (found == null) continue;
        for (var i = 0; i < p.quantity; i++) {
          picked[found.slot.id]!.add(found.option.uuid);
        }
      }
      return picked;
    }
    // As the Mini App: each required slot's default (or its only option), nothing in the optional ones
    for (final PosComboSlot slot in _combo.slots) {
      if (!slot.isRequired) continue;
      final List<PosComboOption> options = _options(slot);
      final PosComboOption? def =
          options.where((PosComboOption o) => o.isDefault).firstOrNull ??
          (options.length == 1 ? options.single : null);
      if (def != null) picked[slot.id]!.add(def.uuid);
    }
    return picked;
  }

  void _tap(PosComboSlot slot, PosComboOption o) {
    final List<String> picked = _picked[slot.id]!;
    final int max = slot.maxPicks < 1 ? 1 : slot.maxPicks;
    setState(() {
      if (max == 1) {
        final bool again = picked.contains(o.uuid);
        picked.clear();
        // An optional single slot: a second tap takes the pick off
        if (!again || slot.isRequired) picked.add(o.uuid);
      } else if (picked.contains(o.uuid)) {
        picked.remove(o.uuid);
      } else if (picked.length < max) {
        picked.add(o.uuid);
      }
    });
  }

  List<PosComboPick> get _picks {
    final List<PosComboPick> picks = <PosComboPick>[];
    for (final PosComboSlot slot in _combo.slots) {
      final Map<String, int> count = <String, int>{};
      for (final String uuid in _picked[slot.id]!) {
        count[uuid] = (count[uuid] ?? 0) + 1;
      }
      for (final MapEntry<String, int> e in count.entries) {
        picks.add(PosComboPick(e.key, quantity: e.value));
      }
    }
    return picks;
  }

  /// Nima yetmaydi (oddiy so'z bilan); bo'lmasa `null`.
  String? get _problem {
    for (final PosComboSlot slot in _combo.slots) {
      final int n = _picked[slot.id]!.length;
      if (slot.isRequired && _options(slot).isEmpty) {
        return "«${slot.name}» hozir mavjud emas: kombo sotilmaydi.";
      }
      if (n < slot.minPicks) {
        return slot.minPicks == 1
            ? "«${slot.name}» ni tanlang."
            : "«${slot.name}» dan kamida ${slot.minPicks} ta tanlang.";
      }
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final String? problem = _problem;
    ComboChoice? choice;
    String? refused;
    if (problem == null) {
      try {
        choice = resolveCombo(widget.catalog, widget.product, _picks);
      } on PosComboRejected catch (e) {
        refused = e.message;
      }
    }
    final PosMoney one =
        widget.variant.price + (choice?.delta ?? PosMoney.zero);
    final PosMoney total = one * _quantity;
    final String? warning = problem ?? refused;
    return SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    widget.product.name,
                    style: RzType.title.copyWith(color: c.fg),
                  ),
                ),
                Text(
                  one.format(),
                  key: const ValueKey<String>('combo-price'),
                  style: RzType.headline.copyWith(color: c.fg),
                ),
              ],
            ),
          ),
          Flexible(
            child: ListView(
              shrinkWrap: true,
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
              children: <Widget>[
                for (final PosComboSlot slot in _combo.slots)
                  _slot(context, slot),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
            decoration: BoxDecoration(
              color: c.surface,
              border: Border(top: BorderSide(color: c.line)),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                if (warning != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Text(
                      warning,
                      key: const ValueKey<String>('combo-problem'),
                      style: RzType.footnote.copyWith(color: c.dangerSoftFg),
                    ),
                  ),
                Row(
                  children: <Widget>[
                    IconButton.outlined(
                      key: const ValueKey<String>('combo-minus'),
                      tooltip: 'Kamaytirish',
                      onPressed: _quantity > 1
                          ? () => setState(() => _quantity--)
                          : null,
                      icon: const Icon(Icons.remove),
                    ),
                    SizedBox(
                      width: 44,
                      child: Text(
                        '$_quantity',
                        key: const ValueKey<String>('combo-quantity'),
                        textAlign: TextAlign.center,
                        style: RzType.title.copyWith(color: c.fg),
                      ),
                    ),
                    IconButton.outlined(
                      key: const ValueKey<String>('combo-plus'),
                      tooltip: "Ko'paytirish",
                      onPressed: _quantity < 99
                          ? () => setState(() => _quantity++)
                          : null,
                      icon: const Icon(Icons.add),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: RzButton(
                        key: const ValueKey<String>('combo-add'),
                        label:
                            '${widget.initial == null ? "Qo'shish" : 'Saqlash'} · ${total.formatAmount()}',
                        icon: Icons.add_shopping_cart_rounded,
                        size: RzButtonSize.lg,
                        fullWidth: true,
                        onPressed: choice == null
                            ? null
                            : () => Navigator.of(context)
                                  .pop(ComboSheetResult(choice!, _quantity)),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _slot(BuildContext context, PosComboSlot slot) {
    final RzColors c = context.rz;
    final List<PosComboOption> options = _options(slot);
    final int max = slot.maxPicks < 1 ? 1 : slot.maxPicks;
    final String rule = options.isEmpty
        ? 'Hozir mavjud emas'
        : slot.isRequired
        ? (max == 1 ? 'Majburiy' : 'Majburiy, ${slot.minPicks}–$max ta')
        : (max == 1 ? 'Ixtiyoriy' : "Ixtiyoriy, ko'pi bilan $max ta");
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              Text(slot.name, style: RzType.headline.copyWith(color: c.fg)),
              const SizedBox(width: 8),
              Text(
                rule,
                style: RzType.footnote.copyWith(
                  color: options.isEmpty ? c.dangerSoftFg : c.fgMuted,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: <Widget>[
              for (final PosComboOption o in options)
                _ComboOptionButton(
                  key: ValueKey<String>('combo-option-${o.uuid}'),
                  option: o,
                  selected: _picked[slot.id]!.contains(o.uuid),
                  single: max == 1,
                  onTap: () => _tap(slot, o),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Bitta tanlov tugmasi: nomi, varianti yoki oldindan qo'yilgan modifikatorlari va farqi.
class _ComboOptionButton extends StatelessWidget {
  const _ComboOptionButton({
    super.key,
    required this.option,
    required this.selected,
    required this.single,
    required this.onTap,
  });

  final PosComboOption option;
  final bool selected;
  final bool single;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosMoney delta = option.priceDelta;
    final String detail = <String>[
      if ((option.variantName ?? '').isNotEmpty) option.variantName!,
      for (final PosComboModifier m in option.modifiers) m.name,
    ].join(', ');
    final String name =
        '${option.quantity > 1 ? '${option.quantity} × ' : ''}${option.name}';
    final String? price = delta.isZero
        ? null
        : '${delta.isNegative ? '−' : '+'}${delta.abs().formatAmount()}';
    return Semantics(
      button: true,
      selected: selected,
      inMutuallyExclusiveGroup: single,
      label: <String>[name, if (detail.isNotEmpty) detail, ?price].join(', '),
      excludeSemantics: true,
      child: Material(
        color: selected ? Color.alphaBlend(c.brandSoft, c.surface) : c.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(RzRadius.card),
          side: BorderSide(
            color: selected ? c.brand : c.line,
            width: selected ? 2 : 1,
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minWidth: 132, minHeight: 60),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 14, 8),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  Icon(
                    selected
                        ? (single
                              ? Icons.radio_button_checked
                              : Icons.check_box_rounded)
                        : (single
                              ? Icons.radio_button_unchecked
                              : Icons.check_box_outline_blank_rounded),
                    color: selected ? c.brand : c.fgSubtle,
                    size: 22,
                  ),
                  const SizedBox(width: 10),
                  Flexible(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: RzType.headline.copyWith(color: c.fg),
                        ),
                        if (detail.isNotEmpty)
                          Text(
                            detail,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: RzType.footnote.copyWith(color: c.fgMuted),
                          ),
                      ],
                    ),
                  ),
                  if (price != null) ...<Widget>[
                    const SizedBox(width: 10),
                    Text(
                      price,
                      style: RzType.headline.copyWith(
                        color: delta.isNegative ? c.successSoftFg : c.fg,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
