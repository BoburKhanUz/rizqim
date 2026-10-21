import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../board/kitchen_board_controller.dart';
import 'kds_type.dart';

/// Doska pastidagi "Yaqinda tayyor" lentasi: oxirgi belgilangan taomlar, har biridan "Qaytarish".
class RecentStrip extends StatelessWidget {
  const RecentStrip({
    super.key,
    required this.items,
    required this.clock,
    required this.onRecall,
    required this.isBusy,
    this.canRecall = true,
  });

  final List<RecentBump> items;
  final ValueListenable<DateTime> clock;
  final void Function(RecentBump item) onRecall;
  final bool Function(int lineId) isBusy;
  final bool canRecall;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Material(
      color: c.surface,
      child: Container(
        decoration: BoxDecoration(
          border: Border(top: BorderSide(color: c.line)),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        height: 76,
        child: Row(
          children: <Widget>[
            Icon(Icons.history_rounded, color: c.fgMuted),
            const SizedBox(width: 8),
            Text(
              'Yaqinda tayyor',
              style: KdsType.meta.copyWith(
                color: c.fgMuted,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: items.length,
                separatorBuilder: (_, _) => const SizedBox(width: 8),
                itemBuilder: (BuildContext context, int i) {
                  final RecentBump item = items[i];
                  final bool busy = isBusy(item.lineId);
                  return Container(
                    padding: const EdgeInsets.only(left: 12, right: 4),
                    decoration: BoxDecoration(
                      color: Color.alphaBlend(c.successSoft, c.surface),
                      borderRadius: BorderRadius.circular(RzRadius.control),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Icon(
                          Icons.check_rounded,
                          color: c.successSoftFg,
                          size: 20,
                        ),
                        const SizedBox(width: 6),
                        ValueListenableBuilder<DateTime>(
                          valueListenable: clock,
                          builder: (BuildContext context, DateTime now, _) => Text(
                            '${item.code} · ${item.title} ${item.quantityLabel} · ${formatDuration(now.difference(item.at))}',
                            style: KdsType.meta.copyWith(
                              color: c.successSoftFg,
                            ),
                          ),
                        ),
                        if (canRecall) ...<Widget>[
                          const SizedBox(width: 4),
                          TextButton.icon(
                            onPressed: busy ? null : () => onRecall(item),
                            style: TextButton.styleFrom(
                              minimumSize: const Size(48, 48),
                              foregroundColor: c.successSoftFg,
                              textStyle: KdsType.meta.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            icon: const Icon(Icons.undo_rounded),
                            label: const Text('Qaytarish'),
                          ),
                        ],
                      ],
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
