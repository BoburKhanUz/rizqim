import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../board/line_timer.dart';
import '../board/wait_level.dart';
import 'kds_type.dart';

/// P6-33: qatorning vaqt maqsadiga nisbatan taymeri: "Maqsad 4:00 · 2:10 qoldi" (sariq: maqsadning 80 foizi o'tdi),
/// "Kechikdi +1:15" (qizil). Rang bilan birga ikonka va yozuv bor. Maqsadsiz, tayyor yoki yuborilmagan qatorda hech
/// narsa chizmaydi; [clock] bilan har soniyada yangilanadi.
class TargetTimerLabel extends StatelessWidget {
  const TargetTimerLabel({
    super.key,
    required this.ticket,
    required this.line,
    required this.clock,
    this.compact = false,
  });

  final KitchenTicket ticket;
  final KitchenLine line;
  final ValueListenable<DateTime> clock;

  /// Ixcham kartochkada kichikroq yozuv.
  final bool compact;

  /// Mangal qatori o'z taymeriga ega ([GrillTimerLabel]); ikkitasini birga chizmaymiz.
  static bool shown(KitchenLine line) =>
      line.targetSeconds != null &&
      !line.isDone &&
      !line.isVoid &&
      (line.waitMinutes ?? 0) <= 0;

  /// Rang tanlash: sariq/qizil/oddiy.
  static (Color, IconData) styleOf(RzColors c, WaitLevel level) =>
      switch (level) {
        WaitLevel.late => (c.dangerSoftFg, Icons.warning_amber_rounded),
        WaitLevel.warn => (c.warningSoftFg, Icons.hourglass_bottom_rounded),
        WaitLevel.normal => (c.fgMuted, Icons.timer_outlined),
      };

  @override
  Widget build(BuildContext context) {
    if (!shown(line)) return const SizedBox.shrink();
    final RzColors c = context.rz;
    return ValueListenableBuilder<DateTime>(
      valueListenable: clock,
      builder: (BuildContext context, DateTime now, Widget? _) {
        final LineTimer? timer = LineTimer.of(ticket, line, now);
        if (timer == null) return const SizedBox.shrink();
        final (Color color, IconData icon) = styleOf(c, timer.level);
        final TextStyle style = (compact ? KdsType.meta : KdsType.modifier)
            .copyWith(
              color: color,
              fontWeight: FontWeight.w700,
              fontFeatures: const <FontFeature>[FontFeature.tabularFigures()],
            );
        return Padding(
          key: ValueKey<String>('target-timer-${line.id}'),
          padding: const EdgeInsets.only(top: 2),
          child: Semantics(
            label: timer.text,
            excludeSemantics: true,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Icon(icon, size: compact ? 16 : 18, color: color),
                const SizedBox(width: 4),
                Flexible(child: Text(timer.text, style: style)),
              ],
            ),
          ),
        );
      },
    );
  }
}
