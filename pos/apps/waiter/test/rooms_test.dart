import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_waiter/src/features/order/order_page.dart';
import 'package:rizqim_waiter/src/features/rooms/rooms_page.dart';

import 'support/harness.dart';

Finder tab(String label, int count) =>
    find.bySemanticsLabel('$label, $count ta');

void main() {
  testWidgets('tabs: my orders, everything, free places', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    addOtherWaiter(h);
    final String mine = seedWaiterOrder(h, tableId: 1);
    markLineReady(h, mine, 0);
    seedWaiterOrder(
      h,
      tableId: 2,
      waiterId: otherWaiterId,
      waiterName: otherWaiterName,
      guests: 7,
    );
    await pumpHome(tester, h);

    // 7 ta joy: 2 ta band (biri meniki), 5 ta bo'sh
    expect(find.text('Mening xonalarim'), findsOneWidget);
    expect(tab('Mening', 1), findsOneWidget);
    expect(tab('Hammasi', 7), findsOneWidget);
    expect(tab("Bo'sh", 5), findsOneWidget);

    // Mening: faqat o'z buyurtmam; tayyor taom ajratib ko'rsatiladi
    expect(find.text('1-xona'), findsOneWidget);
    expect(find.text('2-xona'), findsNothing);
    expect(find.text('4 mehmon'), findsOneWidget);
    expect(find.text('1 ta taom tayyor: olib borish kerak'), findsOneWidget);
    expect(find.text('Band'), findsOneWidget);

    await tester.tap(tab('Hammasi', 7));
    await tester.pumpAndSettle();
    expect(find.text('Asosiy zal'), findsOneWidget);
    expect(find.text('Hovli'), findsOneWidget);
    expect(find.text('1-xona'), findsOneWidget);
    expect(find.text('2-xona'), findsOneWidget);
    expect(find.text('Tapchan 1'), findsOneWidget);
    // boshqa ofitsiantning buyurtmasida uning ismi
    expect(find.text(otherWaiterName), findsOneWidget);
    expect(find.text('7 mehmon'), findsOneWidget);

    await tester.tap(tab("Bo'sh", 5));
    await tester.pumpAndSettle();
    expect(find.text('1-xona'), findsNothing);
    expect(find.text('2-xona'), findsNothing);
    expect(find.text('3-xona'), findsOneWidget);
    expect(find.text('Tapchan 3'), findsOneWidget);
    expect(find.text('Ochish'), findsNWidgets(5));
  });

  testWidgets('no orders yet: the empty state points to free places', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    await pumpHome(tester, h);
    expect(find.text("Sizda ochiq buyurtma yo'q"), findsOneWidget);
    await tester.tap(buttonLabeled("Bo'sh joylar"));
    await tester.pumpAndSettle();
    expect(find.text('3-xona'), findsOneWidget);
  });

  testWidgets('opening a free place asks for guests, then shows the order', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    await pumpHome(tester, h);
    await tester.tap(tab("Bo'sh", 7));
    await tester.pumpAndSettle();
    await tester.tap(find.text('3-xona'));
    await tester.pumpAndSettle();

    expect(find.text('3-xona ni ochish'), findsOneWidget);
    expect(find.text('4'), findsOneWidget);
    await tester.tap(find.bySemanticsLabel("Mehmonni ko'paytirish"));
    await tester.pump();
    await tester.tap(find.bySemanticsLabel("Mehmonni ko'paytirish"));
    await tester.pump();
    expect(find.text('6'), findsOneWidget);
    await tapButton(tester, 'Ochish');

    expect(find.byType(OrderPage), findsOneWidget);
    expect(find.text('3-xona'), findsWidgets);
    expect(find.text('6 mehmon'), findsOneWidget);
    final List<Map<String, Object?>> opened = bodies(
      h,
      'POST',
      '/v1/pos/orders',
    ).where((Map<String, Object?> b) => b.containsKey('channel')).toList();
    expect(opened.single['table_id'], 3);
    expect(opened.single['guests_count'], 6);
    expect(opened.single['channel'], 'pos_hall');

    // orqaga: ro'yxatda endi menda
    await tester.pageBack();
    await tester.pumpAndSettle();
    expect(find.byType(RoomsPage), findsOneWidget);
    expect(tab('Mening', 1), findsOneWidget);
  });

  testWidgets('a realtime event refreshes the list', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    await pumpHome(tester, h);
    expect(find.text("Sizda ochiq buyurtma yo'q"), findsOneWidget);

    // Kassir boshqa qurilmada Botir nomiga buyurtma ochdi
    final String uuid = seedWaiterOrder(h, tableId: 4);
    h.state.emit('order.created', <String, Object?>{
      'order': <String, Object?>{
        'uuid': uuid,
        'status': 'preparing',
        'store_id': 1,
        'branch_id': 1,
        'courier_id': null,
        'updated_at': '2026-10-22T13:00:00Z',
      },
    });
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pumpAndSettle();
    expect(find.text('4-xona'), findsOneWidget);
    expect(tab('Mening', 1), findsOneWidget);
  });
}
