import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Qo'shimcha (modifikator) varianti: menyudagi `modifiers[]` dan (ilova o'z modelidan o'giradi).
@immutable
class PosModifierOptionSpec {
  const PosModifierOptionSpec({
    required this.id,
    required this.name,
    this.priceDelta = PosMoney.zero,
    this.maxQuantity = 1,
    this.available = true,
  });

  /// Buyurtmaga yuboriladigan kalit (modifikator uuid'si).
  final String id;
  final String name;
  final PosMoney priceDelta;

  /// Bitta qo'shimchadan nechta olsa bo'ladi (1 — belgilash, ko'p — stepper).
  final int maxQuantity;

  /// Stop-listda bo'lsa `false` (tanlab bo'lmaydi).
  final bool available;
}

/// Qo'shimchalar guruhi: majburiy yoki ixtiyoriy, kamida [min], ko'pi bilan [max] ta (docs/MENU.md, 1-bo'lim).
@immutable
class PosModifierGroupSpec {
  const PosModifierGroupSpec({
    required this.id,
    required this.name,
    required this.options,
    this.min = 0,
    this.max,
    this.required = false,
  });

  final String id;
  final String name;
  final List<PosModifierOptionSpec> options;
  final int min;

  /// `null` — cheksiz.
  final int? max;
  final bool required;

  /// Haqiqiy eng kam son: majburiy guruhda kamida 1.
  int get minimum => required ? math.max(min, 1) : min;

  /// Bitta tanlov (radio): ko'pi bilan bitta va har varianti bittadan.
  bool get isSingleChoice =>
      max == 1 &&
      options.every((PosModifierOptionSpec o) => o.maxQuantity <= 1);

  /// "Majburiy · 1 ta tanlang", "Ixtiyoriy · ko'pi bilan 3 ta".
  String get ruleLabel {
    final int lo = minimum;
    final String kind = lo > 0 ? 'Majburiy' : 'Ixtiyoriy';
    final String rule = switch ((lo, max)) {
      (final int a, final int b?) when a == b => '$a ta tanlang',
      (final int a, final int b?) when a > 0 => '$a–$b ta tanlang',
      (0, final int b?) => "ko'pi bilan $b ta",
      (final int a, null) when a > 0 => 'kamida $a ta',
      _ => 'xohlagancha',
    };
    return '$kind · $rule';
  }

  /// Tanlangan soni.
  int countIn(Map<String, int> selection) => options.fold(
    0,
    (int sum, PosModifierOptionSpec o) => sum + (selection[o.id] ?? 0),
  );
}

/// Tanlov to'g'rimi: har guruh uchun xato matni (to'g'ri — bo'sh xarita).
Map<String, String> validateModifierSelection(
  List<PosModifierGroupSpec> groups,
  Map<String, int> selection,
) {
  final Map<String, String> errors = <String, String>{};
  for (final PosModifierGroupSpec g in groups) {
    final int n = g.countIn(selection);
    if (n < g.minimum) {
      errors[g.id] = '«${g.name}» dan kamida ${g.minimum} ta tanlang';
    } else if (g.max != null && n > g.max!) {
      errors[g.id] = "«${g.name}» dan ko'pi bilan ${g.max} ta";
    }
  }
  return errors;
}

/// Tanlangan qo'shimchalar narxi (bitta taom uchun).
PosMoney modifierSelectionPrice(
  List<PosModifierGroupSpec> groups,
  Map<String, int> selection,
) {
  var total = PosMoney.zero;
  for (final PosModifierGroupSpec g in groups) {
    for (final PosModifierOptionSpec o in g.options) {
      final int q = selection[o.id] ?? 0;
      if (q > 0) total += o.priceDelta * q;
    }
  }
  return total;
}

/// Qo'shimchalar tanlash paneli: guruhlar sarlavhasi va qoidasi bilan, katta tugmalar. Bitta tanlovli guruhda bosish
/// almashtiradi; ko'p tanlovlida belgilaydi; bir nechta bo'la oladigan variantda − n + stepper. Guruh chegarasidan
/// oshib bo'lmaydi.
class PosModifierPicker extends StatelessWidget {
  const PosModifierPicker({
    super.key,
    required this.groups,
    required this.selection,
    required this.onChanged,
    this.showErrors = false,
  });

  final List<PosModifierGroupSpec> groups;

  /// Variant id → soni.
  final Map<String, int> selection;
  final ValueChanged<Map<String, int>> onChanged;

  /// Majburiy guruh xatolarini ko'rsatish (tasdiqlash bosilgandan keyin).
  final bool showErrors;

  void _set(PosModifierGroupSpec g, PosModifierOptionSpec o, int qty) {
    final Map<String, int> next = Map<String, int>.of(selection);
    if (g.isSingleChoice) {
      for (final PosModifierOptionSpec other in g.options) {
        next.remove(other.id);
      }
    }
    if (qty <= 0) {
      next.remove(o.id);
    } else {
      next[o.id] = qty;
    }
    onChanged(next);
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final Map<String, String> errors = showErrors
        ? validateModifierSelection(groups, selection)
        : const <String, String>{};
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        for (final PosModifierGroupSpec g in groups) ...<Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  g.name,
                  style: RzType.headline.copyWith(color: c.fg),
                ),
              ),
              Text(
                g.ruleLabel,
                style: RzType.footnote.copyWith(
                  color: g.minimum > 0 ? c.warningSoftFg : c.fgMuted,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: <Widget>[
              for (final PosModifierOptionSpec o in g.options)
                _OptionButton(
                  option: o,
                  quantity: selection[o.id] ?? 0,
                  canAdd:
                      o.available &&
                      (g.isSingleChoice ||
                          g.max == null ||
                          g.countIn(selection) < g.max!),
                  onChanged: (int q) => _set(g, o, q),
                ),
            ],
          ),
          if (errors[g.id] != null)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                errors[g.id]!,
                style: RzType.footnote.copyWith(color: c.dangerSoftFg),
              ),
            ),
          const SizedBox(height: 16),
        ],
      ],
    );
  }
}

class _OptionButton extends StatelessWidget {
  const _OptionButton({
    required this.option,
    required this.quantity,
    required this.canAdd,
    required this.onChanged,
  });

  final PosModifierOptionSpec option;
  final int quantity;
  final bool canAdd;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final bool selected = quantity > 0;
    final String price = option.priceDelta.isZero
        ? ''
        : '+${option.priceDelta.format()}';
    final bool multi = option.maxQuantity > 1;
    final Widget label = Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          option.name,
          style: RzType.headline.copyWith(
            color: option.available ? c.fg : c.fgSubtle,
            decoration: option.available ? null : TextDecoration.lineThrough,
          ),
        ),
        if (price.isNotEmpty || !option.available)
          Text(
            option.available ? price : 'Tugadi',
            style: RzType.footnote.copyWith(
              color: option.available ? c.fgMuted : c.dangerSoftFg,
            ),
          ),
      ],
    );
    final ShapeBorder shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(RzRadius.card),
      side: BorderSide(
        color: selected ? c.brand : c.line,
        width: selected ? 2 : 1,
      ),
    );
    final Color bg = selected
        ? Color.alphaBlend(c.brandSoft, c.surface)
        : c.surface;

    if (!multi) {
      return Semantics(
        button: true,
        selected: selected,
        enabled: option.available,
        label: <String>[option.name, if (price.isNotEmpty) price].join(', '),
        excludeSemantics: true,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minWidth: 120, minHeight: 56),
          child: Material(
            color: bg,
            shape: shape,
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: !option.available || (!selected && !canAdd)
                  ? null
                  : () => onChanged(selected ? 0 : 1),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 8, 14, 8),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    Icon(
                      selected
                          ? Icons.check_circle
                          : Icons.radio_button_unchecked,
                      color: selected ? c.brand : c.fgSubtle,
                    ),
                    const SizedBox(width: 8),
                    label,
                  ],
                ),
              ),
            ),
          ),
        ),
      );
    }
    return Material(
      color: bg,
      shape: shape,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 4, 4, 4),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            label,
            const SizedBox(width: 8),
            IconButton(
              tooltip: 'Kamaytirish: ${option.name}',
              onPressed: selected ? () => onChanged(quantity - 1) : null,
              icon: const Icon(Icons.remove),
            ),
            Text('$quantity', style: RzType.title.copyWith(color: c.fg)),
            IconButton(
              tooltip: "Qo'shish: ${option.name}",
              onPressed:
                  option.available && canAdd && quantity < option.maxQuantity
                  ? () => onChanged(quantity + 1)
                  : null,
              icon: const Icon(Icons.add),
            ),
          ],
        ),
      ),
    );
  }
}

/// Qo'shimchalar oynasi: [groups] bo'yicha tanlash; majburiy guruhlar to'ldirilmaguncha tasdiqlab bo'lmaydi.
/// Natija — variant id → soni (yopilsa `null`).
Future<Map<String, int>?> showPosModifierDialog(
  BuildContext context, {
  required String title,
  required List<PosModifierGroupSpec> groups,
  Map<String, int> initial = const <String, int>{},
  PosMoney? basePrice,
  String confirmLabel = "Qo'shish",
}) => showDialog<Map<String, int>>(
  context: context,
  builder: (BuildContext context) => PosModifierDialog(
    title: title,
    groups: groups,
    initial: initial,
    basePrice: basePrice,
    confirmLabel: confirmLabel,
  ),
);

class PosModifierDialog extends StatefulWidget {
  const PosModifierDialog({
    super.key,
    required this.title,
    required this.groups,
    this.initial = const <String, int>{},
    this.basePrice,
    this.confirmLabel = "Qo'shish",
  });

  final String title;
  final List<PosModifierGroupSpec> groups;
  final Map<String, int> initial;

  /// Taom narxi (tasdiq tugmasida jami ko'rinadi).
  final PosMoney? basePrice;
  final String confirmLabel;

  @override
  State<PosModifierDialog> createState() => _PosModifierDialogState();
}

class _PosModifierDialogState extends State<PosModifierDialog> {
  late Map<String, int> _selection = Map<String, int>.of(widget.initial);
  bool _tried = false;

  @override
  Widget build(BuildContext context) {
    final bool valid = validateModifierSelection(
      widget.groups,
      _selection,
    ).isEmpty;
    final PosMoney extra = modifierSelectionPrice(widget.groups, _selection);
    final PosMoney? total = widget.basePrice == null
        ? null
        : widget.basePrice! + extra;
    return AlertDialog(
      title: Text(widget.title),
      content: SizedBox(
        width: 520,
        child: SingleChildScrollView(
          child: PosModifierPicker(
            groups: widget.groups,
            selection: _selection,
            showErrors: _tried,
            onChanged: (Map<String, int> next) =>
                setState(() => _selection = next),
          ),
        ),
      ),
      actions: <Widget>[
        RzButton(
          label: 'Bekor qilish',
          variant: RzButtonVariant.ghost,
          onPressed: () => Navigator.of(context).pop(),
        ),
        RzButton(
          label: total == null
              ? widget.confirmLabel
              : '${widget.confirmLabel} · ${total.format()}',
          size: RzButtonSize.lg,
          onPressed: () {
            if (!valid) {
              setState(() => _tried = true);
              return;
            }
            Navigator.of(context).pop(_selection);
          },
        ),
      ],
    );
  }
}
