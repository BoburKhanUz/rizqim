import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../board/board_view.dart';
import '../board/skewer_groups.dart';
import '../board/wait_level.dart';
import '../settings/kds_settings.dart';
import 'grill_timer_label.dart';
import 'kds_type.dart';
import 'line_marks.dart';

/// Mangal chiptasi: xona/stol va six sonlari katta. Har taom bir qatorda: "Qo'y — 6 six". Qatorni bosish shu taomning
/// hamma donalarini "tayyor" qiladi (tayyorni bossangiz qaytariladi).
class MangalCard extends StatelessWidget {
  const MangalCard({
    super.key,
    required this.entry,
    required this.clock,
    required this.settings,
    required this.canManage,
    required this.isNew,
    required this.isLineBusy,
    required this.orderBusy,
    required this.onGroupTap,
    required this.onBumpAll,
  });

  final BoardTicket entry;
  final ValueListenable<DateTime> clock;
  final KdsSettings settings;
  final bool canManage;
  final bool isNew;
  final bool Function(int lineId) isLineBusy;
  final bool orderBusy;
  final void Function(SkewerGroup group) onGroupTap;
  final VoidCallback onBumpAll;

  static const TextStyle _place = TextStyle(
    fontSize: 34,
    height: 40 / 34,
    fontWeight: FontWeight.w800,
  );
  static const TextStyle _name = TextStyle(
    fontSize: 26,
    height: 32 / 26,
    fontWeight: FontWeight.w700,
  );
  static const TextStyle _count = TextStyle(
    fontSize: 40,
    height: 46 / 40,
    fontWeight: FontWeight.w800,
    fontFeatures: <FontFeature>[FontFeature.tabularFigures()],
  );

  KitchenTicket get ticket => entry.ticket;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final List<SkewerGroup> groups = groupSkewers(ticket.lines);
    final Set<int> rounds = <int>{
      for (final SkewerGroup g in groups)
        if (g.round != null && !g.voided) g.round!,
    };
    final num total = pendingSkewers(groups);
    final bool anyBumpable = groups.any(
      (SkewerGroup g) => g.bumpable.isNotEmpty,
    );
    final String? notes = ticket.notes?.trim();
    return ValueListenableBuilder<DateTime>(
      valueListenable: clock,
      builder: (BuildContext context, DateTime now, Widget? body) {
        final Duration waiting = entry.waiting(now);
        final WaitLevel level = entry.voided
            ? WaitLevel.normal
            : WaitLevel.of(waiting, settings);
        final (Color bg, Color fg, IconData icon, String? label) = entry.voided
            ? (c.danger, c.onDanger, Icons.block_rounded, VoidBadge.label)
            : switch (level) {
                WaitLevel.normal => (
                  Color.alphaBlend(c.surface2, c.surface),
                  c.fg,
                  Icons.timer_outlined,
                  null,
                ),
                WaitLevel.warn => (
                  c.warning,
                  c.onWarning,
                  Icons.hourglass_bottom_rounded,
                  'Shoshiling',
                ),
                WaitLevel.late => (
                  c.danger,
                  c.onDanger,
                  Icons.warning_amber_rounded,
                  'Kechikdi',
                ),
              };
        final Color border = entry.voided
            ? c.danger
            : switch (level) {
                WaitLevel.normal => c.line,
                WaitLevel.warn => c.warning,
                WaitLevel.late => c.danger,
              };
        return Semantics(
          container: true,
          label:
              'Mangal ${placeLabel(ticket)}, ${ticket.code}, kutish ${formatDuration(waiting)}${label != null ? ', $label' : ''}',
          child: Material(
            color: c.surface,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(RzRadius.card),
              side: BorderSide(
                color: border,
                width: level == WaitLevel.normal && !entry.voided ? 1 : 3,
              ),
            ),
            clipBehavior: Clip.antiAlias,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Container(
                  color: bg,
                  padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      Row(
                        children: <Widget>[
                          Expanded(
                            child: Text(
                              placeLabel(ticket),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: _place.copyWith(color: fg),
                            ),
                          ),
                          Icon(icon, color: fg, size: 26),
                          const SizedBox(width: 6),
                          Text(
                            formatDuration(waiting),
                            style: KdsType.timer.copyWith(color: fg),
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Wrap(
                        spacing: 8,
                        runSpacing: 4,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: <Widget>[
                          Text(
                            ticket.code,
                            style: KdsType.meta.copyWith(
                              color: fg,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          for (final int r in (rounds.toList()..sort()))
                            RoundTag(round: r, onColor: fg),
                          if (isNew) const NewBadge(),
                          if (total > 0)
                            Text(
                              'Jami: ${formatCount(total)} six',
                              style: KdsType.meta.copyWith(
                                color: fg,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          if (label != null)
                            Text(
                              label,
                              style: KdsType.meta.copyWith(
                                color: fg,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
                body!,
              ],
            ),
          ),
        );
      },
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          if (notes != null && notes.isNotEmpty)
            Container(
              margin: const EdgeInsets.fromLTRB(12, 12, 12, 0),
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: c.warningSoft,
                borderRadius: BorderRadius.circular(RzRadius.control),
              ),
              child: Text(
                notes,
                style: KdsType.modifier.copyWith(color: c.warningSoftFg),
              ),
            ),
          const SizedBox(height: 4),
          for (var i = 0; i < groups.length; i++) ...<Widget>[
            if (rounds.length > 1 &&
                (i == 0 || groups[i].round != groups[i - 1].round))
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 10, 12, 0),
                child: Text(
                  groups[i].round == null
                      ? 'Bosqichsiz'
                      : '${groups[i].round}-bosqich',
                  style: RzType.footnote.copyWith(
                    color: c.brandSoftFg,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            _GroupRow(
              ticket: ticket,
              clock: clock,
              group: groups[i],
              busy: groups[i].lines.any((KitchenLine l) => isLineBusy(l.id)),
              enabled: canManage && !orderBusy && !entry.voided,
              onTap: () => onGroupTap(groups[i]),
            ),
            if (i != groups.length - 1)
              Divider(height: 1, indent: 12, endIndent: 12, color: c.line),
          ],
          if (!entry.voided && canManage && anyBumpable) ...<Widget>[
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
              child: RzButton(
                label: 'Hammasi tayyor',
                icon: Icons.done_all_rounded,
                variant: RzButtonVariant.success,
                size: RzButtonSize.lg,
                fullWidth: true,
                loading: orderBusy,
                onPressed: onBumpAll,
              ),
            ),
          ] else
            const SizedBox(height: 8),
        ],
      ),
    );
  }

  /// Katta yozuv: xona/stol ("Xona 3", "Stol 7"); joy yo'q bo'lsa tur ("Olib ketish").
  static String placeLabel(KitchenTicket t) {
    final String? table = t.tableRef?.trim();
    if (table != null && table.isNotEmpty) {
      return RegExp(r'^\d+$').hasMatch(table) ? 'Stol $table' : table;
    }
    // P6-25: kassada to'langan buyurtmaning stol tablichkasi
    final String? plate = t.tableLabel?.trim();
    if (plate != null && plate.isNotEmpty) return TablePlate.text(plate);
    return OrderType.fromWire(t.type)?.shortLabel ?? t.type;
  }
}

class _GroupRow extends StatelessWidget {
  const _GroupRow({
    required this.ticket,
    required this.clock,
    required this.group,
    required this.busy,
    required this.enabled,
    required this.onTap,
  });

  final KitchenTicket ticket;
  final ValueListenable<DateTime> clock;
  final SkewerGroup group;
  final bool busy;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final bool voided = group.voided;
    final bool done = group.done;
    final bool served = group.lines.every((KitchenLine l) => l.isServed);
    final bool tappable =
        enabled && !voided && !busy && !group.held && !(done && served);
    final TextDecoration? strike = voided || done
        ? TextDecoration.lineThrough
        : null;
    final Color color = voided || done ? c.fgMuted : c.fg;
    final String count = group.piece
        ? '${formatCount(group.count)} six'
        : group.quantityLabel;
    final String? reason = group.lines.first.voidReason?.trim();
    return Semantics(
      button: tappable,
      label:
          '${group.title}, $count${group.courseNo != null ? ', ${CourseTag.label(group.courseNo!)}' : ''}${group.awaitingWeight ? ", ${WeightBadge.label}" : ''}, ${voided
              ? "o'chirilgan"
              : served
              ? 'berilgan'
              : done
              ? 'tayyor'
              : 'tayyor emas'}${tappable ? (done ? '. Bosib qaytaring' : '. Bosib tayyor deb belgilang') : ''}',
      excludeSemantics: true,
      child: InkWell(
        onTap: tappable ? onTap : null,
        child: Container(
          color: voided ? c.dangerSoft.withValues(alpha: 0.5) : null,
          constraints: const BoxConstraints(minHeight: 72),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          child: Row(
            children: <Widget>[
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      group.title,
                      style: MangalCard._name.copyWith(
                        color: color,
                        decoration: strike,
                      ),
                    ),
                    for (final String note in <String>{
                      for (final KitchenLine l in group.lines)
                        for (final KitchenModifier m in l.modifiers) m.label,
                      if (!voided)
                        for (final KitchenLine l in group.lines)
                          if (l.note?.trim().isNotEmpty ?? false)
                            l.note!.trim(),
                    })
                      Text(
                        note,
                        style: KdsType.modifier.copyWith(
                          color: c.warningSoftFg,
                          fontWeight: FontWeight.w700,
                          decoration: strike,
                        ),
                      ),
                    if (group.courseNo != null && !voided)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Align(
                          alignment: Alignment.centerLeft,
                          child: CourseTag(course: group.courseNo!),
                        ),
                      ),
                    if (group.awaitingWeight && !voided && !done)
                      const Padding(
                        padding: EdgeInsets.only(top: 4),
                        child: WeightBadge(),
                      ),
                    if (group.timerLine case final KitchenLine timed)
                      GrillTimerLabel(
                        ticket: ticket,
                        line: timed,
                        clock: clock,
                        large: true,
                      ),
                    if (voided) ...<Widget>[
                      const SizedBox(height: 4),
                      const VoidBadge(),
                      if (reason != null && reason.isNotEmpty)
                        Text(
                          'Sabab: $reason',
                          style: KdsType.modifier.copyWith(
                            color: c.dangerSoftFg,
                          ),
                        ),
                    ],
                    if (group.held)
                      Text(
                        'Oldindan buyurtma · hali yuborilmagan',
                        style: KdsType.modifier.copyWith(
                          color: c.warningSoftFg,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    if (served)
                      Text(
                        'Berildi',
                        style: KdsType.modifier.copyWith(color: c.fgMuted),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Text(
                count,
                style: MangalCard._count.copyWith(
                  color: voided || done ? c.fgMuted : c.brandSoftFg,
                  decoration: strike,
                ),
              ),
              const SizedBox(width: 10),
              SizedBox.square(
                dimension: 36,
                child: busy
                    ? const Padding(
                        padding: EdgeInsets.all(6),
                        child: CircularProgressIndicator(strokeWidth: 3),
                      )
                    : Icon(
                        voided
                            ? Icons.block_rounded
                            : served
                            ? Icons.done_all_rounded
                            : done
                            ? Icons.check_circle_rounded
                            : group.held
                            ? Icons.schedule_rounded
                            : Icons.radio_button_unchecked_rounded,
                        size: 36,
                        color: voided
                            ? c.danger
                            : done
                            ? c.success
                            : group.held
                            ? c.warning
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
