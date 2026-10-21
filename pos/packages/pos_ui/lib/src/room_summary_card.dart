import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'floor_map.dart';

/// Xona (yoki stol) hisobining qisqa kartochkasi: holat, mehmonlar, ofitsiant, qancha vaqt ochiq, bosqichlar, jami,
/// avans va minimal summagacha qancha qolgani. Pastida amallar ([actions]: "Taom qo'shish", "Schyot").
class RoomSummaryCard extends StatelessWidget {
  const RoomSummaryCard({
    super.key,
    required this.title,
    required this.status,
    required this.total,
    this.kind = FloorTableKind.room,
    this.guests,
    this.seats,
    this.waiter,
    this.openedAt,
    this.now,
    this.rounds,
    this.itemsCount,
    this.prepaid,
    this.minSpend,
    this.bookingNote,
    this.actions = const <Widget>[],
  });

  final String title;
  final FloorTableStatus status;
  final FloorTableKind kind;

  /// Hozirgacha hisob (taomlar, xona puli va xizmat haqi bilan).
  final PosMoney total;
  final int? guests;
  final int? seats;
  final String? waiter;
  final DateTime? openedAt;
  final DateTime? now;

  /// Nechta bosqich yuborilgan.
  final int? rounds;
  final int? itemsCount;

  /// Bron avansi (bo'nak).
  final PosMoney? prepaid;

  /// Xonaning minimal summasi ("obyazalovka").
  final PosMoney? minSpend;

  /// "Bron: 19:00, Jasur, 12 kishi".
  final String? bookingNote;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final DateTime at = now ?? DateTime.now();

    Widget fact(IconData icon, String text) => Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Icon(icon, size: 18, color: c.fgMuted),
        const SizedBox(width: 6),
        Flexible(
          child: Text(
            text,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: RzType.body.copyWith(color: c.fg),
          ),
        ),
      ],
    );

    Widget moneyRow(String label, PosMoney value, {bool strong = false}) => Row(
      children: <Widget>[
        Expanded(
          child: Text(
            label,
            style: (strong ? RzType.headline : RzType.body).copyWith(
              color: strong ? c.fg : c.fgMuted,
            ),
          ),
        ),
        Text(
          value.format(),
          style: (strong ? RzType.title : RzType.body).copyWith(color: c.fg),
        ),
      ],
    );

    final PosMoney? minSpendLeft = minSpend == null || total >= minSpend!
        ? null
        : minSpend! - total;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Row(
              children: <Widget>[
                Icon(FloorStatusStyle.kindIcon(kind), color: c.fg),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: RzType.title.copyWith(color: c.fg),
                  ),
                ),
                FloorStatusBadge(status: status),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 16,
              runSpacing: 8,
              children: <Widget>[
                if (guests != null || seats != null)
                  fact(
                    Icons.people_outline,
                    guests == null
                        ? "$seats o'rin"
                        : (seats == null
                              ? '$guests mehmon'
                              : '$guests/$seats mehmon'),
                  ),
                if (openedAt != null)
                  fact(
                    Icons.schedule,
                    '${formatReceiptTime(openedAt!)} dan, ${formatOpenDuration(at.difference(openedAt!))}',
                  ),
                if (waiter != null) fact(Icons.badge_outlined, waiter!),
                if (rounds != null)
                  fact(Icons.layers_outlined, '$rounds bosqich'),
                if (itemsCount != null)
                  fact(Icons.restaurant_menu, '$itemsCount taom'),
              ],
            ),
            if (bookingNote != null) ...<Widget>[
              const SizedBox(height: 8),
              fact(Icons.event, bookingNote!),
            ],
            const SizedBox(height: 12),
            Divider(color: c.line),
            const SizedBox(height: 8),
            moneyRow('Hisob', total, strong: true),
            if (prepaid != null && !prepaid!.isZero) ...<Widget>[
              const SizedBox(height: 4),
              moneyRow('Avans', -prepaid!),
            ],
            if (minSpend != null) ...<Widget>[
              const SizedBox(height: 10),
              Semantics(
                label: minSpendLeft == null
                    ? 'Minimal summa bajarildi'
                    : 'Minimal summagacha ${minSpendLeft.format()}',
                excludeSemantics: true,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        minHeight: 8,
                        value: minSpend!.isZero
                            ? 1
                            : (total.tiyin / minSpend!.tiyin).clamp(0, 1),
                        backgroundColor: Color.alphaBlend(
                          c.surface2,
                          c.surface,
                        ),
                        color: minSpendLeft == null ? c.success : c.warning,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      minSpendLeft == null
                          ? 'Minimal summa (${minSpend!.format()}) bajarildi'
                          : 'Minimal summagacha: ${minSpendLeft.format()}',
                      style: RzType.footnote.copyWith(
                        color: minSpendLeft == null
                            ? c.successSoftFg
                            : c.warningSoftFg,
                      ),
                    ),
                  ],
                ),
              ),
            ],
            if (actions.isNotEmpty) ...<Widget>[
              const SizedBox(height: 14),
              Wrap(spacing: 8, runSpacing: 8, children: actions),
            ],
          ],
        ),
      ),
    );
  }
}
