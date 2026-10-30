import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'rooms_controller.dart';

/// Ro'yxatdagi kartochka: ochiq buyurtma (mehmonlar, ochiq vaqt, jami, holat; tayyor taomlar ajratib ko'rsatiladi)
/// yoki bo'sh joy.
class PlaceCard extends StatelessWidget {
  const PlaceCard({
    super.key,
    required this.entry,
    required this.onTap,
    this.readyCount = 0,
    this.openFor,
    this.staffId,
    this.showHall = true,
  });

  final PlaceEntry entry;
  final VoidCallback onTap;

  /// Berilishi kutilayotgan (tayyor) taomlar.
  final int readyCount;
  final Duration? openFor;

  /// Kirgan ofitsiant: boshqasining buyurtmasida uning ismi ko'rsatiladi.
  final int? staffId;
  final bool showHall;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final FloorOrder? order = entry.order;
    final bool ready = readyCount > 0;
    final String? hallName = showHall ? entry.hall?.name : null;
    final bool foreign = order?.waiter != null && order!.waiter!.id != staffId;

    final String semantics = <String>[
      entry.title,
      ?hallName,
      entry.status.label,
      if (ready) '$readyCount ta taom tayyor',
    ].join(', ');

    return Tooltip(
      message: entry.title,
      child: Semantics(
        button: true,
        label: semantics,
        excludeSemantics: true,
        child: Material(
          color: c.surface,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(RzRadius.card),
            side: BorderSide(
              color: ready ? c.success : c.line,
              width: ready ? 2 : 1,
            ),
          ),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: onTap,
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 72),
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Icon(
                          FloorStatusStyle.kindIcon(entry.kind),
                          color: c.fg,
                          size: 26,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text(
                                entry.title,
                                // Uzun nom ("Katta xona (oilaviy)") ikki qatorga sig'adi, to'liq nom bosib turganda
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: RzType.title.copyWith(color: c.fg),
                              ),
                              if (hallName != null)
                                Text(
                                  hallName,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: RzType.footnote.copyWith(
                                    color: c.fgMuted,
                                  ),
                                ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        FloorStatusBadge(status: entry.status),
                      ],
                    ),
                    if (order != null)
                      ..._orderFacts(context, order, foreign)
                    else
                      ..._freeFacts(context),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  List<Widget> _orderFacts(BuildContext context, FloorOrder o, bool foreign) {
    final RzColors c = context.rz;
    Widget fact(IconData icon, String text) => Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Icon(icon, size: 18, color: c.fgMuted),
        const SizedBox(width: 5),
        Flexible(
          child: Text(
            text,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: RzType.body.copyWith(color: c.fg),
          ),
        ),
      ],
    );
    return <Widget>[
      const SizedBox(height: 10),
      Wrap(
        spacing: 16,
        runSpacing: 6,
        children: <Widget>[
          if (o.guestsCount != null)
            fact(Icons.people_outline, '${o.guestsCount} mehmon'),
          if (openFor != null)
            fact(Icons.schedule, formatOpenDuration(openFor!)),
          fact(Icons.payments_outlined, o.totalPrice.format()),
          if (foreign) fact(Icons.badge_outlined, o.waiter!.name ?? ''),
        ],
      ),
      if (readyCount > 0) ...<Widget>[
        const SizedBox(height: 10),
        _Callout(
          icon: Icons.room_service_outlined,
          text: '$readyCount ta taom tayyor: olib borish kerak',
          background: c.successSoft,
          foreground: c.successSoftFg,
        ),
      ],
      if (o.unsentCount > 0) ...<Widget>[
        const SizedBox(height: 8),
        _Callout(
          icon: Icons.hourglass_empty,
          text: '${o.unsentCount} ta taom oshxonaga yuborilmagan',
          background: c.warningSoft,
          foreground: c.warningSoftFg,
        ),
      ],
    ];
  }

  List<Widget> _freeFacts(BuildContext context) {
    final RzColors c = context.rz;
    final FloorTable? t = entry.table;
    final FloorReservation? r = t?.reservation;
    return <Widget>[
      const SizedBox(height: 8),
      Row(
        children: <Widget>[
          if (t?.seats != null) ...<Widget>[
            Icon(Icons.people_outline, size: 18, color: c.fgMuted),
            const SizedBox(width: 5),
            Text(
              "${t!.seats} o'rin",
              style: RzType.body.copyWith(color: c.fgMuted),
            ),
            const SizedBox(width: 16),
          ],
          // P6-26: the hostess' mark; opening the table is never blocked by it
          if (t?.isCleaning ?? false) ...<Widget>[
            Icon(
              Icons.cleaning_services_outlined,
              size: 18,
              color: c.warningSoftFg,
            ),
            const SizedBox(width: 5),
            Text(
              'Tozalanmoqda',
              style: RzType.body.copyWith(color: c.warningSoftFg),
            ),
            const SizedBox(width: 16),
          ],
          if (r != null && r.startsAt != null)
            Flexible(
              child: Text(
                'Bron: ${formatReceiptTime(r.startsAt!.toLocal())}'
                '${r.guests == null ? '' : ', ${r.guests} kishi'}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: RzType.body.copyWith(color: c.warningSoftFg),
              ),
            ),
          const Spacer(),
          Text('Ochish', style: RzType.headline.copyWith(color: c.brandSoftFg)),
          Icon(Icons.chevron_right, color: c.brandSoftFg),
        ],
      ),
    ];
  }
}

class _Callout extends StatelessWidget {
  const _Callout({
    required this.icon,
    required this.text,
    required this.background,
    required this.foreground,
  });

  final IconData icon;
  final String text;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: background,
      borderRadius: BorderRadius.circular(RzRadius.control),
    ),
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      child: Row(
        children: <Widget>[
          Icon(icon, size: 20, color: foreground),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: RzType.body.copyWith(
                color: foreground,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    ),
  );
}
