import 'package:flutter/material.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Qizil "O'CHIRILDI" belgisi (rang bilan birga yozuv va ikonka: faqat rangga tayanmaydi).
class VoidBadge extends StatelessWidget {
  const VoidBadge({super.key, this.large = false});

  final bool large;

  static const String label = "O'CHIRILDI";

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final TextStyle style = (large ? RzType.headline : RzType.footnote)
        .copyWith(color: c.onDanger, fontWeight: FontWeight.w800);
    // Tor joyda (mangal qatori) belgi kichrayadi, kesilmaydi
    return FittedBox(
      fit: BoxFit.scaleDown,
      alignment: Alignment.centerLeft,
      child: _badge(c, style),
    );
  }

  Widget _badge(RzColors c, TextStyle style) {
    return Container(
      padding: EdgeInsets.symmetric(horizontal: large ? 12 : 8, vertical: 3),
      decoration: BoxDecoration(
        color: c.danger,
        borderRadius: BorderRadius.circular(RzRadius.control),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(Icons.block_rounded, size: large ? 22 : 16, color: c.onDanger),
          const SizedBox(width: 4),
          Text(label, style: style),
        ],
      ),
    );
  }
}

/// "2-bosqich" belgisi.
class RoundTag extends StatelessWidget {
  const RoundTag({super.key, required this.round, this.onColor});

  final int round;

  /// Rangli fonda (sarlavha) yozuv rangi; `null` — oddiy fon.
  final Color? onColor;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final Color fg = onColor ?? c.brandSoftFg;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: onColor == null ? c.brandSoft : Colors.transparent,
        border: onColor == null ? null : Border.all(color: fg),
        borderRadius: BorderRadius.circular(RzRadius.control),
      ),
      child: Text(
        '$round-bosqich',
        style: RzType.footnote.copyWith(color: fg, fontWeight: FontWeight.w700),
      ),
    );
  }
}

/// `6` yoki `1.5`: ortiqcha ".0" siz.
String formatCount(num value) {
  if (value == value.roundToDouble()) return value.toInt().toString();
  final String s = value.toStringAsFixed(3);
  return s.replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '');
}

/// Ko'k "Yangi" belgisi: chipta yoki ochiq stolning bosqichi yaqinda qo'shilgan.
class NewBadge extends StatelessWidget {
  const NewBadge({super.key});

  static const String label = 'Yangi';

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: c.info,
        borderRadius: BorderRadius.circular(RzRadius.control),
      ),
      child: Text(
        label,
        style: RzType.footnote.copyWith(
          color: c.onInfo,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}
