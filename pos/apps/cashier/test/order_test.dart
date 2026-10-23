import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/features/order/order_page.dart';

import 'support/harness.dart';
import 'support/order_helpers.dart';

void main() {
  testWidgets(
    'a round of 3 items (one kg dish) is added and sent in 6 taps; osh goes to its station printer',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      final String uuid = h.state.seedOrder(tableId: 1, guests: 6);
      await pumpHome(tester, h);
      await openPlace(tester, '1-xona');
      expect(find.byType(OrderPage), findsOneWidget);
      // 1-bosqich (seed): osh va choy, oshxonada
      expect(find.text('1-bosqich'), findsOneWidget);
      expect(find.text('Oshxonada'), findsNWidgets(2));
      final TapCounter taps = TapCounter(tester);

      await taps.tap(menuTile(1)); // Choy
      await taps.tap(menuTile(2)); // Non
      await taps.tap(menuTile(3)); // Osh → kg klaviatura
      expect(find.text('Qozonda qoldi: 8'), findsOneWidget);
      await taps.tap(find.bySemanticsLabel('1').first);
      await taps.tap(buttonLabeled("Qo'shish: 1 kg (guruch)"));
      expect(find.text('Yangi bosqich (2) · ${som(129000)}'), findsOneWidget);
      await taps.tap(sendButton);

      expect(taps.count, lessThanOrEqualTo(6));
      final Map<String, Object?> order = h.state.orders[uuid]!;
      final List<Map<String, Object?>> round2 = items(order)
          .where((Map<String, Object?> i) => i['round_no'] == 2)
          .toList();
      expect(
        round2.map((Map<String, Object?> i) => i['product_name']),
        <String>['Choy', 'Non', 'Osh'],
      );
      expect(
        round2.every((Map<String, Object?> i) => i['kitchen_status'] == 'sent'),
        isTrue,
      );
      expect(find.text('2-bosqich'), findsOneWidget);
      // Faqat oshning sexi printeri bor: bitta marka, 2-bosqich
      final String ticket = h.printerFor('server-2').documents.single.text;
      expect(ticket, contains('OSH'));
      expect(ticket, contains('1-xona'));
      expect(ticket, contains('Bosqich: 2'));
      expect(ticket, contains('1 kg (guruch) Osh'));
      expect(ticket, isNot(contains('Choynak')));
      expect(ticket, isNot(contains('Non')));
      // Kassa printeriga marka chiqmaydi
      expect(h.printerFor('server-1').received, isEmpty);
    },
  );

  testWidgets(
    'pieces merge, stepper changes quantity, tea-bread quick buttons',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      final String uuid = h.state.seedOrder(tableId: 1);
      await pumpHome(tester, h);
      await openPlace(tester, '1-xona');

      await tester.tap(find.byKey(const ValueKey<String>('quick-1')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('quick-1')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('quick-2')));
      await tester.pumpAndSettle();
      Finder inDraft(int key, Finder f) => find.descendant(
        of: find.byKey(ValueKey<String>('draft-$key')),
        matching: f,
      );
      expect(inDraft(1, find.text('2')), findsOneWidget);
      expect(inDraft(2, find.text('1')), findsOneWidget);
      await tester.tap(inDraft(2, find.byIcon(Icons.add)));
      await tester.pumpAndSettle();
      expect(inDraft(2, find.text('2')), findsOneWidget);
      await tester.tap(inDraft(2, find.byIcon(Icons.remove)));
      await tester.pumpAndSettle();
      // 1 da "−" o'rnida o'chirish
      await tester.tap(inDraft(2, find.byIcon(Icons.delete_outline)));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey<String>('draft-2')), findsNothing);
      await tester.tap(buttonLabeled('Saqlash'));
      await tester.pumpAndSettle();

      final List<Map<String, Object?>> round2 = items(h.state.orders[uuid]!)
          .where((Map<String, Object?> i) => i['round_no'] == 2)
          .toList();
      expect(round2, hasLength(1));
      expect(round2.single['quantity'], 2);
      // Saqlandi, yuborilmadi
      expect(round2.single['kitchen_status'], 'pending');
      expect(find.text('Yuborilmagan'), findsOneWidget);
      expect(find.text('Yuborish (1)'), findsOneWidget);
      await tester.tap(sendButton);
      await tester.pumpAndSettle();
      expect(items(h.state.orders[uuid]!).last['kitchen_status'], 'sent');
    },
  );

  testWidgets('required modifiers open the dialog; options reach the server', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    final Map<String, Object?> group =
        ((h.state.products[3]['modifier_groups']! as List<Object?>).single!
            as Map<String, Object?>);
    group
      ..['is_required'] = true
      ..['max_select'] = 1;
    final String uuid = h.state.seedOrder(tableId: 1);
    await pumpHome(tester, h);
    await openPlace(tester, '1-xona');

    await tester.tap(menuTile(4));
    await tester.pumpAndSettle();
    expect(find.text('Majburiy · 1 ta tanlang'), findsOneWidget);
    await tester.tap(find.bySemanticsLabel(RegExp('^Piyoz')));
    await tester.pumpAndSettle();
    await tester.tap(find.textContaining("Qo'shish ·"));
    await tester.pumpAndSettle();
    expect(find.text('+ Piyoz'), findsOneWidget);
    await tester.tap(sendButton);
    await tester.pumpAndSettle();

    final Map<String, Object?> line = items(h.state.orders[uuid]!).last;
    expect(line['product_name'], 'Shashlik');
    expect(
      ((line['modifiers']! as List<Object?>).single!
          as Map<String, Object?>)['name'],
      'Piyoz',
    );
    expect(line['total_price'], '20000.00');
  });

  testWidgets(
    'long press: note, seat and hold for one line; the hold round keeps lines until "Hozir yuborish"',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      final String uuid = h.state.seedOrder(tableId: 1);
      await pumpHome(tester, h);
      await openPlace(tester, '1-xona');

      await tester.longPress(menuTile(4));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(ActionChip, 'piyozsiz'));
      await tester.ensureVisible(find.widgetWithText(ChoiceChip, '3'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(ChoiceChip, '3'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Ushlab turish').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Ushlab turish').last);
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled("Qo'shish"));
      await tester.pumpAndSettle();
      expect(find.text("Shashlik · 3-o'rin"), findsOneWidget);
      expect(find.text('! piyozsiz'), findsOneWidget);
      expect(find.text('Ushlab turiladi'), findsOneWidget);
      await tester.tap(menuTile(1));
      await tester.pumpAndSettle();
      await tester.tap(sendButton);
      await tester.pumpAndSettle();

      List<Map<String, Object?>> round2() =>
          items(h.state.orders[uuid]!)
              .where((Map<String, Object?> i) => i['round_no'] == 2)
              .toList();
      final Map<String, Object?> shashlik = round2().first;
      expect(shashlik['fire_mode'], 'hold');
      expect(shashlik['kitchen_status'], 'pending');
      expect(shashlik['note'], 'piyozsiz');
      expect(shashlik['seat_no'], 3);
      expect(round2().last['kitchen_status'], 'sent');
      expect(find.text('Ushlab turilgan: 1 ta taom'), findsOneWidget);

      // Butun bosqich "ushlab turish" bilan
      await tester.tap(menuTile(2));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Ushlab turish').first);
      await tester.pumpAndSettle();
      expect(find.text('Saqlash (ushlab)'), findsOneWidget);
      await tester.tap(sendButton);
      await tester.pumpAndSettle();
      expect(items(h.state.orders[uuid]!).last['fire_mode'], 'hold');
      expect(find.text('Ushlab turilgan: 2 ta taom'), findsOneWidget);

      await tester.tap(find.text('Hozir yuborish'));
      await tester.pumpAndSettle();
      expect(
        items(
          h.state.orders[uuid]!,
        ).where((Map<String, Object?> i) => i['kitchen_status'] == 'pending'),
        isEmpty,
      );
      expect(find.textContaining('Ushlab turilgan:'), findsNothing);
    },
  );

  testWidgets('a kg dish below the minimum cannot be added', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    h.state.seedOrder(tableId: 1);
    await pumpHome(tester, h);
    await openPlace(tester, '1-xona');
    await tester.tap(menuTile(3));
    await tester.pumpAndSettle();
    await tester.tap(find.bySemanticsLabel('Nuqta'));
    await tester.tap(find.bySemanticsLabel('3').last);
    await tester.pumpAndSettle();
    expect(find.text('Kamida 0.5 kg (guruch)'), findsOneWidget);
    await tester.tap(find.bySemanticsLabel("O'chirish").last);
    await tester.tap(find.bySemanticsLabel('7').last);
    await tester.pumpAndSettle();
    expect(find.text('Qadam: 0.5 kg (guruch)'), findsOneWidget);
    await tester.tap(find.bySemanticsLabel("O'chirish").last);
    await tester.tap(find.bySemanticsLabel('5').last);
    await tester.pumpAndSettle();
    await tester.tap(buttonLabeled("Qo'shish: 0.5 kg (guruch)"));
    await tester.pumpAndSettle();
    expect(find.text('0.5 kg (guruch)'), findsOneWidget);
  });

  testWidgets('sold-out dishes are marked and cannot be tapped', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    ((h.state.availability['sold_out']! as Map<String, Object?>)['variants']!
            as List<int>)
        .add(4);
    h.state.seedOrder(tableId: 1);
    await pumpHome(tester, h);
    await openPlace(tester, '1-xona');
    expect(
      find.bySemanticsLabel(RegExp(r'^Shashlik, .*Tugadi')),
      findsOneWidget,
    );
    await tester.tap(menuTile(4));
    await tester.pumpAndSettle();
    expect(find.textContaining('Yangi bosqich'), findsNothing);
    expect(FakePosState.variantUuid(4), isNotEmpty);
  });
}
