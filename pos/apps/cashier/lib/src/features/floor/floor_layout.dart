import 'dart:math' as math;
import 'dart:ui';

import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';

/// Xarita to'ri: joylarning `layout` i to'liq bo'lmasa (panelda xarita chizilmagan) joylar tartib bilan to'rga
/// teriladi; ustunlar soni joylar soniga qarab ([gridColumnsFor]) — kam joyli zalda kartochkalar katta.
int gridColumnsFor(int count) => switch (count) {
  <= 4 => 2,
  <= 9 => 3,
  <= 16 => 4,
  _ => 5,
};
const double _cellW = 25;
const double _cellH = 17;

/// Zal xaritasi: joy rasmlari ([FloorMapItem]) va xarita o'lchami.
class HallMap {
  const HallMap(this.items, this.size);

  final List<FloorMapItem> items;
  final Size size;
}

/// [hall] joylarini xarita elementlariga aylantiradi: holat, ochiq vaqt, mehmonlar, ofitsiant, summa, bron vaqti.
HallMap hallMap(FloorHall hall) {
  final List<FloorTable> tables = hall.tables;
  final bool drawn =
      tables.isNotEmpty &&
      tables.every((FloorTable t) {
        final PosLayout? l = t.layout;
        return l != null &&
            l.x != null &&
            l.y != null &&
            (l.w ?? 0) > 0 &&
            (l.h ?? 0) > 0;
      });
  final List<FloorMapItem> items = <FloorMapItem>[];
  final int cols = gridColumnsFor(tables.length);
  for (var i = 0; i < tables.length; i++) {
    final FloorTable t = tables[i];
    final Rect rect = drawn
        ? Rect.fromLTWH(t.layout!.x!, t.layout!.y!, t.layout!.w!, t.layout!.h!)
        : Rect.fromLTWH(
            (i % cols) * _cellW,
            (i ~/ cols) * _cellH,
            _cellW,
            _cellH,
          );
    items.add(floorMapItem(t, rect));
  }
  if (drawn) return HallMap(items, _extent(items));
  final int rows = math.max(1, (tables.length + cols - 1) ~/ cols);
  return HallMap(items, Size(cols * _cellW, rows * _cellH));
}

Size _extent(List<FloorMapItem> items) {
  var w = 1.0;
  var h = 1.0;
  for (final FloorMapItem i in items) {
    w = math.max(w, i.rect.right);
    h = math.max(h, i.rect.bottom);
  }
  return Size(w, h);
}

/// Bitta joy: ochiq buyurtmalar yig'indisi (mehmonlar, summa), eng eski ochilgan vaqt, birinchi ofitsiant.
FloorMapItem floorMapItem(FloorTable t, Rect rect) {
  final List<FloorOrder> orders = t.orders;
  DateTime? opened;
  for (final FloorOrder o in orders) {
    final DateTime? at = o.openedAt;
    if (at != null && (opened == null || at.isBefore(opened))) opened = at;
  }
  final String? waiter = orders
      .map((FloorOrder o) => o.waiter?.name)
      .whereType<String>()
      .map((String n) => n.split(' ').first)
      .firstOrNull;
  return FloorMapItem(
    id: '${t.id}',
    label: t.name,
    rect: rect,
    kind: t.kind,
    status: t.status,
    seats: t.seats,
    guests: orders.isEmpty
        ? null
        : orders.fold<int>(
            0,
            (int s, FloorOrder o) => s + (o.guestsCount ?? 0),
          ),
    openedAt: opened,
    bookingAt: t.orders.isEmpty ? t.reservation?.startsAt?.toLocal() : null,
    amount: orders.isEmpty
        ? null
        : PosMoney.sum(orders.map((FloorOrder o) => o.totalPrice)),
    waiter: waiter,
    billRequested: t.billRequested,
  );
}

/// Holatlar bo'yicha sanoq (xarita ostidagi izoh).
Map<FloorTableStatus, int> statusCounts(Iterable<FloorTable> tables) {
  final Map<FloorTableStatus, int> out = <FloorTableStatus, int>{};
  for (final FloorTable t in tables) {
    out[t.status] = (out[t.status] ?? 0) + 1;
  }
  return out;
}
