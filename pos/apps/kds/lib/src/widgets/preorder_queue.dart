import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../board/board_view.dart';
import 'kds_type.dart';
import 'line_marks.dart';
import 'mangal_card.dart';

/// "1 soat 45 daq", "45 daq", "9:05" (10 daqiqadan kam), manfiy bo'lsa "5 daq o'tdi".
String formatCountdown(Duration d) {
  if (d.isNegative) {
    final Duration late = -d;
    return late.inMinutes < 1 ? "hozir" : "${_hm(late)} o'tdi";
  }
  if (d < const Duration(minutes: 10)) return formatDuration(d);
  return _hm(d);
}

String _hm(Duration d) {
  final int h = d.inHours;
  final int m = d.inMinutes % 60;
  if (h == 0) return '$m daq';
  return m == 0 ? '$h soat' : '$h soat $m daq';
}

/// "Oldindan buyurtmalar": ushlab turilgan, muddati bor qatorlar muddat bo'yicha (eng yaqini birinchi). Har birida
/// qolgan vaqt sanab turadi; muddatdan [QueuedLine.lead] oldin qator o'zi faol doskaga o'tadi.
class PreorderQueue extends StatelessWidget {
  const PreorderQueue({super.key, required this.items, required this.clock});

  final List<QueuedLine> items;
  final ValueListenable<DateTime> clock;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return const EmptyState(
        icon: Icons.event_available_outlined,
        title: "Oldindan buyurtma yo'q",
        message: "Muddati belgilangan, hali yuborilmagan taomlar shu yerda chiqadi va vaqti yaqinlashganda doskaga o'tadi.",
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.all(12),
      itemCount: items.length,
      separatorBuilder: (BuildContext context, int index) =>
          const SizedBox(height: 10),
      itemBuilder: (BuildContext context, int i) =>
          _QueuedTile(item: items[i], clock: clock),
    );
  }
}

class _QueuedTile extends StatelessWidget {
  const _QueuedTile({required this.item, required this.clock});

  final QueuedLine item;
  final ValueListenable<DateTime> clock;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final KitchenLine line = item.line;
    final String quantity = line.isPiece
        ? '${line.quantityLabel}×'
        : line.quantityLabel;
    final String? note = line.note?.trim();
    final DateTime onBoardAt = item.dueAt.subtract(item.lead);
    return Material(
      color: c.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(RzRadius.card),
        side: BorderSide(color: c.line),
      ),
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 84),
        child: IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Container(
                width: 168,
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 10,
                ),
                decoration: BoxDecoration(
                  color: Color.alphaBlend(c.surface2, c.surface),
                  borderRadius: const BorderRadius.horizontal(
                    left: Radius.circular(RzRadius.card),
                  ),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      formatTime(item.dueAt),
                      style: KdsType.code.copyWith(color: c.fg),
                    ),
                    ValueListenableBuilder<DateTime>(
                      valueListenable: clock,
                      builder: (BuildContext context, DateTime now, _) {
                        final Duration left = item.dueIn(now);
                        return Row(
                          children: <Widget>[
                            Icon(
                              Icons.timer_outlined,
                              size: 20,
                              color: left.isNegative ? c.danger : c.fgMuted,
                            ),
                            const SizedBox(width: 4),
                            Flexible(
                              child: Text(
                                formatCountdown(left),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: KdsType.meta.copyWith(
                                  color: left.isNegative ? c.danger : c.fgMuted,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ],
                        );
                      },
                    ),
                    Text(
                      'Doskada: ${formatTime(onBoardAt)}',
                      style: RzType.footnote.copyWith(color: c.fgMuted),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 10,
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: <Widget>[
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(
                            quantity,
                            style: KdsType.quantity.copyWith(
                              color: c.brandSoftFg,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              line.title,
                              style: KdsType.line.copyWith(color: c.fg),
                            ),
                          ),
                        ],
                      ),
                      for (final KitchenModifier m in line.modifiers)
                        Text(
                          '+ ${m.label}',
                          style: KdsType.modifier.copyWith(color: c.fgMuted),
                        ),
                      if (note != null && note.isNotEmpty)
                        Text(
                          note,
                          style: KdsType.modifier.copyWith(
                            color: c.warningSoftFg,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      const SizedBox(height: 4),
                      Wrap(
                        spacing: 10,
                        runSpacing: 4,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: <Widget>[
                          Text(
                            MangalCard.placeLabel(item.ticket),
                            style: KdsType.meta.copyWith(
                              color: c.fg,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          Text(
                            item.ticket.code,
                            style: KdsType.meta.copyWith(color: c.fgMuted),
                          ),
                          if (line.roundNo != null)
                            RoundTag(round: line.roundNo!),
                          if (item.station != null)
                            Text(
                              item.station!.name,
                              style: KdsType.meta.copyWith(color: c.fgMuted),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
