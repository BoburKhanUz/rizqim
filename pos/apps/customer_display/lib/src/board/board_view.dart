import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../controller/display_controller.dart';
import '../widgets/auto_scroller.dart';
import '../widgets/display_type.dart';

/// "Tayyorlanmoqda / Tayyor" raqamlar taxtasi (televizor yoki planshet): chapda pishayotgan, o'ngda tayyor raqamlar,
/// yirik raqamlar. Yangi tayyor bo'lgan (yoki qayta chaqirilgan) raqam tepada "17 raqam tayyor!" bilan va ajratilgan holda
/// turadi. Sig'maydigan ro'yxat o'zi sekin siljiydi.
class BoardView extends StatelessWidget {
  const BoardView({super.key, required this.controller});

  final DisplayController controller;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosQueueBoard board =
        controller.board ?? const PosQueueBoard(businessDate: '');
    final List<ReadyAnnouncement> highlighted = controller.highlighted;
    final Set<int> hot = <int>{for (final ReadyAnnouncement a in highlighted) a.number};
    final ReadyAnnouncement? latest = controller.latestAnnouncement;

    // Tayyor: eng yangisi birinchi (mijoz hozir chaqirilganini ko'radi); tayyorlanmoqda: raqam bo'yicha
    final List<PosQueueEntry> ready = <PosQueueEntry>[...board.ready]
      ..sort((PosQueueEntry a, PosQueueEntry b) {
        final DateTime? ta = a.readyAt;
        final DateTime? tb = b.readyAt;
        if (ta == null && tb == null) return b.number.compareTo(a.number);
        if (ta == null) return 1;
        if (tb == null) return -1;
        return tb.compareTo(ta);
      });
    final List<PosQueueEntry> preparing = <PosQueueEntry>[...board.preparing]
      ..sort((PosQueueEntry a, PosQueueEntry b) => a.number.compareTo(b.number));

    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          _Header(
            title: controller.storeName ?? controller.branchName ?? 'Buyurtmalar',
            latest: latest,
          ),
          const SizedBox(height: 16),
          Expanded(
            child: LayoutBuilder(
              builder: (BuildContext context, BoxConstraints box) {
                final bool wide = box.maxWidth >= 720;
                final Widget left = _Panel(
                  key: const ValueKey<String>('panel-preparing'),
                  title: 'Tayyorlanmoqda',
                  icon: Icons.hourglass_top_rounded,
                  count: preparing.length,
                  background: c.surface,
                  foreground: c.fg,
                  accent: c.warning,
                  numbers: <int>[for (final PosQueueEntry e in preparing) e.number],
                  hot: const <int>{},
                  digit: 52,
                  chip: const Size(128, 84),
                  emptyText: "Hozir tayyorlanayotgan buyurtma yo'q",
                );
                final Widget right = _Panel(
                  key: const ValueKey<String>('panel-ready'),
                  title: 'Tayyor',
                  icon: Icons.check_circle_rounded,
                  count: ready.length,
                  background: c.successSoft,
                  foreground: c.successSoftFg,
                  accent: c.success,
                  numbers: <int>[for (final PosQueueEntry e in ready) e.number],
                  hot: hot,
                  digit: 80,
                  chip: const Size(176, 112),
                  emptyText: 'Tayyor buyurtma hali yo\'q',
                );
                return wide
                    ? Row(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: <Widget>[
                          Expanded(flex: 4, child: left),
                          const SizedBox(width: 20),
                          Expanded(flex: 5, child: right),
                        ],
                      )
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: <Widget>[
                          Expanded(child: right),
                          const SizedBox(height: 16),
                          Expanded(child: left),
                        ],
                      );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.title, required this.latest});

  final String title;
  final ReadyAnnouncement? latest;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return SizedBox(
      height: 84,
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 250),
        child: latest == null
            ? Align(
                key: const ValueKey<String>('header-title'),
                alignment: Alignment.centerLeft,
                child: Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: DisplayType.greeting.copyWith(fontSize: 44, color: c.fg),
                ),
              )
            : DecoratedBox(
                key: ValueKey<String>('announce-${latest!.number}-${latest!.at.microsecondsSinceEpoch}'),
                decoration: BoxDecoration(
                  color: c.success,
                  borderRadius: BorderRadius.circular(RzRadius.sheet),
                ),
                child: Center(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 24),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Icon(Icons.campaign_rounded, color: c.onSuccess, size: 48),
                        const SizedBox(width: 16),
                        Flexible(
                          child: FittedBox(
                            fit: BoxFit.scaleDown,
                            child: Text(
                              latest!.again
                                  ? '${latest!.number} raqam, buyurtmangizni oling!'
                                  : '${latest!.number} raqam tayyor!',
                              maxLines: 1,
                              style: DisplayType.banner.copyWith(color: c.onSuccess),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
      ),
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({
    super.key,
    required this.title,
    required this.icon,
    required this.count,
    required this.background,
    required this.foreground,
    required this.accent,
    required this.numbers,
    required this.hot,
    required this.digit,
    required this.chip,
    required this.emptyText,
  });

  final String title;
  final IconData icon;
  final int count;
  final Color background;
  final Color foreground;
  final Color accent;
  final List<int> numbers;
  final Set<int> hot;
  final double digit;
  final Size chip;
  final String emptyText;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(RzRadius.sheet),
        border: Border.all(color: accent.withValues(alpha: 0.5), width: 2),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(24, 18, 24, 12),
            child: Row(
              children: <Widget>[
                Icon(icon, color: accent, size: 40),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    title,
                    style: DisplayType.heading.copyWith(fontSize: 36, color: foreground),
                  ),
                ),
                Text(
                  '$count',
                  style: DisplayType.heading.copyWith(color: foreground.withValues(alpha: 0.7)),
                ),
              ],
            ),
          ),
          Divider(height: 1, color: accent.withValues(alpha: 0.35)),
          Expanded(
            child: numbers.isEmpty
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Text(
                        emptyText,
                        textAlign: TextAlign.center,
                        style: DisplayType.label.copyWith(color: c.fgMuted),
                      ),
                    ),
                  )
                : LayoutBuilder(
                    builder: (BuildContext context, BoxConstraints box) {
                      // Katta ekranda (televizor) raqamlar kattalashadi: uzoqdan o'qiladi
                      final double scale = (box.maxWidth / 620).clamp(1.0, 2.1).toDouble();
                      return AutoScroller(
                        padding: const EdgeInsets.all(20),
                        child: Wrap(
                          spacing: 14 * scale,
                          runSpacing: 14 * scale,
                          children: <Widget>[
                            for (final int n in numbers)
                              _NumberChip(
                                number: n,
                                size: Size(chip.width * scale, chip.height * scale),
                                digit: digit * scale,
                                hot: hot.contains(n),
                                accent: accent,
                                foreground: foreground,
                                background: background,
                              ),
                          ],
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}

class _NumberChip extends StatelessWidget {
  const _NumberChip({
    required this.number,
    required this.size,
    required this.digit,
    required this.hot,
    required this.accent,
    required this.foreground,
    required this.background,
  });

  final int number;
  final Size size;
  final double digit;
  final bool hot;
  final Color accent;
  final Color foreground;
  final Color background;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Semantics(
      label: 'Raqam $number',
      child: AnimatedScale(
        scale: hot ? 1.12 : 1,
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeOutBack,
        child: AnimatedContainer(
          key: ValueKey<String>('number-$number'),
          duration: const Duration(milliseconds: 300),
          width: size.width,
          height: size.height,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: hot ? accent : c.surface,
            borderRadius: BorderRadius.circular(RzRadius.card),
            border: Border.all(color: accent, width: hot ? 4 : 2),
            boxShadow: hot
                ? <BoxShadow>[BoxShadow(color: accent.withValues(alpha: 0.5), blurRadius: 24)]
                : null,
          ),
          child: Text(
            '$number',
            maxLines: 1,
            style: DisplayType.number(digit).copyWith(
              color: hot ? c.onSuccess : foreground,
            ),
          ),
        ),
      ),
    );
  }
}
