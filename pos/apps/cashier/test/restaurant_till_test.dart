import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/counter/counter_page.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';
import 'package:rizqim_cashier/src/features/shift/shift_report_mapping.dart';

import 'support/counter.dart';
import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';
import 'support/restaurant.dart';

/// P6-22 / P6-23 / P6-25 at the till: tips on the X/Z (apart from the sales), the tip payout from the drawer, clock in
/// and out, the table plate on a counter order.
void main() {
  /// A bill of 209 000 paid in cash with a [tip] on top (to the waiter Botir).
  Future<void> paidWithTip(
    WidgetTester tester,
    Restaurant r,
    String uuid, {
    int tip = 11000,
  }) async {
    final PosData data = r.p.h.data;
    final PosOrder order = await settle(tester, data.orders.get(uuid));
    final PosBilling billing = await settle(
      tester,
      data.billing.createBills(uuid, version: order.version),
    );
    await settle(
      tester,
      data.billing.pay(
        billing.bills.single.uuid,
        tenders: <PosTenderInput>[
          PosTenderInput.cash(
            billing.bills.single.dueAmount,
            tendered: billing.bills.single.dueAmount + PosMoney.som(tip),
            tip: PosMoney.som(tip),
            tipFromChange: true,
          ),
        ],
        shiftUuid: r.p.state.shift!['uuid'] as String,
        tipStaffId: 4,
      ),
    );
  }

  testWidgets(
    'X report: tips in their own section, the cash tips in the drawer; the slip prints them apart from the sales',
    (WidgetTester tester) async {
      final Restaurant r = Restaurant();
      final String uuid = r.order(waiterId: 4);
      await pumpHome(tester, r.p.h);
      await paidWithTip(tester, r, uuid);
      await openSection(tester, 'Smena');
      await tapKey(tester, 'x-report');

      expect(find.text('Choy puli (tushum emas)'), findsOneWidget);
      expect(find.byKey(const ValueKey<String>('x-cash-tips')), findsOneWidget);
      expect(find.text('Jami (1)'), findsOneWidget);
      expect(find.text('  Botir Aliyev'), findsOneWidget);
      await tapButton(tester, 'Chop etish');

      final String slip = r.p.receipts.last;
      final int sales = slip.indexOf('SAVDO');
      final int tips = slip.indexOf('CHOY PULI (tushum emas)');
      final int cash = slip.indexOf('KASSA (NAQD)');
      expect(
        <bool>[sales >= 0, tips > sales, cash > tips],
        <bool>[true, true, true],
      );
      expect(slip, contains('Kirim: Choy puli (naqd)'));
      // Sales stay the bill total (209 000); the expected cash has the cash tip: 100 000 + 209 000 + 11 000
      expect(slip.substring(sales, tips), contains('209 000'));
      expect(slip.substring(sales, tips), isNot(contains('220 000')));
      expect(slip.substring(cash), contains('320 000'));
    },
  );

  test('report labels: the tip payout and the payroll are named but not in the manual picker', () {
    const PosCashMovement tips = PosCashMovement(
      uuid: 'm1',
      type: 'wage',
      category: 'tips',
      amount: PosMoney.som(5000),
    );
    const PosCashMovement payroll = PosCashMovement(
      uuid: 'm2',
      type: 'wage',
      category: 'payroll',
      amount: PosMoney.som(5000),
    );
    expect(movementLabel(tips), 'Ish haqi · Choy puli');
    expect(movementLabel(payroll), 'Ish haqi · Ish haqi (hisob-kitob)');
    expect(
      movementCategories[PosCashMovementType.wage]!.keys,
      isNot(contains('tips')),
    );
    expect(
      movementCategories[PosCashMovementType.wage]!.keys,
      isNot(contains('payroll')),
    );
  });

  testWidgets(
    'tip payout: a cashier without tips.manage asks the manager, then the cash tip goes to the waiter (wage / tips)',
    (WidgetTester tester) async {
      final Restaurant r = Restaurant();
      r.p.state.rolePermissions['manager']!.add('tips.manage');
      final String uuid = r.order(waiterId: 4);
      await pumpHome(tester, r.p.h);
      await paidWithTip(tester, r, uuid);
      await openSection(tester, 'Smena');

      await tapKey(tester, 'tip-payout');
      expect(find.text("Menejer tasdig'i"), findsOneWidget);
      await typePin(tester, '5926');
      expect(find.text('Choy pulini berish'), findsWidgets);
      // The only tipped waiter is picked, with what is his
      expect(
        tester
            .widget<TextField>(
              find.byKey(const ValueKey<String>('tip-payout-amount')),
            )
            .controller!
            .text,
        '11 000',
      );
      await tapKey(tester, 'tip-payout-submit');

      final Map<String, Object?> m = r.p.backend.movements.last;
      expect(
        <Object?>[m['type'], m['category'], m['amount']],
        <Object?>['wage', 'tips', '11000.00'],
      );
      expect(
        (m['employee'] as Map<String, Object?>?)?['id'] ?? m['employee_id'],
        4,
      );
      // The X: the payout is its own line, not wages
      final PosShiftWithReport x = await settle(
        tester,
        r.p.h.data.shifts.current(),
      );
      final ShiftReportView v = ShiftReportView(x.report!);
      expect(v.tipPayouts, const PosMoney.som(11000));
      expect(v.wages, PosMoney.zero);
      expect(v.cashTipsLeft, PosMoney.zero);
    },
  );

  testWidgets('clock in and out from the top bar (staff_pay.clock)', (
    WidgetTester tester,
  ) async {
    final Restaurant r = Restaurant(clock: true);
    await pumpHome(tester, r.p.h);
    final Finder button = find.byKey(const ValueKey<String>('clock-button'));
    expect(
      find.descendant(of: button, matching: find.text('Ishga keldim')),
      findsOneWidget,
    );
    await tapOn(tester, button);
    expect(r.restaurant.openSessions.keys, <int>[3]);
    expect(
      find.descendant(of: button, matching: find.textContaining('Ishda')),
      findsOneWidget,
    );

    r.p.h.now = r.p.h.now.add(const Duration(hours: 3));
    await tapOn(tester, button);
    await tapButton(tester, 'Ishdan ketdim');
    expect(r.restaurant.openSessions, isEmpty);
    expect(r.restaurant.closedMinutes[3], 180);
    expect(
      find.descendant(of: button, matching: find.text('Ishga keldim')),
      findsOneWidget,
    );
  });

  testWidgets(
    'phone: the clock is in the staff menu (no room in the top bar)',
    (WidgetTester tester) async {
      final Restaurant r = Restaurant(clock: true);
      await pumpHome(tester, r.p.h, size: const Size(390, 844));
      expect(find.byKey(const ValueKey<String>('clock-button')), findsNothing);
      await tester.tap(find.byTooltip('Xodim menyusi'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Ish vaqti'));
      await tester.pumpAndSettle();
      expect(find.textContaining('hali ishga kelganingizni'), findsOneWidget);
      await tapKey(tester, 'clock-dialog-action');
      expect(r.restaurant.openSessions.keys, <int>[3]);
    },
  );

  testWidgets(
    'table plate: a counter order carries «Stol A7» to the kitchen ticket and the talon',
    (WidgetTester tester) async {
      final SyncStore store = await SyncStore.open(MemorySyncStorage());
      final P22 p = P22(offlineStore: store);
      FakeRestaurantBackend(p.backend);
      p.state.format = formatBlock(
        profile: 'kafe',
        label: 'Kafe',
        modules: const <String>[
          'quick_counter',
          'queue_numbers',
          'table_service',
        ],
      );
      seedCounterMenu(p.state);
      p.state.openShift();
      FakeQueueBackend(p);
      await pumpHome(tester, p.h);
      expect(find.byType(CounterPage), findsOneWidget);

      await tester.tap(menuTile(oshId));
      await tester.pumpAndSettle();
      await enter(tester, 'counter-table-label', 'A7');
      await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
      await tester.pumpAndSettle();
      expect(find.byType(PaymentPage), findsOneWidget);
      await tapKey(tester, 'payment-submit');

      final Map<String, Object?> order = p.state.orders.values.last;
      expect(order['table_label'], 'A7');
      final String talon = p.receipts.firstWhere(
        (String t) => t.contains('Navbat raqamingiz'),
      );
      expect(talon, contains('Stol A7'));
      expect(talon, contains('A7-stolga olib kelinadi'));
      expect(
        p.h.printerFor('server-2').documents.last.text,
        contains('Stol A7'),
      );
      expect(find.textContaining('A7-stolga olib boriladi'), findsOneWidget);

      // Next customer: an empty plate field
      await tester.tap(find.byKey(const ValueKey<String>('counter-next')));
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<TextField>(
              find.byKey(const ValueKey<String>('counter-table-label')),
            )
            .controller!
            .text,
        isEmpty,
      );
    },
  );

  testWidgets('without table_service the counter has no plate field', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    p.state.format = formatBlock();
    seedCounterMenu(p.state);
    p.state.openShift();
    FakeQueueBackend(p);
    await pumpHome(tester, p.h);
    expect(find.byType(CounterPage), findsOneWidget);
    expect(
      find.byKey(const ValueKey<String>('counter-table-label')),
      findsNothing,
    );
  });
}
