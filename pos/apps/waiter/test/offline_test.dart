import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_ui/rizqim_ui.dart';
import 'package:rizqim_waiter/src/features/order/order_page.dart';

import 'support/harness.dart';

bool disabled(WidgetTester tester, String label) =>
    tester.widget<RzButton>(buttonLabeled(label)).onPressed == null;

void main() {
  testWidgets('rooms list: banner when the connection is lost, then recovers', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    seedWaiterOrder(h, tableId: 1);
    await pumpHome(tester, h);
    expect(find.byType(RzOfflineBanner), findsNothing);
    expect(find.text('1-xona'), findsOneWidget);

    h.adapter.offline = true;
    await tester.pump(const Duration(seconds: 61));
    await tester.pumpAndSettle();
    expect(find.byType(RzOfflineBanner), findsOneWidget);
    expect(find.textContaining('Faqat ko\'rish mumkin'), findsOneWidget);
    // oxirgi holat ko'rinib turadi
    expect(find.text('1-xona'), findsOneWidget);

    // joyni ochib bo'lmaydi
    await tester.tap(find.bySemanticsLabel("Bo'sh, 6 ta"));
    await tester.pumpAndSettle();
    await tester.tap(find.text('3-xona'));
    await tester.pumpAndSettle();
    expect(find.text('3-xona ni ochish'), findsNothing);
    expect(
      find.textContaining('Aloqa yo\'q: joyni ochib bo\'lmaydi'),
      findsOneWidget,
    );

    // aloqa qaytdi: o'zi qayta urinadi
    h.adapter.offline = false;
    await tester.pump(const Duration(seconds: 6));
    await tester.pumpAndSettle();
    expect(find.byType(RzOfflineBanner), findsNothing);
  });

  testWidgets('order screen is view-only while offline', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    final String uuid = seedWaiterOrder(h, tableId: 1);
    markLineReady(h, uuid, 0);
    await pumpHome(tester, h);
    await tester.tap(find.text('1-xona'));
    await tester.pumpAndSettle();
    expect(find.byType(OrderPage), findsOneWidget);
    expect(disabled(tester, "Taom qo'shish"), isFalse);

    h.adapter.offline = true;
    await tester.tap(find.byTooltip('Yangilash'));
    await tester.pumpAndSettle();

    expect(find.byType(RzOfflineBanner), findsOneWidget);
    // ko'rish ishlaydi, yozuv amallari o'chiq
    expect(find.text('Osh (Choyxona oshi)'), findsOneWidget);
    expect(disabled(tester, "Taom qo'shish"), isTrue);
    expect(disabled(tester, "Schyot so'rash"), isTrue);
    expect(disabled(tester, 'Berildi'), isTrue);

    h.adapter.offline = false;
    await tester.tap(find.text('Qayta urinish'));
    await tester.pumpAndSettle();
    expect(find.byType(RzOfflineBanner), findsNothing);
    expect(disabled(tester, "Taom qo'shish"), isFalse);
    expect(disabled(tester, 'Berildi'), isFalse);
  });

  testWidgets(
    'a send that loses the connection is repeated with the same key',
    (WidgetTester tester) async {
      final WaiterHarness h = WaiterHarness();
      final String uuid = seedWaiterOrder(h, tableId: 1, sent: false);
      await pumpHome(tester, h);
      await tester.tap(find.text('1-xona'));
      await tester.pumpAndSettle();

      h.adapter.offline = true;
      await tapButton(tester, 'Yuborish (2)');
      expect(find.byType(RzOfflineBanner), findsOneWidget);
      expect(
        find.textContaining("Aloqa yo'q. Hozircha faqat ko'rish"),
        findsOneWidget,
      );
      expect(disabled(tester, 'Yuborish (2)'), isTrue);

      h.adapter.offline = false;
      await tester.tap(find.text('Qayta urinish'));
      await tester.pumpAndSettle();
      await tapButton(tester, 'Yuborish (2)');

      final List<String?> keys = h.adapter
          .to('POST', '/v1/pos/orders/$uuid/send')
          .map((r) => r.headers['Idempotency-Key'] as String?)
          .toList();
      expect(keys, hasLength(2));
      expect(keys.first, isNotNull);
      expect(keys.first, keys.last);
      expect(find.text('Oshxonada'), findsNWidgets(2));
    },
  );

  testWidgets('a stale version reloads the order and asks to repeat', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    final String uuid = seedWaiterOrder(h, tableId: 1, sent: false);
    await pumpHome(tester, h);
    await tester.tap(find.text('1-xona'));
    await tester.pumpAndSettle();

    // kassir shu orada o'zgartirdi
    h.state.orders[uuid]!['version'] = 9;
    await tapButton(tester, 'Yuborish (2)');
    expect(
      find.textContaining("Buyurtmani boshqa qurilma o'zgartirdi"),
      findsOneWidget,
    );
    await tester.pump(const Duration(seconds: 7));
    await tapButton(tester, 'Yuborish (2)');
    expect(find.text('Oshxonada'), findsNWidgets(2));
    expect(bodies(h, 'POST', '/v1/pos/orders/$uuid/send').last['version'], 9);
  });
}
