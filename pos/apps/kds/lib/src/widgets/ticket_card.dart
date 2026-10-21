import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../board/wait_level.dart';
import '../settings/kds_settings.dart';
import 'kds_type.dart';

/// Bitta oshxona chiptasi: raqam, turi, kutish vaqti (rang, ikonka va yorliq bilan), izoh, taomlar va tugmalar.
class TicketCard extends StatelessWidget {
  const TicketCard({
    super.key,
    required this.ticket,
    required this.clock,
    required this.settings,
    required this.canManage,
    required this.isNew,
    required this.isLineBusy,
    required this.orderBusy,
    required this.onLineTap,
    required this.onBumpAll,
    required this.onMarkReady,
  });

  final KitchenTicket ticket;

  /// Har soniyada yangilanadigan soat (faqat taymer qayta chiziladi).
  final ValueListenable<DateTime> clock;
  final KdsSettings settings;
  final bool canManage;
  final bool isNew;
  final bool Function(int lineId) isLineBusy;
  final bool orderBusy;
  final void Function(KitchenLine line) onLineTap;
  final VoidCallback onBumpAll;
  final VoidCallback onMarkReady;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final String? notes = ticket.notes?.trim();
    final int pending = ticket.lines.length - ticket.readyCount;
    return ValueListenableBuilder<DateTime>(
      valueListenable: clock,
      builder: (BuildContext context, DateTime now, Widget? body) {
        final Duration waiting = ticket.waitingAt(now);
        final WaitLevel level = WaitLevel.of(waiting, settings);
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
              color: border,
              width: level == WaitLevel.normal ? 1 : 3,
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
          for (final KitchenLine line in ticket.lines) ...<Widget>[
            _LineRow(
              line: line,
              busy: isLineBusy(line.id),
              enabled: canManage && !orderBusy,
              onTap: () => onLineTap(line),
            ),
            if (line != ticket.lines.last)
              Divider(height: 1, indent: 12, endIndent: 12, color: c.line),
          ],
          if (canManage) ...<Widget>[
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
              child: pending > 0
                  ? RzButton(
                      label: 'Hammasi tayyor',
                      icon: Icons.done_all_rounded,
                      variant: RzButtonVariant.success,
                      size: RzButtonSize.lg,
                      fullWidth: true,
                      loading: orderBusy,
                      onPressed: onBumpAll,
                    )
                  : ticket.isCooking
                  ? RzButton(
                      label: 'Buyurtma tayyor',
                      icon: Icons.check_circle_outline,
                      size: RzButtonSize.lg,
                      fullWidth: true,
                      onPressed: onMarkReady,
                    )
                  : const SizedBox.shrink(),
            ),
          ] else
            const SizedBox(height: 8),
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
  });

  final KitchenTicket ticket;
  final Duration waiting;
  final WaitLevel level;
  final bool isNew;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final (Color bg, Color fg, IconData icon, String? label) = switch (level) {
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
        'Stol $table',
    ];
    final String channel =
        OrderChannel.fromWire(ticket.channel)?.label ?? ticket.channel;
    final String status =
        OrderStatus.fromWire(ticket.status)?.staffLabel ?? ticket.status;
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
                          ticket.code,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: KdsType.code.copyWith(color: fg),
                        ),
                      ),
                      if (isNew) ...<Widget>[
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 2,
                          ),
                          decoration: BoxDecoration(
                            color: c.info,
                            borderRadius: BorderRadius.circular(
                              RzRadius.control,
                            ),
                          ),
                          child: Text(
                            'Yangi',
                            style: RzType.footnote.copyWith(
                              color: c.onInfo,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
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
              '$status · $accepted · ${ticket.readyCount}/${ticket.lines.length} tayyor',
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
    final bool ready = line.isReady;
    final Color textColor = ready ? c.fgMuted : c.fg;
    final TextDecoration? strike = ready ? TextDecoration.lineThrough : null;
    final String quantity = line.unit == 'pcs'
        ? '${line.quantityLabel}×'
        : line.quantityLabel;
    return Semantics(
      button: enabled,
      label:
          '${line.title}, $quantity, ${ready ? 'tayyor' : 'tayyor emas'}${enabled ? (ready ? '. Bosib qaytaring' : '. Bosib tayyor deb belgilang') : ''}',
      excludeSemantics: true,
      child: InkWell(
        onTap: enabled && !busy ? onTap : null,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 56),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                ConstrainedBox(
                  constraints: const BoxConstraints(minWidth: 52),
                  child: Text(
                    quantity,
                    style: KdsType.quantity.copyWith(
                      color: ready ? c.fgMuted : c.brandSoftFg,
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
                          ready
                              ? Icons.check_circle_rounded
                              : Icons.radio_button_unchecked_rounded,
                          size: 32,
                          color: ready ? c.success : c.fgMuted,
                        ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
