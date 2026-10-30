import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'order_draft.dart';

/// P6-32 tez modifikatorlar (`quick_modifiers`): savat qatoridagi tugmalar — hajm (`size`, bitta bosishda
/// almashtiriladi: 0,3 / 0,5 / 0,7) va tugma yonidagi variantlar (`inline`: "Muzsiz", "Shakarsiz"). Standart variant
/// oldindan belgilangan; buyurtma tanlovni baribir aniq yuboradi. `dialog` guruhlari — avvalgidek oynada.
class QuickModifierBar extends StatelessWidget {
  const QuickModifierBar({
    super.key,
    required this.product,
    required this.selected,
    required this.onChanged,
    this.availability,
    this.enabled = true,
    this.keyPrefix = 'quick',
  });

  final PosProduct product;
  final Map<String, int> selected;
  final ValueChanged<Map<String, int>> onChanged;
  final PosAvailability? availability;
  final bool enabled;

  /// Tugma kalitlari: `<prefix>-<modifier uuid>`.
  final String keyPrefix;

  @override
  Widget build(BuildContext context) {
    final List<PosModifierGroup> groups = quickGroupsOf(product);
    if (groups.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 6),
      child: Wrap(
        spacing: 6,
        runSpacing: 6,
        children: <Widget>[
          for (final PosModifierGroup g in groups)
            for (final PosModifier m in g.modifiers)
              if (m.isAvailable &&
                  !(availability?.stoppedModifiers.contains(m.id) ?? false))
                QuickChip(
                  key: ValueKey<String>('$keyPrefix-${m.uuid}'),
                  label: m.name,
                  price: m.priceDelta,
                  selected: (selected[m.uuid] ?? 0) > 0,
                  square: g.displayMode == PosModifierDisplay.size,
                  onTap: enabled
                      ? () => onChanged(toggleQuickModifier(selected, g, m))
                      : null,
                ),
        ],
      ),
    );
  }
}

/// Kichik tanlov tugmasi (kamida 44 pt balandlikda bosiladi): hajm — to'rtburchak, variant — yumaloq.
class QuickChip extends StatelessWidget {
  const QuickChip({
    super.key,
    required this.label,
    required this.selected,
    required this.onTap,
    this.price = PosMoney.zero,
    this.square = false,
    this.compact = false,
  });

  final String label;
  final bool selected;
  final VoidCallback? onTap;

  /// Farq (0 bo'lsa ko'rsatilmaydi).
  final PosMoney price;
  final bool square;

  /// Menyu tugmasi ichida: narxsiz, ixcham.
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final String text = compact || price.isZero
        ? label
        : '$label ${price.isNegative ? '−' : '+'}${price.abs().formatAmount()}';
    return Semantics(
      button: true,
      selected: selected,
      enabled: onTap != null,
      label: text,
      excludeSemantics: true,
      child: Material(
        color: selected ? c.brand : c.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(square ? RzRadius.control : 999),
          side: BorderSide(color: selected ? c.brand : c.line),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: ConstrainedBox(
            constraints: BoxConstraints(
              minWidth: compact ? 44 : 52,
              minHeight: compact ? 36 : 40,
            ),
            child: Padding(
              padding: EdgeInsets.symmetric(horizontal: compact ? 8 : 12),
              child: Center(
                widthFactor: 1,
                heightFactor: 1,
                child: Text(
                  text,
                  maxLines: 1,
                  style: (compact ? RzType.footnote : RzType.body).copyWith(
                    color: selected ? c.onBrand : c.fg,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
