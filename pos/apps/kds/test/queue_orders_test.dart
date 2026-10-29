import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_kds/src/widgets/ticket_card.dart';

import 'support/fake_kds.dart';
import 'support/helpers.dart';

/// P6-14: avval to'langan kassa buyurtmasi (yopilgan, `delivered`) raqami tayyor bo'lguncha oddiy chipta bo'lib doskada
/// turadi; "Raqam N tayyor" (yig'ish) tugmasi uni tayyor qiladi va chipta doskadan ketadi.
void main() {
  testWidgets('a paid-first counter order is a normal ticket with its number and a running timer', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = KdsHarness();
    h.state
      ..addQueueOrder(number: 17, age: const Duration(minutes: 4))
      ..addOrder(
        age: const Duration(minutes: 1),
        items: const <(String, String, String, List<String>)>[
          ('Somsa', '4', 'pcs', <String>[]),
        ],
      );
    await pumpBoard(tester, h);

    // The queue number is the ticket's headline; the order code and "To'langan" are in the small line
    expect(find.byKey(const ValueKey<String>('queue-number')), findsOneWidget);
    expect(find.text('№17'), findsOneWidget);
    expect(find.textContaining("To'langan"), findsOneWidget);
    expect(find.textContaining('Yetkazildi'), findsNothing);
    expect(find.byType(TicketCard), findsNWidgets(2));
    expect(find.text('4:00'), findsOneWidget);

    // The timer keeps running while the number is cooked (a delivered order's clock would have stopped)
    await advance(tester, h, const Duration(minutes: 2));
    expect(find.text('6:00'), findsOneWidget);
  });

  testWidgets('bumping the last line of a paid-first order makes its number ready and the ticket leaves the board', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = KdsHarness();
    h.state.addQueueOrder(number: 5);
    await pumpBoard(tester, h);

    await tester.tap(buttonLabeled('Hammasi tayyor'));
    await tester.pumpAndSettle();
    await advance(tester, h, const Duration(seconds: 16));

    expect(find.byType(TicketCard), findsNothing);
    expect(h.state.log.where((String l) => l.contains('/ready')), isNotEmpty);
    expect(h.state.orders.single['pickup_status'], 'ready');
  });

  testWidgets('the expo button marks the number ready from the kitchen (closed order, no status endpoint)', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = KdsHarness();
    final String uuid = h.state.addQueueOrder(number: 21);
    await pumpBoard(tester, h);

    expect(buttonLabeled('Raqam 21 tayyor'), findsOneWidget);
    await tester.tap(buttonLabeled('Raqam 21 tayyor'));
    await tester.pumpAndSettle();

    expect(h.state.log, contains('POST /api/v1/pos/orders/$uuid/ready'));
    expect(h.state.log.where((String l) => l.endsWith('/status')), isEmpty);
    expect(h.state.orders.single['pickup_status'], 'ready');
    await advance(tester, h, const Duration(seconds: 16));
    expect(find.byType(TicketCard), findsNothing);
  });

  testWidgets('a ticket without a queue number keeps its code and its normal buttons', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = KdsHarness();
    h.state.addOrder(
      items: const <(String, String, String, List<String>)>[
        ('Somsa', '4', 'pcs', <String>[]),
      ],
    );
    await pumpBoard(tester, h);

    expect(find.textContaining('№'), findsNothing);
    expect(find.byKey(const ValueKey<String>('expo-ready')), findsNothing);
    expect(buttonLabeled('Hammasi tayyor'), findsOneWidget);
  });

  testWidgets('a view-only cook sees the number but no expo button', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = KdsHarness();
    h.state
      ..permissions = <String>['orders.view']
      ..addQueueOrder(number: 3);
    await pumpBoard(tester, h);

    expect(find.text('№3'), findsOneWidget);
    expect(find.byKey(const ValueKey<String>('expo-ready')), findsNothing);
    expect(buttonLabeled('Hammasi tayyor'), findsNothing);
  });
}
