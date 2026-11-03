import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../board/board_view.dart';
import '../board/line_timer.dart';
import '../board/wait_level.dart';
import '../settings/kds_settings.dart';
import 'kds_type.dart';
import 'line_marks.dart';
import 'target_timer_label.dart';

/// P6-33: ixcham chipta (barista va ichimlik sexlari: ko'p mayda chipta bir ekranda). Raqam va kutish vaqti bir
/// qatorda, har taom bir qatorda (modifikatorlari shu qatorda, vergul bilan); qatorni bosish "tayyor"; vaqt maqsadi
/// (sariq/qizil) va kombo nomi kichik yozuvda. Bosqich/kurs sarlavhalari yo'q — ichimlik uchun kerak emas.
class CompactTicketCard extends StatelessWidget {
  const CompactTicketCard({
    super.key,
    required this.entry,
    required this.clock,
    required this.settings,
    required this.canManage,
    required this.isNew,
    required this.isLineBusy,
    required this.orderBusy,
    required this.onLineTap,
    required this.onBumpAll,
  });

  final BoardTicket entry;
  final ValueListenable<DateTime> clock;
  final KdsSettings settings;
  final bool canManage;
  final bool isNew;
  final bool Function(int lineId) isLineBusy;
  final bool orderBusy;
  final void Function(KitchenLine line) onLineTap;
  final VoidCallback onBumpAll;

  KitchenTicket get ticket => entry.ticket;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final List<KitchenLine> lines = ticket.lines;
    final bool anyBumpable = lines.any((KitchenLine l) => l.canBeReady);
    return ValueListenableBuilder<DateTime>(
      valueListenable: clock,
      builder: (BuildContext context, DateTime now, Widget? body) {
        final Duration waiting = entry.waiting(now);
        final WaitLevel level = entry.voided
            ? WaitLevel.normal
            : _worse(
                WaitLevel.of(waiting, settings),
                LineTimer.worstLevel(ticket, lines, now),
              );
        final (Color bg, Color fg, IconData icon) = entry.voided
            ? (c.danger, c.onDanger, Icons.block_rounded)
            : switch (level) {
                WaitLevel.normal => (
                  Color.alphaBlend(c.surface2, c.surface),
                  c.fg,
                  Icons.timer_outlined,
                ),
                WaitLevel.warn => (
                  c.warning,
                  c.onWarning,
                  Icons.hourglass_bottom_rounded,
                ),
                WaitLevel.late => (
                  c.danger,
                  c.onDanger,
                  Icons.warning_amber_rounded,
                ),
              };
        final String label = entry.voided
            ? VoidBadge.label
            : switch (level) {
                WaitLevel.normal => '',
                WaitLevel.warn => 'Shoshiling',
                WaitLevel.late => 'Kechikdi',
              };
        final String id = ticket.queueNumber != null
            ? '№${ticket.queueNumber}'
            : ticket.code;
        return Material(
          color: c.surface,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(RzRadius.card),
            side: BorderSide(
              color: entry.voided
                  ? c.danger
                  : switch (level) {
                      WaitLevel.normal => c.line,
                      WaitLevel.warn => c.warning,
                      WaitLevel.late => c.danger,
                    },
              width: level == WaitLevel.normal && !entry.voided ? 1 : 3,
            ),
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Semantics(
                container: true,
                label:
                    '$id, kutish ${formatDuration(waiting)}${label.isEmpty ? '' : ', $label'}',
                child: Container(
                  color: bg,
                  padding: const EdgeInsets.fromLTRB(10, 6, 10, 6),
                  child: Row(
                    children: <Widget>[
                      Flexible(
                        child: Text(
                          id,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: KdsType.line.copyWith(
                            color: fg,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      if (isNew) ...<Widget>[
                        const SizedBox(width: 6),
                        const NewBadge(),
                      ],
                      const Spacer(),
                      Icon(icon, color: fg, size: 20),
                      const SizedBox(width: 4),
                      Text(
                        formatDuration(waiting),
                        style: KdsType.line.copyWith(
                          color: fg,
                          fontFeatures: const <FontFeature>[
                            FontFeature.tabularFigures(),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              body!,
            ],
          ),
        );
      },
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          for (var i = 0; i < lines.length; i++) ...<Widget>[
            _CompactLine(
              ticket: ticket,
              clock: clock,
              line: lines[i],
              // Bir komboning qismlaridan faqat birinchisida kombo nomi
              showCombo:
                  lines[i].combo != null &&
                  (i == 0 ||
                      lines[i - 1].combo?.itemId != lines[i].combo!.itemId),
              busy: isLineBusy(lines[i].id),
              enabled: canManage && !orderBusy && !entry.voided,
              onTap: () => onLineTap(lines[i]),
            ),
            if (i != lines.length - 1)
              Divider(height: 1, indent: 10, endIndent: 10, color: c.line),
          ],
          if (canManage && !entry.voided && anyBumpable && lines.length > 1)
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 6, 10, 8),
              child: RzButton(
                label: 'Hammasi tayyor',
                icon: Icons.done_all_rounded,
                variant: RzButtonVariant.success,
                fullWidth: true,
                loading: orderBusy,
                onPressed: onBumpAll,
              ),
            )
          else
            const SizedBox(height: 4),
        ],
      ),
    );
  }

  static WaitLevel _worse(WaitLevel a, WaitLevel b) =>
      a.index >= b.index ? a : b;
}

class _CompactLine extends StatelessWidget {
  const _CompactLine({
    required this.ticket,
    required this.clock,
    required this.line,
    required this.showCombo,
    required this.busy,
    required this.enabled,
    required this.onTap,
  });

  final KitchenTicket ticket;
  final ValueListenable<DateTime> clock;
  final KitchenLine line;
  final bool showCombo;
  final bool busy;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final bool voided = line.isVoid;
    final bool done = line.isDone;
    final bool actionable =
        enabled &&
        !voided &&
        !line.isServed &&
        (!line.isHeld || line.dueAt != null);
    final Color textColor = voided || done ? c.fgMuted : c.fg;
    final TextDecoration? strike = voided || done
        ? TextDecoration.lineThrough
        : null;
    final String quantity = line.isPiece
        ? '${line.quantityLabel}×'
        : line.quantityLabel;
    final String mods = line.modifiers
        .map((KitchenModifier m) => m.label)
        .join(', ');
    final String? note = line.note?.trim();
    final String state = voided
        ? "o'chirilgan"
        : line.isServed
        ? 'berilgan'
        : done
        ? 'tayyor'
        : 'tayyor emas';
    return Semantics(
      button: actionable,
      label:
          '${line.title}, $quantity${mods.isEmpty ? '' : ', $mods'}, $state${line.combo != null ? ', ${line.combo!.label}' : ''}${actionable ? (line.isReady ? '. Bosib qaytaring' : '. Bosib tayyor deb belgilang') : ''}',
      excludeSemantics: true,
      child: InkWell(
        onTap: actionable && !busy ? onTap : null,
        child: Container(
          constraints: const BoxConstraints(minHeight: 48),
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          color: voided ? c.dangerSoft.withValues(alpha: 0.5) : null,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                quantity,
                style: KdsType.quantity.copyWith(
                  color: voided || done ? c.fgMuted : c.brandSoftFg,
                  decoration: strike,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    if (showCombo)
                      Text(
                        'Kombo: ${line.combo!.label}',
                        key: ValueKey<String>('combo-${line.combo!.itemId}'),
                        style: KdsType.meta.copyWith(
                          color: c.brandSoftFg,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    Text(
                      line.title,
                      style: KdsType.line.copyWith(
                        color: textColor,
                        decoration: strike,
                      ),
                    ),
                    if (mods.isNotEmpty)
                      Text(
                        mods,
                        style: KdsType.modifier.copyWith(
                          color: c.fgMuted,
                          decoration: strike,
                        ),
                      ),
                    if (note != null && note.isNotEmpty && !voided)
                      Text(
                        note,
                        style: KdsType.modifier.copyWith(
                          color: c.warningSoftFg,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    TargetTimerLabel(
                      ticket: ticket,
                      line: line,
                      clock: clock,
                      compact: true,
                    ),
                    if (voided)
                      const Padding(
                        padding: EdgeInsets.only(top: 2),
                        child: VoidBadge(),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 6),
              SizedBox.square(
                dimension: 28,
                child: busy
                    ? const Padding(
                        padding: EdgeInsets.all(4),
                        child: CircularProgressIndicator(strokeWidth: 3),
                      )
                    : Icon(
                        voided
                            ? Icons.block_rounded
                            : line.isServed
                            ? Icons.done_all_rounded
                            : done
                            ? Icons.check_circle_rounded
                            : Icons.radio_button_unchecked_rounded,
                        size: 28,
                        color: voided
                            ? c.danger
                            : done
                            ? c.success
                            : c.fgMuted,
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
