import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_waiter/src/features/rooms/rooms_page.dart';

import 'support/harness.dart';

/// P4-21: the waiter's phone reads the plan from the bootstrap. Nothing the waiter does depends on the plan (rooms, orders,
/// rounds to the kitchen); an unpaid invoice shows a plain notice and the phone keeps working.
void main() {
  testWidgets('no notice for a store that has paid; the rooms are there', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    h.state.entitlements = fakeEntitlements();
    await pumpHome(tester, h);
    expect(find.byType(RoomsPage), findsOneWidget);
    expect(find.textContaining("To'lov muddati o'tdi"), findsNothing);
    expect(find.textContaining('Sozlamalar yopiq'), findsNothing);
    expect(h.services.session.entitlements.planName, 'Asosiy');
  });

  testWidgets(
    'an overdue invoice: the days to the lock, in plain words, and the rooms still work',
    (WidgetTester tester) async {
      final WaiterHarness h = WaiterHarness();
      final DateTime lockAt = h.now.add(const Duration(days: 4));
      h.state.entitlements = fakeEntitlements(
        plan: 'start',
        status: 'past_due',
        lockAt: lockAt,
        notice: fakeBillingNotice(lockAt: lockAt),
      );
      await pumpHome(tester, h);
      expect(
        find.text("To'lov muddati o'tdi — 4 kundan keyin sozlamalar yopiladi"),
        findsOneWidget,
      );
      expect(
        find.textContaining('Kassa ishlashda davom etadi'),
        findsOneWidget,
      );
      expect(find.byType(RoomsPage), findsOneWidget);
      expect(find.text('Mening xonalarim'), findsOneWidget);
    },
  );

  testWidgets(
    'locked: "Sozlamalar yopiq", and when the owner pays the plan is read again and the notice goes',
    (WidgetTester tester) async {
      final WaiterHarness h = WaiterHarness();
      h.state.entitlements = fakeEntitlements(
        plan: 'start',
        status: 'locked',
        notice: fakeBillingNotice(locked: true, daysOverdue: 9),
      );
      await pumpHome(tester, h);
      expect(find.text("Sozlamalar yopiq — to'lov qiling"), findsOneWidget);
      expect(find.byType(RoomsPage), findsOneWidget);

      h.state.entitlements = fakeEntitlements();
      await settle(tester, h.services.session.refreshEntitlements());
      await tester.pumpAndSettle();
      expect(find.text("Sozlamalar yopiq — to'lov qiling"), findsNothing);
    },
  );

  testWidgets('an old server without the block shows nothing', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    await pumpHome(tester, h);
    expect(find.byType(RoomsPage), findsOneWidget);
    expect(h.services.session.entitlements.has(PosFeature.bookings), isTrue);
    expect(h.services.session.entitlements.notice, isNull);
  });
}
