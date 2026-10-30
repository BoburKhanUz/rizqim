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

/// "2-kurs" belgisi (P6-21): chiptada qator qaysi kursga tegishli. Rang bilan birga yozuv: faqat rangga tayanmaydi.
class CourseTag extends StatelessWidget {
  const CourseTag({super.key, required this.course, this.onColor});

  final int course;

  /// Rangli fonda yozuv rangi; `null` — oddiy fon.
  final Color? onColor;

  static String label(int course) => '$course-kurs';

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final Color fg = onColor ?? c.infoSoftFg;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: onColor == null ? c.infoSoft : Colors.transparent,
        border: onColor == null ? null : Border.all(color: fg),
        borderRadius: BorderRadius.circular(RzRadius.control),
      ),
      child: Text(
        label(course),
        style: RzType.footnote.copyWith(color: fg, fontWeight: FontWeight.w800),
      ),
    );
  }
}

/// "Og'irlik kutilmoqda" belgisi (P6-25): taom tortilmagan, miqdori taxminiy. Oshxona uni pishirishda davom etadi;
/// og'irlikni kassir yoki ofitsiant kiritadi, shuning uchun bu yerda faqat eslatma.
class WeightBadge extends StatelessWidget {
  const WeightBadge({super.key});

  static const String label = "Og'irlik kutilmoqda";

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return FittedBox(
      fit: BoxFit.scaleDown,
      alignment: Alignment.centerLeft,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(
          color: c.warningSoft,
          borderRadius: BorderRadius.circular(RzRadius.control),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Icon(Icons.scale_outlined, size: 16, color: c.warningSoftFg),
            const SizedBox(width: 4),
            Text(
              label,
              style: RzType.footnote.copyWith(
                color: c.warningSoftFg,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Stol tablichkasi (P6-25, kafe "stolga olib kelish"): kassada to'langan buyurtmani yuguruvchi shu stolga olib boradi.
/// Chiptaning rangli sarlavhasida turadi, shuning uchun [color] — sarlavha yozuvi rangi.
class TablePlate extends StatelessWidget {
  const TablePlate({super.key, required this.label, required this.color});

  /// Tablichka raqami yoki nomi ("12").
  final String label;
  final Color color;

  /// "12" -> "Stol 12"; raqam bo'lmasa (nom) o'zi.
  static String text(String label) {
    final String t = label.trim();
    return RegExp(r'^\d+$').hasMatch(t) ? 'Stol $t' : t;
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
      decoration: BoxDecoration(
        border: Border.all(color: color, width: 2),
        borderRadius: BorderRadius.circular(RzRadius.control),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(Icons.table_restaurant_outlined, size: 22, color: color),
          const SizedBox(width: 6),
          Text(
            text(label),
            maxLines: 1,
            style: RzType.headline.copyWith(
              color: color,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
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
