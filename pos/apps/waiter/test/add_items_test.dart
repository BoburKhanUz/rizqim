import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_waiter/src/common/errors.dart';

import 'order_test.dart' show openAddSheet, openOwnOrder;
import 'support/harness.dart';

void main() {
  testWidgets('search, stop-list and the cart', (WidgetTester tester) async {
    final WaiterHarness h = WaiterHarness();
    // Non stop-listda
    ((h.state.availability['stopped']! as Map<String, Object?>)['products']!
            as List<int>)
        .add(2);
    await openOwnOrder(tester, h);
    await openAddSheet(tester);

    // stop-listdagi taom tez tugmada ham, ro'yxatda ham sotilmaydi
    expect(find.text('+ Non'), findsNothing);
    await tester.tap(find.text('+ Choy'));
    await tester.pump();
    await tester.tap(find.text('+ Choy  x1'));
    await tester.pump();
    expect(find.text('+ Choy  x2'), findsOneWidget);
    expect(find.text('Stop-listda'), findsOneWidget);

    // qidiruv toifalarni yashiradi
    await tester.enterText(find.byType(TextField), 'osh');
    await tester.pumpAndSettle();
    expect(find.text('Osh'), findsOneWidget);
    expect(find.text('Choy'), findsNothing);
    await tester.enterText(find.byType(TextField), 'xyz');
    await tester.pumpAndSettle();
    expect(find.text('Taom topilmadi'), findsOneWidget);
    await tester.enterText(find.byType(TextField), '');
    await tester.pumpAndSettle();

    // savat: izoh qo'shish va yuborish
    await tester.tap(find.bySemanticsLabel('Savat, 1 qator'));
    await tester.pumpAndSettle();
    expect(find.text('Yangi bosqich'), findsOneWidget);
    await tester.tap(find.byTooltip('Izoh'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField).last, 'shakarsiz');
    await tapButton(tester, 'Saqlash');
    expect(find.text('Izoh: shakarsiz'), findsOneWidget);
    await tapButton(tester, 'Tayyor');
    await tapButton(tester, 'Yuborish');

    final Map<String, Object?> body = bodies(h, 'POST', '/rounds').single;
    final Map<String, Object?> line =
        (body['items']! as List<Object?>).single! as Map<String, Object?>;
    expect(line['quantity'], '2.000');
    expect(line['note'], 'shakarsiz');
    expect(find.text('Izoh: shakarsiz'), findsOneWidget);
  });

  testWidgets('a kg item below the minimum is refused by the keypad', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    await openOwnOrder(tester, h);
    await openAddSheet(tester);
    await tester.tap(find.widgetWithText(ChoiceChip, 'Taomlar'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Osh'));
    await tester.pumpAndSettle();
    for (final String key in <String>['0', '.', '2']) {
      await tester.tap(
        find.descendant(
          of: find.byType(PosNumericKeypad),
          matching: find.text(key),
        ),
      );
      await tester.pump();
    }
    expect(find.textContaining('Kamida 0.5'), findsOneWidget);
    expect(
      tester
          .widget<FilledButton>(
            find.descendant(
              of: buttonLabeled("Qo'shish"),
              matching: find.byType(FilledButton),
            ),
          )
          .onPressed,
      isNull,
    );
  });

  test('error texts are plain Uzbek', () {
    expect(
      describeError(
        const PosApprovalRequired(
          message: 'x',
          statusCode: 403,
          reason: 'approval_required',
        ),
      ),
      contains('menejer tasdig'),
    );
    expect(
      describeError(
        const PosApprovalRejected(
          message: 'x',
          statusCode: 403,
          reason: 'approver_not_allowed',
        ),
      ),
      contains('tasdiqlay olmaydi'),
    );
    expect(
      describeError(const PosNetworkException(message: 'x')),
      contains("Aloqa yo'q"),
    );
    expect(describeError(const PosApprovalCancelled()), isEmpty);
  });
}
