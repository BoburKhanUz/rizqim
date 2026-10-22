import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Holat ranglari va ikonkasi: holat faqat rang bilan emas, ikonka va yorliq bilan ham ajraladi (docs/DESIGN.md).
abstract final class FloorStatusStyle {
  /// (fon, matn/ikonka, chegara).
  static (Color background, Color foreground, Color border) colors(
    RzColors c,
    FloorTableStatus status,
  ) => switch (status) {
    FloorTableStatus.free => (c.successSoft, c.successSoftFg, c.success),
    FloorTableStatus.occupied => (c.infoSoft, c.infoSoftFg, c.info),
    FloorTableStatus.booked => (c.warningSoft, c.warningSoftFg, c.warning),
    FloorTableStatus.billPrinted => (c.brandSoft, c.brandSoftFg, c.brand),
    FloorTableStatus.overdue => (c.dangerSoft, c.dangerSoftFg, c.danger),
    FloorTableStatus.inactive => (c.surface2, c.fgMuted, c.line),
  };

  static IconData icon(FloorTableStatus status) => switch (status) {
    FloorTableStatus.free => Icons.check_circle_outline,
    FloorTableStatus.occupied => Icons.restaurant,
    FloorTableStatus.booked => Icons.event_available,
    FloorTableStatus.billPrinted => Icons.receipt_long,
    FloorTableStatus.overdue => Icons.timer_outlined,
    FloorTableStatus.inactive => Icons.block,
  };

  static IconData kindIcon(FloorTableKind kind) => switch (kind) {
    FloorTableKind.table => Icons.table_restaurant_outlined,
    FloorTableKind.room => Icons.meeting_room_outlined,
    FloorTableKind.tapchan => Icons.weekend_outlined,
  };
}

/// Xona yoki stol holati belgisi.
class FloorStatusBadge extends StatelessWidget {
  const FloorStatusBadge({super.key, required this.status});

  final FloorTableStatus status;

  @override
  Widget build(BuildContext context) {
    final (Color bg, Color fg, Color _) = FloorStatusStyle.colors(
      context.rz,
      status,
    );
    return Semantics(
      label: status.label,
      excludeSemantics: true,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(RzRadius.control),
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(FloorStatusStyle.icon(status), size: 14, color: fg),
              const SizedBox(width: 6),
              Text(status.label, style: RzType.caption.copyWith(color: fg)),
            ],
          ),
        ),
      ),
    );
  }
}

/// Xaritadagi bitta joy: backend `dining_tables` (turi, o'rinlar, `layout` x/y/w/h) va joriy holat.
@immutable
class FloorMapItem {
  const FloorMapItem({
    required this.id,
    required this.label,
    required this.rect,
    this.kind = FloorTableKind.table,
    this.status = FloorTableStatus.free,
    this.seats,
    this.guests,
    this.openedAt,
    this.bookingAt,
    this.amount,
    this.waiter,
  });

  final String id;

  /// "Xona 3", "T-2".
  final String label;

  /// Xarita birliklaridagi joy va o'lcham (masalan 0–100 to'r); [FloorMap] ekranga moslab cho'zadi.
  final Rect rect;
  final FloorTableKind kind;
  final FloorTableStatus status;
  final int? seats;
  final int? guests;

  /// Hisob ochilgan vaqt: "ochiq soatlar" belgisi.
  final DateTime? openedAt;

  /// Yaqin bron vaqti.
  final DateTime? bookingAt;

  /// Joriy hisob summasi.
  final PosMoney? amount;

  /// Ofitsiant ismi (qisqa).
  final String? waiter;
}

/// Zal xaritasi: xonalar, tapchanlar va stollar o'z joyida, holat rangi va belgilari bilan (ochiq soatlar,
/// mehmonlar, bron vaqti). Xarita mavjud joyga proporsiyani saqlab sig'adi.
class FloorMap extends StatelessWidget {
  const FloorMap({
    super.key,
    required this.items,
    this.onTap,
    this.onLongPress,
    this.selectedId,
    this.now,
    this.layoutSize,
    this.padding = const EdgeInsets.all(12),
    this.showWaiter = false,
  });

  final List<FloorMapItem> items;
  final ValueChanged<FloorMapItem>? onTap;
  final ValueChanged<FloorMapItem>? onLongPress;
  final String? selectedId;

  /// "Hozir" (ochiq soatlar uchun); `null` — `DateTime.now()`. Ota widget har daqiqada qayta quradi.
  final DateTime? now;

  /// Xarita o'lchami birliklarda; `null` — joylarning eng chetidan.
  final Size? layoutSize;
  final EdgeInsets padding;

  /// Ofitsiant ismi ham belgi sifatida ko'rinadi ([FloorMapItem.waiter]).
  final bool showWaiter;

  Size get _extent {
    if (layoutSize != null) return layoutSize!;
    var w = 1.0;
    var h = 1.0;
    for (final FloorMapItem i in items) {
      if (i.rect.right > w) w = i.rect.right;
      if (i.rect.bottom > h) h = i.rect.bottom;
    }
    return Size(w, h);
  }

  @override
  Widget build(BuildContext context) {
    final DateTime at = now ?? DateTime.now();
    final Size extent = _extent;
    return Padding(
      padding: padding,
      child: LayoutBuilder(
        builder: (BuildContext context, BoxConstraints box) {
          final double scale = <double>[
            box.maxWidth / extent.width,
            box.maxHeight / extent.height,
          ].reduce((double a, double b) => a < b ? a : b);
          final Size size = Size(extent.width * scale, extent.height * scale);
          return Align(
            alignment: Alignment.topLeft,
            child: SizedBox.fromSize(
              size: size,
              child: Stack(
                children: <Widget>[
                  for (final FloorMapItem item in items)
                    Positioned(
                      left: item.rect.left * scale,
                      top: item.rect.top * scale,
                      width: item.rect.width * scale,
                      height: item.rect.height * scale,
                      child: Padding(
                        padding: const EdgeInsets.all(3),
                        child: FloorTile(
                          item: item,
                          now: at,
                          selected: item.id == selectedId,
                          showWaiter: showWaiter,
                          onTap: onTap == null ? null : () => onTap!(item),
                          onLongPress: onLongPress == null
                              ? null
                              : () => onLongPress!(item),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

/// Xaritadagi bitta joy kartochkasi (xaritadan tashqarida ro'yxat ko'rinishida ham ishlatsa bo'ladi).
class FloorTile extends StatelessWidget {
  const FloorTile({
    super.key,
    required this.item,
    required this.now,
    this.selected = false,
    this.onTap,
    this.onLongPress,
    this.showWaiter = false,
  });

  final FloorMapItem item;
  final DateTime now;
  final bool selected;

  /// Ofitsiant belgisi ([FloorMapItem.waiter]).
  final bool showWaiter;
  final VoidCallback? onTap;
  final VoidCallback? onLongPress;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final (Color bg, Color fg, Color border) = FloorStatusStyle.colors(
      c,
      item.status,
    );
    final List<_Badge> badges = <_Badge>[
      if (item.openedAt != null)
        _Badge(
          Icons.schedule,
          formatOpenDuration(now.difference(item.openedAt!)),
          'ochiq',
        ),
      if (item.guests != null || item.seats != null)
        _Badge(
          Icons.people_outline,
          item.guests == null
              ? '${item.seats}'
              : (item.seats == null
                    ? '${item.guests}'
                    : '${item.guests}/${item.seats}'),
          'mehmon',
        ),
      if (item.bookingAt != null)
        _Badge(Icons.event, formatReceiptTime(item.bookingAt!), 'bron'),
      if (showWaiter && item.waiter != null)
        _Badge(Icons.badge_outlined, item.waiter!, 'ofitsiant'),
    ];
    final String semantics = <String>[
      item.label,
      item.status.label,
      for (final _Badge b in badges) '${b.semantics} ${b.text}',
      if (item.amount != null) item.amount!.format(),
    ].join(', ');

    return Semantics(
      button: onTap != null,
      selected: selected,
      label: semantics,
      excludeSemantics: true,
      child: Material(
        color: Color.alphaBlend(bg, c.surface),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(
            item.kind == FloorTableKind.table
                ? RzRadius.control
                : RzRadius.card,
          ),
          side: BorderSide(
            color: selected ? c.fg : border,
            width: selected ? 3 : 1.5,
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          onLongPress: onLongPress,
          child: Padding(
            padding: const EdgeInsets.all(8),
            child: LayoutBuilder(
              builder: (BuildContext context, BoxConstraints box) {
                // Kichik joyda faqat nom va holat ikonkasi (kichraytiriladi); o'rtachada belgilar; kattada hammasi
                final Widget header = Row(
                  children: <Widget>[
                    Icon(
                      FloorStatusStyle.kindIcon(item.kind),
                      size: 16,
                      color: fg,
                    ),
                    const SizedBox(width: 4),
                    Flexible(
                      child: Text(
                        item.label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: RzType.headline.copyWith(color: c.fg),
                      ),
                    ),
                    const SizedBox(width: 4),
                    Icon(
                      FloorStatusStyle.icon(item.status),
                      size: 16,
                      color: fg,
                    ),
                  ],
                );
                if (box.maxHeight < 60 || box.maxWidth < 64) {
                  return FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.topLeft,
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Text(
                          item.label,
                          maxLines: 1,
                          style: RzType.headline.copyWith(color: c.fg),
                        ),
                        const SizedBox(width: 4),
                        Icon(
                          FloorStatusStyle.icon(item.status),
                          size: 16,
                          color: fg,
                        ),
                      ],
                    ),
                  );
                }
                final Widget badgeRow = SizedBox(
                  height: 18,
                  child: Wrap(
                    spacing: 6,
                    clipBehavior: Clip.hardEdge,
                    children: <Widget>[
                      for (final _Badge b in badges)
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: <Widget>[
                            Icon(b.icon, size: 13, color: c.fgMuted),
                            const SizedBox(width: 2),
                            Text(
                              b.text,
                              style: RzType.caption.copyWith(color: c.fg),
                            ),
                          ],
                        ),
                    ],
                  ),
                );
                final bool full = box.maxHeight >= 96;
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    header,
                    if (full)
                      Text(
                        item.status.label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: RzType.caption.copyWith(color: fg),
                      ),
                    const Spacer(),
                    if (full && item.amount != null)
                      Text(
                        item.amount!.format(),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: RzType.footnote.copyWith(
                          color: c.fg,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    if (badges.isNotEmpty) badgeRow,
                  ],
                );
              },
            ),
          ),
        ),
      ),
    );
  }
}

class _Badge {
  const _Badge(this.icon, this.text, this.semantics);

  final IconData icon;
  final String text;
  final String semantics;
}
