import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_cashier/src/features/floor/floor_page.dart';
import 'package:rizqim_cashier/src/features/order/order_page.dart';

import 'support/harness.dart';

/// Xaritadagi joy (semantika: "nom, holat, ...").
Finder place(String name, String status) =>
    find.bySemanticsLabel(RegExp('^$name, $status'));

void main() {
  testWidgets(
    'floor shows rooms in every state, badges, legend and untabled orders',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      h.state
        ..seedOrder(tableId: 1, guests: 8, waiterId: 4)
        ..seedOrder(tableId: 3, guests: 5, age: const Duration(hours: 7))
        ..reservations[2] = <String, Object?>{
          'booking_uuid': 'b-1',
          'starts_at': DateTime.utc(2026, 10, 22, 13, 40).toIso8601String(),
          'guests': 12,
        };
      final String billed = h.state.seedOrder(tableId: 4, guests: 3);
      h.state.billPrinted.add(billed);
      h.state.seedOrder(
        guests: 0,
        lines: const <(int, String)>[(4, '6')],
        age: const Duration(minutes: 4),
      );
      await pumpHome(tester, h);

      expect(find.byType(FloorPage), findsOneWidget);
      expect(find.text('Asosiy zal · 3/4'), findsOneWidget);
      expect(find.text('Hovli · 0/3'), findsOneWidget);
      expect(place('1-xona', 'Band'), findsOneWidget);
      expect(place('2-xona', 'Bron'), findsOneWidget);
      expect(place('3-xona', 'Uzoq ochiq'), findsOneWidget);
      expect(place('4-xona', 'Schyot chiqdi'), findsOneWidget);
      // Belgilar: ochiq vaqt, mehmonlar/o'rinlar, ofitsiant, bron vaqti
      expect(
        find.bySemanticsLabel(RegExp('^1-xona.*ochiq 1:35.*mehmon 8/10')),
        findsOneWidget,
      );
      expect(find.text('Botir'), findsOneWidget);
      expect(
        find.bySemanticsLabel(RegExp(r'^2-xona.*bron \d\d:40')),
        findsOneWidget,
      );
      // Izoh: holatlar sanog'i
      expect(find.byType(FloorStatusBadge), findsNWidgets(4));
      // Stolsiz buyurtma
      expect(find.text('Stolsiz va olib ketish (1)'), findsOneWidget);
      expect(find.textContaining('Olib ketish'), findsWidgets);

      await tester.tap(find.text('Hovli · 0/3'));
      await tester.pumpAndSettle();
      expect(place('Tapchan 1', "Bo'sh"), findsOneWidget);
    },
  );

  testWidgets('another device opens a room: the map updates from real-time', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    await pumpHome(tester, h);
    expect(place('2-xona', "Bo'sh"), findsOneWidget);
    final int loads = h.state.log
        .where((String l) => l == 'GET /api/v1/pos/floor')
        .length;

    await settle(
      tester,
      h.data.orders.open(channel: PosChannel.hall, tableId: 2, guestsCount: 6),
    );
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pumpAndSettle();
    expect(place('2-xona', 'Band'), findsOneWidget);
    expect(
      h.state.log.where((String l) => l == 'GET /api/v1/pos/floor').length,
      greaterThan(loads),
    );
  });

  testWidgets(
    'tap a free room → guests dialog → order opened → editor; back → occupied',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      await pumpHome(tester, h);

      await tester.tap(find.text('2-xona'));
      await tester.pumpAndSettle();
      expect(find.text('2-xona: yangi buyurtma'), findsOneWidget);
      await tester.tap(find.widgetWithText(ChoiceChip, '6'));
      await tester.tap(find.widgetWithText(ChoiceChip, 'Botir Aliyev'));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled('Ochish'));
      await tester.pumpAndSettle();

      expect(find.byType(OrderPage), findsOneWidget);
      final Map<String, Object?> order = h.state.orders.values.single;
      expect((order['table']! as Map<String, Object?>)['id'], 2);
      expect(order['guests_count'], 6);
      expect(
        (order['waiter']! as Map<String, Object?>)['name'],
        'Botir Aliyev',
      );
      expect(find.text('2-xona'), findsOneWidget);
      expect(find.text('6 mehmon'), findsOneWidget);

      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(place('2-xona', 'Band'), findsOneWidget);

      // Band xona bosilsa — o'sha buyurtma
      await tester.tap(find.text('2-xona'));
      await tester.pumpAndSettle();
      expect(find.byType(OrderPage), findsOneWidget);
      expect(h.state.orders, hasLength(1));
    },
  );

  testWidgets('a booked-soon room warns and points to bookings', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    h.state.reservations[1] = <String, Object?>{
      'booking_uuid': 'b-9',
      'starts_at': DateTime.utc(2026, 10, 22, 13, 30).toIso8601String(),
      'guests': 10,
    };
    await pumpHome(tester, h);
    await tester.tap(find.text('1-xona'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Bron kutilmoqda'), findsOneWidget);
    // Mehmonlar soni bron bo'yicha
    expect(
      tester.widget<ChoiceChip>(find.widgetWithText(ChoiceChip, '10')).selected,
      isTrue,
    );
    await tester.tap(find.widgetWithText(TextButton, 'Bronlar'));
    await tester.pumpAndSettle();
    expect(find.byType(FloorPage), findsNothing);
    expect(h.state.orders, isEmpty);
  });

  testWidgets('a tapchan with two orders asks which one (or a new one)', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    h.state
      ..seedOrder(tableId: 11, guests: 2)
      ..seedOrder(tableId: 11, guests: 3, age: const Duration(minutes: 20));
    await pumpHome(tester, h);
    await tester.tap(find.text('Hovli · 1/3'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tapchan 1'));
    await tester.pumpAndSettle();
    expect(find.text('POS1 · 2 mehmon'), findsOneWidget);
    expect(find.text('POS2 · 3 mehmon'), findsOneWidget);
    expect(buttonLabeled('Yangi buyurtma'), findsOneWidget);
    await tester.tap(find.text('POS2 · 3 mehmon'));
    await tester.pumpAndSettle();
    expect(find.byType(OrderPage), findsOneWidget);
    expect(find.textContaining('#POS2'), findsOneWidget);
  });

  testWidgets('Tez kassa: an untabled takeaway order is opened by "Yuborish"', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    await pumpHome(tester, h);
    await tester.tap(buttonLabeled('Tez kassa'));
    await tester.pumpAndSettle();
    expect(find.byType(OrderPage), findsOneWidget);
    expect(find.text('Tez kassa'), findsWidgets);
    expect(h.state.orders, isEmpty);

    await tester.tap(find.byKey(const ValueKey<String>('menu-4')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey<String>('menu-4')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey<String>('send')));
    await tester.pumpAndSettle();

    final Map<String, Object?> order = h.state.orders.values.single;
    expect(order['channel'], 'pos_takeaway');
    final List<Object?> items = order['items']! as List<Object?>;
    expect(items, hasLength(1));
    expect((items.single! as Map<String, Object?>)['quantity'], 2);
    expect((items.single! as Map<String, Object?>)['kitchen_status'], 'sent');
    expect(find.text('Olib ketish'), findsWidgets);

    await tester.pageBack();
    await tester.pumpAndSettle();
    expect(find.text('Stolsiz va olib ketish (1)'), findsOneWidget);
    expect(
      find.textContaining(const PosMoney.som(36000).format()),
      findsOneWidget,
    );
  });
}
