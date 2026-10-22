import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'keypad.dart';

/// Kg va ulush klaviaturasi: tez tugmalar (0.5, 0.7, 0.75, 1, 2 — qozondan ulush va kg), kasr bilan raqam terish
/// (uchtagacha kasr raqam), birlik yorlig'i ("kg (guruch)"), qadam va chegaralar tekshiruvi.
class PosQuantityKeypad extends StatefulWidget {
  const PosQuantityKeypad({
    super.key,
    required this.onSubmit,
    this.unit = PosUnit.kg,
    this.unitLabel,
    this.initial,
    this.presets = PosQuantity.presets,
    this.step,
    this.min,
    this.max,
    this.submitLabel = "Qo'shish",
    this.keySize = 64,
    this.autofocus = false,
  });

  final ValueChanged<PosQuantity> onSubmit;
  final PosUnit unit;

  /// Variant yorlig'i ("kg (guruch)", "porsiya"); `null` — [PosUnit.label].
  final String? unitLabel;
  final PosQuantity? initial;
  final List<PosQuantity> presets;

  /// Variantning `quantity_step` (masalan 0.1 kg).
  final PosQuantity? step;

  /// Variantning `min_quantity`.
  final PosQuantity? min;
  final PosQuantity? max;
  final String submitLabel;
  final double keySize;
  final bool autofocus;

  @override
  State<PosQuantityKeypad> createState() => _PosQuantityKeypadState();
}

class _PosQuantityKeypadState extends State<PosQuantityKeypad> {
  late final PosKeypadController _input = PosKeypadController(
    initial: widget.initial?.format() ?? '',
    maxIntegerDigits: 4,
    maxDecimals: PosQuantity.scale,
  )..addListener(() => setState(() {}));

  @override
  void dispose() {
    _input.dispose();
    super.dispose();
  }

  String get _unit => (widget.unitLabel == null || widget.unitLabel!.isEmpty)
      ? widget.unit.label
      : widget.unitLabel!;

  /// Xato matni yoki `null` (to'g'ri miqdor).
  String? _error(PosQuantity? q) {
    if (q == null || q.isZero) return null;
    if (widget.min != null && q < widget.min!) {
      return 'Kamida ${widget.min!.format()} $_unit';
    }
    if (widget.max != null && q > widget.max!) {
      return "Ko'pi bilan ${widget.max!.format()} $_unit";
    }
    if (widget.step != null && !q.isMultipleOf(widget.step!)) {
      return 'Qadam: ${widget.step!.format()} $_unit';
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosQuantity? q = _input.quantity;
    final String? error = _error(q);
    final bool valid = q != null && q.isPositive && error == null;

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        PosValueDisplay(value: _input.text, suffix: _unit, error: error),
        const SizedBox(height: 12),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          alignment: WrapAlignment.center,
          children: <Widget>[
            for (final PosQuantity p in widget.presets)
              _PresetChip(
                label: p.format(),
                selected: q == p,
                onTap: () => _input.value = p.format(),
              ),
          ],
        ),
        const SizedBox(height: 12),
        Center(
          child: PosNumericKeypad(
            keySize: widget.keySize,
            autofocus: widget.autofocus,
            extraKey: PosKeypadExtraKey.decimal,
            onKey: _input.input,
            onBackspace: _input.backspace,
            onClear: _input.clear,
          ),
        ),
        const SizedBox(height: 12),
        RzButton(
          label: valid
              ? '${widget.submitLabel}: ${q.label(widget.unit, unitLabel: widget.unitLabel)}'
              : widget.submitLabel,
          size: RzButtonSize.lg,
          fullWidth: true,
          onPressed: valid ? () => widget.onSubmit(q) : null,
        ),
        if (!valid && q == null)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(
              'Miqdorni tanlang yoki tering',
              textAlign: TextAlign.center,
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
          ),
      ],
    );
  }
}

class _PresetChip extends StatelessWidget {
  const _PresetChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Semantics(
      button: true,
      selected: selected,
      label: label,
      excludeSemantics: true,
      child: SizedBox(
        height: RzTouch.large,
        child: FilledButton(
          onPressed: onTap,
          style: FilledButton.styleFrom(
            minimumSize: const Size(72, RzTouch.large),
            backgroundColor: selected
                ? c.brand
                : Color.alphaBlend(c.brandSoft, c.surface),
            foregroundColor: selected ? c.onBrand : c.brandSoftFg,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(RzRadius.card),
            ),
            textStyle: RzType.title,
          ),
          child: Text(label),
        ),
      ),
    );
  }
}

/// Miqdor stepper: [−] miqdor [+]. Eng kichigida "−" o'rniga o'chirish (agar [onRemove] berilgan bo'lsa).
class PosQuantityStepper extends StatelessWidget {
  const PosQuantityStepper({
    super.key,
    required this.value,
    required this.onChanged,
    this.unit = PosUnit.piece,
    this.unitLabel,
    this.step = PosQuantity.one,
    this.min = PosQuantity.one,
    this.max,
    this.onRemove,
    this.buttonSize = RzTouch.large,
  });

  final PosQuantity value;
  final ValueChanged<PosQuantity> onChanged;
  final PosUnit unit;
  final String? unitLabel;
  final PosQuantity step;
  final PosQuantity min;
  final PosQuantity? max;

  /// Eng kichik miqdorda "−" bosilsa (qatorni olib tashlash).
  final VoidCallback? onRemove;
  final double buttonSize;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosQuantity down = value - step;
    final PosQuantity up = value + step;
    final bool canDown = down >= min;
    final bool canUp = max == null || up <= max!;
    final bool removes = !canDown && onRemove != null;

    Widget button({
      required IconData icon,
      required String label,
      required VoidCallback? onPressed,
      bool danger = false,
    }) => Semantics(
      button: true,
      label: label,
      enabled: onPressed != null,
      excludeSemantics: true,
      child: SizedBox.square(
        dimension: buttonSize,
        child: IconButton.filledTonal(
          onPressed: onPressed,
          iconSize: 26,
          style: IconButton.styleFrom(
            minimumSize: Size.square(buttonSize),
            backgroundColor: danger
                ? Color.alphaBlend(c.dangerSoft, c.surface)
                : Color.alphaBlend(c.fg.withValues(alpha: 0.08), c.surface),
            foregroundColor: danger ? c.dangerSoftFg : c.fg,
            disabledBackgroundColor: c.fgSubtle.withValues(alpha: 0.12),
            disabledForegroundColor: c.fgSubtle,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(RzRadius.control),
            ),
          ),
          icon: Icon(icon),
        ),
      ),
    );

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        button(
          icon: removes ? Icons.delete_outline : Icons.remove,
          label: removes ? 'Olib tashlash' : 'Kamaytirish',
          danger: removes,
          onPressed: canDown
              ? () => onChanged(down)
              : (removes ? onRemove : null),
        ),
        ConstrainedBox(
          constraints: const BoxConstraints(minWidth: 72),
          child: Semantics(
            liveRegion: true,
            label: 'Miqdor: ${value.label(unit, unitLabel: unitLabel)}',
            excludeSemantics: true,
            child: Text(
              value.label(unit, unitLabel: unitLabel),
              textAlign: TextAlign.center,
              style: RzType.title.copyWith(color: c.fg),
            ),
          ),
        ),
        button(
          icon: Icons.add,
          label: "Ko'paytirish",
          onPressed: canUp ? () => onChanged(up) : null,
        ),
      ],
    );
  }
}
