import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../board/board_view.dart';
import '../board/wait_level.dart';
import '../settings/kds_settings.dart';
import 'kds_type.dart';
import 'line_marks.dart';

/// Bitta oshxona chiptasi: raqam, turi, kutish vaqti (rang, ikonka va yorliq bilan), izoh, taomlar va tugmalar.
class TicketCard extends StatelessWidget {
  const TicketCard({
    super.key,
    required this.entry,
    required this.clock,
    required this.settings,
    required this.canManage,
    required this.isNew,
    this.newRounds = const <int?>{},
    required this.isLineBusy,
    required this.orderBusy,
    required this.onLineTap,
    required this.onBumpAll,
    required this.onMarkReady,
  });

  final BoardTicket entry;

  /// Har soniyada yangilanadigan soat (faqat taymer qayta chiziladi).
  final ValueListenable<DateTime> clock;
  final KdsSettings settings;
  final bool canManage;
  final bool isNew;

  /// Yaqinda qo'shilgan qatorlari bor bosqichlar: ochiq stolning yangi bosqichi sarlavhasida "Yangi".
  final Set<int?> newRounds;
  final bool Function(int lineId) isLineBusy;
  final bool orderBusy;
  final void Function(KitchenLine line) onLineTap;
  final VoidCallback onBumpAll;
  final VoidCallback onMarkReady;

  KitchenTicket get ticket => entry.ticket;

  /// Qatorni "tayyor" deb belgilash mumkin (ushlab turilgan qator hali oshxonada emas).
  static bool canBump(KitchenLine l) => l.canBeReady;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final String? notes = ticket.notes?.trim();
    final bool anyBumpable = ticket.lines.any(canBump);
    // P6-14: navbat raqamli kassa buyurtmasi hali oshxonada: "Raqam N tayyor" (yig'ish) tugmasi
    final bool expo = ticket.isQueueOrder && ticket.pickupStatus == 'preparing';
    final bool nothingLeft = ticket.lines.every(
      (KitchenLine l) => l.isDone || l.isVoid,
    );
    final Set<int> rounds = <int>{
      for (final KitchenLine l in ticket.lines)
        if (l.roundNo != null) l.roundNo!,
    };
    return ValueListenableBuilder<DateTime>(
      valueListenable: clock,
      builder: (BuildContext context, DateTime now, Widget? body) {
        final Duration waiting = entry.waiting(now);
        final WaitLevel level = entry.voided
            ? WaitLevel.normal
            : WaitLevel.of(waiting, settings);
        final Color border = switch (level) {
          WaitLevel.normal => c.line,
          WaitLevel.warn => c.warning,
          WaitLevel.late => c.danger,
        };
        return Material(
          color: c.surface,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(RzRadius.card),
            side: BorderSide(
              color: entry.voided ? c.danger : border,
              width: level == WaitLevel.normal && !entry.voided ? 1 : 3,
            ),
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              _Header(
                ticket: ticket,
                waiting: waiting,
                level: level,
                isNew: isNew,
                voided: entry.voided,
                rounds: rounds,
              ),
              body!,
            ],
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
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Icon(Icons.sticky_note_2_outlined, color: c.warningSoftFg),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      notes,
                      style: KdsType.modifier.copyWith(color: c.warningSoftFg),
                    ),
                  ),
                ],
              ),
            ),
          const SizedBox(height: 4),
          for (var i = 0; i < ticket.lines.length; i++) ...<Widget>[
            if (rounds.length > 1 &&
                (i == 0 ||
                    ticket.lines[i].roundNo != ticket.lines[i - 1].roundNo))
              _RoundHeading(
                round: ticket.lines[i].roundNo,
                // Hamma bosqich yangi (yangi chipta) bo'lsa sarlavhadagi belgi yetarli
                isNew:
                    newRounds.contains(ticket.lines[i].roundNo) &&
                    !rounds.every(newRounds.contains),
              ),
            _LineRow(
              line: ticket.lines[i],
              busy: isLineBusy(ticket.lines[i].id),
              enabled: canManage && !orderBusy && !entry.voided,
              onTap: () => onLineTap(ticket.lines[i]),
            ),
            if (i != ticket.lines.length - 1)
              Divider(height: 1, indent: 12, endIndent: 12, color: c.line),
          ],
          if (entry.voided)
            const SizedBox(height: 8)
          else if (canManage) ...<Widget>[
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  if (anyBumpable)
                    RzButton(
                      label: 'Hammasi tayyor',
                      icon: Icons.done_all_rounded,
                      variant: RzButtonVariant.success,
                      size: RzButtonSize.lg,
                      fullWidth: true,
                      loading: orderBusy,
                      onPressed: onBumpAll,
                    ),
                  if (anyBumpable && expo) const SizedBox(height: 8),
                  if (expo)
                    // P6-14: yig'ish (expo): raqam tayyor — qolgan qatorlar ham tayyor bo'ladi, raqam ekranda chaqiriladi
                    RzButton(
                      key: const ValueKey<String>('expo-ready'),
                      label: "Raqam ${ticket.queueNumber} tayyor",
                      icon: Icons.campaign_outlined,
                      variant: anyBumpable
                          ? RzButtonVariant.secondary
                          : RzButtonVariant.success,
                      size: RzButtonSize.lg,
                      fullWidth: true,
                      loading: orderBusy,
                      onPressed: onMarkReady,
                    )
                  else if (!anyBumpable &&
                      ticket.isCooking &&
                      nothingLeft &&
                      entry.canMarkOrderReady)
                    RzButton(
                      label: 'Buyurtma tayyor',
                      icon: Icons.check_circle_outline,
                      size: RzButtonSize.lg,
                      fullWidth: true,
                      onPressed: onMarkReady,
                    ),
                ],
              ),
            ),
          ] else
            const SizedBox(height: 8),
        ],
      ),
    );
  }
}

/// Bosqich sarlavhasi (chiptada bir nechta bosqich bo'lsa).
class _RoundHeading extends StatelessWidget {
  const _RoundHeading({required this.round, this.isNew = false});

  final int? round;
  final bool isNew;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 0),
      child: Row(
        children: <Widget>[
          Text(
            round == null ? 'Bosqichsiz' : '$round-bosqich',
            style: RzType.footnote.copyWith(
              color: c.brandSoftFg,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.4,
            ),
          ),
          if (isNew) ...<Widget>[const SizedBox(width: 8), const NewBadge()],
          const SizedBox(width: 8),
          Expanded(child: Divider(height: 1, color: c.line)),
        ],
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({
    required this.ticket,
    required this.waiting,
    required this.level,
    required this.isNew,
    required this.voided,
    required this.rounds,
  });

  final KitchenTicket ticket;
  final Duration waiting;
  final WaitLevel level;
  final bool isNew;
  final bool voided;
  final Set<int> rounds;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final (Color bg, Color fg, IconData icon, String? label) = voided
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
    final OrderType? type = OrderType.fromWire(ticket.type);
    final IconData typeIcon = switch (type) {
      OrderType.dineIn => Icons.restaurant_rounded,
      OrderType.pickup => Icons.shopping_bag_outlined,
      OrderType.delivery => Icons.delivery_dining_rounded,
      null => Icons.receipt_long_outlined,
    };
    final List<String> place = <String>[
      type?.shortLabel ?? ticket.type,
      if (ticket.hallRef case final String hall when hall.isNotEmpty) hall,
      if (ticket.tableRef case final String table when table.isNotEmpty)
        // Xona, tapchan va nomli joy o'z nomi bilan ("Xona 3"), raqam bo'lsa "Stol 7"
        RegExp(r'^\d+$').hasMatch(table) ? 'Stol $table' : table,
    ];
    final String channel =
        OrderChannel.fromWire(ticket.channel)?.label ?? ticket.channel;
    // P6-14: avval to'langan kassa buyurtmasi yopilgan ("Yetkazildi" emas), raqami hali oshxonada
    final String status = ticket.isClosedPickupCooking
        ? "To'langan"
        : OrderStatus.fromWire(ticket.status)?.staffLabel ?? ticket.status;
    final String accepted = formatTime(ticket.acceptedAt ?? ticket.createdAt);
    final TextStyle meta = KdsType.meta.copyWith(color: fg);

    return Semantics(
      container: true,
      label:
          '${ticket.code}, ${place.join(', ')}, kutish ${formatDuration(waiting)}${label != null ? ', $label' : ''}',
      child: Container(
        color: bg,
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(
                  child: Row(
                    children: <Widget>[
                      Flexible(
                        child: Text(
                          // P6-14: kassa navbat raqami asosiy; buyurtma kodi pastdagi qatorda
                          ticket.queueNumber != null
                              ? '№${ticket.queueNumber}'
                              : ticket.code,
                          key: ticket.queueNumber != null
                              ? const ValueKey<String>('queue-number')
                              : null,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: KdsType.code.copyWith(color: fg),
                        ),
                      ),
                      if (isNew) ...<Widget>[
                        const SizedBox(width: 8),
                        const NewBadge(),
                      ],
                    ],
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
            Row(
              children: <Widget>[
                Icon(typeIcon, color: fg, size: 20),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    '${place.join(' · ')}  ·  $channel',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: meta.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
                if (label != null)
                  Text(
                    label,
                    style: meta.copyWith(fontWeight: FontWeight.w700),
                  ),
              ],
            ),
            const SizedBox(height: 2),
            Text(
              '${ticket.queueNumber != null ? '${ticket.code} · ' : ''}$status · $accepted · ${ticket.readyCount}/${ticket.activeCount} tayyor${rounds.length == 1 ? ' · ${rounds.single}-bosqich' : ''}',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: meta,
            ),
          ],
        ),
      ),
    );
  }
}

class _LineRow extends StatelessWidget {
  const _LineRow({
    required this.line,
    required this.busy,
    required this.enabled,
    required this.onTap,
  });

  final KitchenLine line;
  final bool busy;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final bool voided = line.isVoid;
    final bool done = line.isDone;
    final bool held = line.isHeld;
    // Tayyor qatorni qaytarish mumkin; berilgan (ofitsiant belgilagan) va o'chirilganni oshxona o'zgartirmaydi;
    // ushlab turilgan qator — faqat vaqti bo'lsa (oldindan pishiriladigan osh, P3-26)
    final bool actionable =
        enabled && !voided && (!held || line.dueAt != null) && !line.isServed;
    final bool tappable = actionable && !busy;
    final Color textColor = voided || done ? c.fgMuted : c.fg;
    final TextDecoration? strike = voided || done
        ? TextDecoration.lineThrough
        : null;
    final String quantity = line.isPiece
        ? '${line.quantityLabel}×'
        : line.quantityLabel;
    final String stateLabel = voided
        ? "o'chirilgan"
        : line.isServed
        ? 'berilgan'
        : done
        ? 'tayyor'
        : held
        ? 'oldindan buyurtma, hali yuborilmagan'
        : 'tayyor emas';
    final String? note = line.note?.trim();
    final String? reason = line.voidReason?.trim();
    final DateTime? due = line.dueAt;
    return Semantics(
      button: actionable,
      label:
          '${line.title}, $quantity, $stateLabel${actionable ? (line.isReady ? '. Bosib qaytaring' : '. Bosib tayyor deb belgilang') : ''}',
      excludeSemantics: true,
      child: InkWell(
        onTap: tappable ? onTap : null,
        child: Container(
          color: voided ? c.dangerSoft.withValues(alpha: 0.5) : null,
          constraints: const BoxConstraints(minHeight: 56),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              ConstrainedBox(
                constraints: const BoxConstraints(minWidth: 52),
                child: Text(
                  quantity,
                  style: KdsType.quantity.copyWith(
                    color: voided || done ? c.fgMuted : c.brandSoftFg,
                    decoration: strike,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      line.title,
                      style: KdsType.line.copyWith(
                        color: textColor,
                        decoration: strike,
                      ),
                    ),
                    for (final KitchenModifier m in line.modifiers)
                      Text(
                        '+ ${m.label}',
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
                    if (voided) ...<Widget>[
                      const SizedBox(height: 4),
                      const Align(
                        alignment: Alignment.centerLeft,
                        child: VoidBadge(),
                      ),
                      if (reason != null && reason.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(top: 2),
                          child: Text(
                            'Sabab: $reason',
                            style: KdsType.modifier.copyWith(
                              color: c.dangerSoftFg,
                            ),
                          ),
                        ),
                    ],
                    if (held && due != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 2),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: <Widget>[
                            Icon(
                              Icons.schedule_rounded,
                              size: 18,
                              color: c.warningSoftFg,
                            ),
                            const SizedBox(width: 4),
                            Flexible(
                              child: Text(
                                'Muddat ${formatTime(due)} · hali yuborilmagan',
                                style: KdsType.modifier.copyWith(
                                  color: c.warningSoftFg,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    if (line.isServed)
                      Text(
                        'Berildi',
                        style: KdsType.modifier.copyWith(color: c.fgMuted),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              SizedBox.square(
                dimension: 32,
                child: busy
                    ? const Padding(
                        padding: EdgeInsets.all(5),
                        child: CircularProgressIndicator(strokeWidth: 3),
                      )
                    : Icon(
                        voided
                            ? Icons.block_rounded
                            : line.isServed
                            ? Icons.done_all_rounded
                            : done
                            ? Icons.check_circle_rounded
                            : held
                            ? Icons.schedule_rounded
                            : Icons.radio_button_unchecked_rounded,
                        size: 32,
                        color: voided
                            ? c.danger
                            : done
                            ? c.success
                            : held
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
