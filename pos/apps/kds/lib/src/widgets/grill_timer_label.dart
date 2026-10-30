import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../board/grill_timer.dart';
import 'kds_type.dart';

/// Qatorning mangal taymeri: "Mangal 12 daq · 07:42 qoldi", muddat o'tsa "Mangal 12 daq · vaqti o'tdi +1:15".
/// Ikonka va yozuv bor (faqat rang emas); har soniyada [clock] bilan yangilanadi. Vaqt belgilanmagan yoki qator
/// tayyor bo'lsa hech narsa chizmaydi.
class GrillTimerLabel extends StatelessWidget {
  const GrillTimerLabel({
    super.key,
    required this.ticket,
    required this.line,
    required this.clock,
    this.large = false,
  });

  final KitchenTicket ticket;
  final KitchenLine line;
  final ValueListenable<DateTime> clock;

  /// Mangal kartochkasida katta yozuv.
  final bool large;

  /// Ekran o'quvchi va test uchun matn.
  static String textOf(GrillTimer timer) {
    final String target = 'Mangal ${timer.target.inMinutes} daq';
    return timer.overdue
        ? "$target · vaqti o'tdi +${formatDuration(-timer.remaining)}"
        : '$target · ${formatDuration(timer.remaining)} qoldi';
  }

  @override
  Widget build(BuildContext context) {
    if ((line.waitMinutes ?? 0) <= 0 || line.isDone || line.isVoid) {
      return const SizedBox.shrink();
    }
    final RzColors c = context.rz;
    return ValueListenableBuilder<DateTime>(
      valueListenable: clock,
      builder: (BuildContext context, DateTime now, Widget? _) {
        final GrillTimer? timer = GrillTimer.of(ticket, line, now);
        if (timer == null) return const SizedBox.shrink();
        final Color color = timer.overdue
            ? c.dangerSoftFg
            : timer.soon
            ? c.warningSoftFg
            : c.fgMuted;
        final IconData icon = timer.overdue
            ? Icons.warning_amber_rounded
            : Icons.local_fire_department_outlined;
        final TextStyle style = (large ? KdsType.line : KdsType.modifier)
            .copyWith(
              color: color,
              fontWeight: FontWeight.w700,
              fontFeatures: const <FontFeature>[FontFeature.tabularFigures()],
            );
        return Padding(
          padding: const EdgeInsets.only(top: 2),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(icon, size: large ? 26 : 18, color: color),
              const SizedBox(width: 4),
              Flexible(child: Text(textOf(timer), style: style)),
            ],
          ),
        );
      },
    );
  }
}
