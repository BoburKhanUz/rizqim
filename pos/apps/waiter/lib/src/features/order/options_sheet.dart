import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../common/sheet.dart';
import 'draft_cart.dart';

/// Taom tanlovi: variant, qo'shimchalar va izoh.
class ItemChoice {
  const ItemChoice({required this.variant, required this.modifiers, this.note});

  final PosVariant variant;
  final List<DraftModifier> modifiers;
  final String? note;
}

/// Taom oynasi: variant (bir nechta bo'lsa), qo'shimchalar guruhlari (majburiy / eng kam / eng ko'p) va izoh.
/// Tanlov qoidalarga to'g'ri kelmaguncha "Qo'shish" o'chiq.
Future<ItemChoice?> showOptionsSheet(
  BuildContext context, {
  required PosProduct product,
  required PosCatalog catalog,
  PosVariant? initialVariant,
}) => showWaiterSheet<ItemChoice>(
  context,
  fullHeight: true,
  builder: (BuildContext context) => OptionsSheet(
    product: product,
    catalog: catalog,
    initialVariant: initialVariant,
  ),
);

class OptionsSheet extends StatefulWidget {
  const OptionsSheet({
    super.key,
    required this.product,
    required this.catalog,
    this.initialVariant,
  });

  final PosProduct product;
  final PosCatalog catalog;
  final PosVariant? initialVariant;

  @override
  State<OptionsSheet> createState() => _OptionsSheetState();
}

class _OptionsSheetState extends State<OptionsSheet> {
  late PosVariant _variant = _firstAvailable();
  final Map<int, int> _chosen = <int, int>{};
  final TextEditingController _note = TextEditingController();

  PosVariant _firstAvailable() {
    final PosVariant? start = widget.initialVariant;
    if (start != null) return start;
    for (final PosVariant v in widget.product.variants) {
      if (widget.catalog.isVariantAvailable(v)) return v;
    }
    return widget.product.variants.first;
  }

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  List<PosModifierGroup> get _groups =>
      <PosModifierGroup>[...widget.product.modifierGroups]..sort(
        (PosModifierGroup a, PosModifierGroup b) =>
            a.sortOrder.compareTo(b.sortOrder),
      );

  int _min(PosModifierGroup g) =>
      g.isRequired ? (g.minSelect < 1 ? 1 : g.minSelect) : g.minSelect;

  int _count(PosModifierGroup g) => g.modifiers.fold(
    0,
    (int sum, PosModifier m) => sum + (_chosen[m.id] ?? 0),
  );

  /// Guruh qoidasi buzilgan bo'lsa matn, aks holda `null`.
  String? _problem(PosModifierGroup g) {
    final int n = _count(g);
    final int min = _min(g);
    if (n < min) {
      return min == 1
          ? 'Kamida bitta tanlang'
          : 'Kamida $min ta tanlang (hozir $n)';
    }
    if (g.maxSelect != null && n > g.maxSelect!) {
      return "Ko'pi bilan ${g.maxSelect} ta tanlash mumkin";
    }
    return null;
  }

  String _hint(PosModifierGroup g) {
    final int min = _min(g);
    final int? max = g.maxSelect;
    if (min == 1 && max == 1) return 'Majburiy: bittasini tanlang';
    if (max != null && max == min && min > 0) return 'Aynan $min ta';
    if (max != null && min > 0) return '$min tadan $max tagacha';
    if (min > 0) return min == 1 ? 'Majburiy' : 'Kamida $min ta';
    if (max != null) return "Ko'pi bilan $max ta";
    return 'Ixtiyoriy';
  }

  bool _modifierAvailable(PosModifier m) =>
      m.isAvailable &&
      !widget.catalog.availability.stoppedModifiers.contains(m.id);

  void _toggle(PosModifierGroup g, PosModifier m) {
    setState(() {
      final int now = _chosen[m.id] ?? 0;
      if (now > 0) {
        _chosen.remove(m.id);
        return;
      }
      if (g.maxSelect == 1) {
        for (final PosModifier other in g.modifiers) {
          _chosen.remove(other.id);
        }
      } else if (g.maxSelect != null && _count(g) >= g.maxSelect!) {
        return;
      }
      _chosen[m.id] = 1;
    });
  }

  void _setQuantity(PosModifierGroup g, PosModifier m, int q) {
    setState(() {
      if (q <= 0) {
        _chosen.remove(m.id);
        return;
      }
      final int others = _count(g) - (_chosen[m.id] ?? 0);
      if (g.maxSelect != null && others + q > g.maxSelect!) return;
      _chosen[m.id] = q.clamp(1, m.maxQuantity < 1 ? 1 : m.maxQuantity);
    });
  }

  bool get _valid => _groups.every((PosModifierGroup g) => _problem(g) == null);

  void _submit() {
    final List<DraftModifier> mods = <DraftModifier>[
      for (final PosModifierGroup g in _groups)
        for (final PosModifier m in g.modifiers)
          if ((_chosen[m.id] ?? 0) > 0) DraftModifier(m, g, _chosen[m.id]!),
    ];
    Navigator.of(context).pop(
      ItemChoice(variant: _variant, modifiers: mods, note: _note.text.trim()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosProduct p = widget.product;
    return SafeArea(
      child: Column(
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Align(
              alignment: Alignment.centerLeft,
              child: SheetHeader(title: p.name, subtitle: p.description),
            ),
          ),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
              children: <Widget>[
                if (p.variants.length > 1) ...<Widget>[
                  Text('Turi', style: RzType.headline.copyWith(color: c.fg)),
                  const SizedBox(height: 8),
                  for (final PosVariant v in p.variants)
                    _OptionRow(
                      label: v.name.isEmpty ? p.name : v.name,
                      trailing: v.unit.isMeasured
                          ? '${v.price.format()} / ${v.unitLabel ?? v.unit.label}'
                          : v.price.format(),
                      selected: v.uuid == _variant.uuid,
                      radio: true,
                      enabled: widget.catalog.isVariantAvailable(v),
                      disabledNote: widget.catalog
                          .unavailableReasonOf(v)
                          ?.label,
                      onTap: () => setState(() => _variant = v),
                    ),
                  const SizedBox(height: 12),
                ],
                for (final PosModifierGroup g in _groups) ...<Widget>[
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: Text(
                          g.name,
                          style: RzType.headline.copyWith(color: c.fg),
                        ),
                      ),
                      Text(
                        _hint(g),
                        style: RzType.footnote.copyWith(
                          color: _min(g) > 0 ? c.brandSoftFg : c.fgMuted,
                          fontWeight: _min(g) > 0 ? FontWeight.w600 : null,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  for (final PosModifier m in g.modifiers)
                    _OptionRow(
                      label: m.name,
                      trailing: m.priceDelta.isZero
                          ? null
                          : '${m.priceDelta.isNegative ? '' : '+'}${m.priceDelta.format()}',
                      selected: (_chosen[m.id] ?? 0) > 0,
                      radio: g.maxSelect == 1,
                      enabled: _modifierAvailable(m),
                      disabledNote: 'Tugadi',
                      quantity: m.maxQuantity > 1 ? _chosen[m.id] : null,
                      maxQuantity: m.maxQuantity,
                      onQuantity: (int q) => _setQuantity(g, m, q),
                      onTap: () => _toggle(g, m),
                    ),
                  if (_problem(g) != null && (_count(g) > 0 || _min(g) > 0))
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(
                        _problem(g)!,
                        style: RzType.footnote.copyWith(color: c.dangerSoftFg),
                      ),
                    ),
                  const SizedBox(height: 12),
                ],
                Text('Izoh', style: RzType.headline.copyWith(color: c.fg)),
                const SizedBox(height: 8),
                TextField(
                  controller: _note,
                  maxLength: 200,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    hintText: 'Masalan: piyozsiz',
                    border: OutlineInputBorder(),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
            child: RzButton(
              label: _variant.unit.isMeasured ? 'Davom etish' : "Qo'shish",
              size: RzButtonSize.lg,
              fullWidth: true,
              onPressed: _valid ? _submit : null,
            ),
          ),
        ],
      ),
    );
  }
}

/// Tanlash qatori (kamida 56 dp): doira (bitta tanlov) yoki katak (ko'p tanlov), nom, narx; ixtiyoriy son stepperi.
class _OptionRow extends StatelessWidget {
  const _OptionRow({
    required this.label,
    required this.selected,
    required this.radio,
    required this.onTap,
    this.trailing,
    this.enabled = true,
    this.disabledNote,
    this.quantity,
    this.maxQuantity = 1,
    this.onQuantity,
  });

  final String label;
  final String? trailing;
  final bool selected;
  final bool radio;
  final bool enabled;
  final String? disabledNote;
  final VoidCallback onTap;
  final int? quantity;
  final int maxQuantity;
  final ValueChanged<int>? onQuantity;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final Color fg = enabled ? c.fg : c.fgSubtle;
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Semantics(
        button: true,
        selected: selected,
        enabled: enabled,
        label: <String>[
          label,
          ?trailing,
          if (!enabled) disabledNote ?? '',
        ].join(', '),
        excludeSemantics: true,
        child: Material(
          color: selected
              ? Color.alphaBlend(c.brandSoft, c.surface)
              : Color.alphaBlend(c.surface2, c.surface),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(RzRadius.control),
            side: BorderSide(
              color: selected ? c.brand : Colors.transparent,
              width: 1.5,
            ),
          ),
          child: InkWell(
            borderRadius: BorderRadius.circular(RzRadius.control),
            onTap: enabled ? onTap : null,
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 56),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: Row(
                  children: <Widget>[
                    Icon(
                      radio
                          ? (selected
                                ? Icons.radio_button_checked
                                : Icons.radio_button_unchecked)
                          : (selected
                                ? Icons.check_box
                                : Icons.check_box_outline_blank),
                      color: selected ? c.brand : c.fgMuted,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        enabled ? label : '$label · ${disabledNote ?? ''}',
                        style: RzType.body.copyWith(color: fg),
                      ),
                    ),
                    if (trailing != null)
                      Text(
                        trailing!,
                        style: RzType.body.copyWith(color: c.fgMuted),
                      ),
                    if (selected &&
                        quantity != null &&
                        onQuantity != null) ...<Widget>[
                      const SizedBox(width: 8),
                      IconButton(
                        tooltip: 'Kamaytirish',
                        onPressed: () => onQuantity!(quantity! - 1),
                        icon: const Icon(Icons.remove_circle_outline),
                      ),
                      Text('$quantity', style: RzType.headline),
                      IconButton(
                        tooltip: "Ko'paytirish",
                        onPressed: quantity! < maxQuantity
                            ? () => onQuantity!(quantity! + 1)
                            : null,
                        icon: const Icon(Icons.add_circle_outline),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
