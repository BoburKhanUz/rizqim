import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../board/board_view.dart';
import '../board/line_timer.dart';
import '../board/wait_level.dart';
import 'combo_heading.dart';
import 'kds_type.dart';
import 'station_kinds.dart';
import 'target_timer_label.dart';

/// P6-33 (`kds_expo`): yig'ish ekrani kartochkasi. Ko'p sexli buyurtma: har sexning o'z taomlari va tayyorlik
/// holati (`assembly.stations`), hamma sex tayyor bo'lganda "Yig'ildi" tugmasi yonadi. Tugma navbat raqamini
/// "tayyor" qiladi (raqamlar ekrani va mijoz ekrani) va onlayn buyurtma mijozga xabar beradi. Taomlar bu yerda faqat
/// ko'rinadi — tayyor deb sexlar belgilaydi.
///
/// Vaqt: hamma sex tayyor bo'lgach — oxirgi sex tayyor bo'lgandan beri o'tgan vaqt yig'ish maqsadiga ([expoTarget])
/// nisbatan; undan oldin — eng eski pishayotgan qatordan, qatorlarning o'z maqsadlariga qarab.
class ExpoCard extends StatelessWidget {
  const ExpoCard({
    super.key,
    required this.entry,
    required this.clock,
    required this.canManage,
    required this.busy,
    required this.onAssembled,
    this.expoTarget,
  });

  final BoardTicket entry;
  final ValueListenable<DateTime> clock;
  final bool canManage;

  /// "Yig'ildi" yuborilgan, javob kutilmoqda.
  final bool busy;
  final VoidCallback onAssembled;

  /// Yig'ish sexining vaqt maqsadi (`target_seconds`); `null` — yo'q.
  final Duration? expoTarget;

  KitchenTicket get ticket => entry.ticket;
  KitchenAssembly get assembly => ticket.assembly!;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return ValueListenableBuilder<DateTime>(
      valueListenable: clock,
      builder: (BuildContext context, DateTime now, _) {
        final bool ready = assembly.waiting || assembly.allStationsDone;
        final Duration waiting = entry.waiting(now);
        final LineTimer? assemblyTimer = ready && expoTarget != null
            ? LineTimer(target: expoTarget!, elapsed: waiting)
            : null;
        final WaitLevel level =
            assemblyTimer?.level ??
            LineTimer.worstLevel(ticket, ticket.lines, now);
        final (Color bg, Color fg, IconData icon) = switch (level) {
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
          WaitLevel.late => (c.danger, c.onDanger, Icons.warning_amber_rounded),
        };
        final String? levelLabel = switch (level) {
          WaitLevel.normal => null,
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
              color: ready ? c.success : _border(c, level),
              width: ready || level != WaitLevel.normal ? 3 : 1,
            ),
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Semantics(
                container: true,
                label:
                    '$id, ${ready ? "yig'ishga tayyor" : 'sexlar pishirmoqda'}, kutish ${formatDuration(waiting)}${levelLabel != null ? ', $levelLabel' : ''}',
                child: Container(
                  color: bg,
                  padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      Row(
                        children: <Widget>[
                          Expanded(
                            child: Text(
                              id,
                              key: const ValueKey<String>('expo-id'),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: KdsType.code.copyWith(color: fg),
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
                      const SizedBox(height: 2),
                      Row(
                        children: <Widget>[
                          Expanded(
                            child: Text(
                              _subtitle(),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: KdsType.meta.copyWith(
                                color: fg,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          if (levelLabel != null)
                            Text(
                              levelLabel,
                              style: KdsType.meta.copyWith(
                                color: fg,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                        ],
                      ),
                      if (assemblyTimer != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 2),
                          child: Text(
                            "Yig'ish: ${assemblyTimer.text}",
                            key: const ValueKey<String>('expo-timer'),
                            style: KdsType.meta.copyWith(color: fg),
                          ),
                        ),
                    ],
                  ),
                ),
              ),
              if ((ticket.notes ?? '').trim().isNotEmpty)
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
                      Icon(
                        Icons.sticky_note_2_outlined,
                        color: c.warningSoftFg,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          ticket.notes!.trim(),
                          style: KdsType.modifier.copyWith(
                            color: c.warningSoftFg,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              for (final KitchenAssemblyStation s in _blocks())
                _StationBlock(
                  ticket: ticket,
                  station: s,
                  lines: _linesOf(s),
                  clock: clock,
                ),
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
                child: canManage
                    ? Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: <Widget>[
                          RzButton(
                            key: const ValueKey<String>('expo-assembled'),
                            label: "Yig'ildi",
                            icon: Icons.takeout_dining_outlined,
                            variant: RzButtonVariant.success,
                            size: RzButtonSize.lg,
                            fullWidth: true,
                            loading: busy,
                            onPressed: ready ? onAssembled : null,
                          ),
                          if (!ready)
                            Padding(
                              padding: const EdgeInsets.only(top: 6),
                              child: Text(
                                "Hamma sexlar tayyor bo'lishini kuting.",
                                textAlign: TextAlign.center,
                                style: KdsType.meta.copyWith(color: c.fgMuted),
                              ),
                            ),
                        ],
                      )
                    : const SizedBox.shrink(),
              ),
            ],
          ),
        );
      },
    );
  }

  static Color _border(RzColors c, WaitLevel level) => switch (level) {
    WaitLevel.normal => c.line,
    WaitLevel.warn => c.warning,
    WaitLevel.late => c.danger,
  };

  /// "Olib ketish · Kassa · 3 sex".
  String _subtitle() {
    final OrderType? type = OrderType.fromWire(ticket.type);
    final String channel =
        OrderChannel.fromWire(ticket.channel)?.label ?? ticket.channel;
    return <String>[
      type?.shortLabel ?? ticket.type,
      channel,
      if (ticket.queueNumber != null) ticket.code,
    ].join(' · ');
  }

  Set<int?> get _known => <int?>{
    for (final KitchenAssemblyStation s in assembly.stations) s.stationId,
  };

  /// Sexning o'z taomlari; sexsiz guruh (`station_id == null`) — sexsiz qatorlar va sexi ma'lum sexlar orasida
  /// bo'lmagan (masalan, onlayn) qatorlar.
  List<KitchenLine> _linesOf(KitchenAssemblyStation s) => <KitchenLine>[
    for (final KitchenLine l in ticket.lines)
      if (l.stationId == s.stationId ||
          (s.stationId == null && !_known.contains(l.stationId)))
        l,
  ];

  /// Serverning sexlari; sexi ma'lum emas qatorlar bo'lsa va "Umumiy" guruhi yo'q bo'lsa, ular uchun alohida blok.
  List<KitchenAssemblyStation> _blocks() {
    final bool orphans = ticket.lines.any(
      (KitchenLine l) => !_known.contains(l.stationId),
    );
    if (!orphans || _known.contains(null)) return assembly.stations;
    final List<KitchenLine> rest = <KitchenLine>[
      for (final KitchenLine l in ticket.lines)
        if (!_known.contains(l.stationId)) l,
    ];
    return <KitchenAssemblyStation>[
      ...assembly.stations,
      KitchenAssemblyStation(
        name: 'Umumiy',
        itemsTotal: rest.length,
        itemsReady: rest.where((KitchenLine l) => l.isDone).length,
        done: rest.every((KitchenLine l) => l.isDone),
      ),
    ];
  }
}

class _StationBlock extends StatelessWidget {
  const _StationBlock({
    required this.ticket,
    required this.station,
    required this.lines,
    required this.clock,
  });

  final KitchenTicket ticket;
  final KitchenAssemblyStation station;
  final List<KitchenLine> lines;
  final ValueListenable<DateTime> clock;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return ValueListenableBuilder<DateTime>(
      valueListenable: clock,
      builder: (BuildContext context, DateTime now, _) {
        // Sex hali pishirayotgan va biror qatori maqsaddan o'tgan: qaysi sex kechikayotgani yig'ishda ko'rinadi
        final bool late =
            !station.done &&
            LineTimer.worstLevel(ticket, lines, now) == WaitLevel.late;
        final Color tone = station.done
            ? c.success
            : late
            ? c.dangerSoftFg
            : c.fgMuted;
        return Container(
          key: ValueKey<String>('expo-station-${station.stationId ?? 'none'}'),
          padding: const EdgeInsets.fromLTRB(12, 10, 12, 4),
          child: Semantics(
            container: true,
            label:
                '${station.name}: ${station.itemsReady} / ${station.itemsTotal} tayyor${station.done
                    ? ", sex tayyor"
                    : late
                    ? ', kechikmoqda'
                    : ''}',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Icon(stationKindIcon(station.kind), size: 22, color: tone),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        station.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: KdsType.line.copyWith(
                          color: station.done ? c.fgMuted : c.fg,
                        ),
                      ),
                    ),
                    if (late)
                      Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: Text(
                          'Kechikdi',
                          style: KdsType.meta.copyWith(
                            color: c.dangerSoftFg,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    Text(
                      '${station.itemsReady}/${station.itemsTotal}',
                      style: KdsType.quantity.copyWith(color: tone),
                    ),
                    const SizedBox(width: 6),
                    Icon(
                      station.done
                          ? Icons.check_circle_rounded
                          : late
                          ? Icons.warning_amber_rounded
                          : Icons.hourglass_bottom_rounded,
                      size: 26,
                      color: tone,
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(
                    minHeight: 6,
                    value: station.itemsTotal == 0
                        ? 1
                        : station.itemsReady / station.itemsTotal,
                    color: station.done
                        ? c.success
                        : (late ? c.danger : c.warning),
                    backgroundColor: c.line,
                  ),
                ),
                for (var i = 0; i < lines.length; i++) ...<Widget>[
                  if (ComboHeading.startsGroup(lines, i))
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(
                        'Kombo: ${lines[i].combo!.label}',
                        style: KdsType.meta.copyWith(
                          color: c.brandSoftFg,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  _ExpoLine(ticket: ticket, line: lines[i], clock: clock),
                ],
              ],
            ),
          ),
        );
      },
    );
  }
}

class _ExpoLine extends StatelessWidget {
  const _ExpoLine({
    required this.ticket,
    required this.line,
    required this.clock,
  });

  final KitchenTicket ticket;
  final KitchenLine line;
  final ValueListenable<DateTime> clock;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final bool done = line.isDone;
    final TextDecoration? strike = done ? TextDecoration.lineThrough : null;
    final String quantity = line.isPiece
        ? '${line.quantityLabel}×'
        : line.quantityLabel;
    final String mods = line.modifiers
        .map((KitchenModifier m) => m.label)
        .join(', ');
    return Padding(
      padding: const EdgeInsets.only(top: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(
            done ? Icons.check_rounded : Icons.radio_button_unchecked_rounded,
            size: 20,
            color: done ? c.success : c.fgMuted,
          ),
          const SizedBox(width: 6),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  '$quantity ${line.title}${mods.isEmpty ? '' : ' · $mods'}',
                  style: KdsType.modifier.copyWith(
                    color: done ? c.fgMuted : c.fg,
                    fontWeight: FontWeight.w600,
                    decoration: strike,
                  ),
                ),
                if ((line.note ?? '').trim().isNotEmpty && !done)
                  Text(
                    line.note!.trim(),
                    style: KdsType.meta.copyWith(
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
              ],
            ),
          ),
        ],
      ),
    );
  }
}
