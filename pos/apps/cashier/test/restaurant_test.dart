import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_ui/rizqim_ui.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';
import 'package:rizqim_cashier/src/features/billing/tender_form.dart';
import 'package:rizqim_cashier/src/features/order/order_page.dart';

import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';
import 'support/restaurant.dart';

/// P6-21 … P6-25 at the till (restoran): tips at payment, split by guest, the weight dialog and the bill block, the
/// seat of a line, sending courses, the service charge notice on the bill. Modules off: nothing new shows.
void main() {
  testWidgets(
    'tips: the change left as a tip goes to the order waiter, apart from the receipt total',
    (WidgetTester tester) async {
      final Restaurant r = Restaurant();
      final String uuid = r.order(waiterId: 4);
      await pumpHome(tester, r.p.h);
      await openBilling(tester, uuid);
      await tapButton(tester, 'Schyot tuzish');
      await tapText(tester, "To'lash: ", contains: true);
      expect(find.byType(PaymentPage), findsOneWidget);

      // 209 000 bill, the guest gives 220 000 and leaves the change
      expect(find.byKey(const ValueKey<String>('tip-amount')), findsOneWidget);
      await enter(tester, 'tender-given', '220000');
      await tapKey(tester, 'tip-from-change');
      expect(
        tester
            .widget<Text>(find.byKey(const ValueKey<String>('tender-change')))
            .data,
        uzs(0),
      );
      expect(
        tester
            .widget<Text>(
              find.byKey(const ValueKey<String>('payment-tip-summary')),
            )
            .data,
        'Chek: ${uzs(209000)}, choy puli: ${uzs(11000)}',
      );
      // Personal mode: the order's waiter is picked by default
      expect(
        tester
            .widget<ChoiceChip>(find.widgetWithText(ChoiceChip, 'Botir Aliyev'))
            .selected,
        isTrue,
      );
      await tapKey(tester, 'payment-submit');

      final Map<String, Object?> tip = r.restaurant.tips.single;
      expect(
        <Object?>[tip['amount'], tip['method'], tip['source'], tip['staff_id']],
        <Object?>['11000.00', 'cash', 'change', 4],
      );
      final Map<String, Object?> bill = r.p.backend.bills[uuid]!.single;
      expect(bill['total'], '209000.00');
      expect(bill['tips_amount'], '11000.00');
      final Map<String, Object?> pay =
          (bill['payments']! as List<Object?>).single! as Map<String, Object?>;
      expect(
        <Object?>[pay['amount'], pay['tip_amount'], pay['change_amount']],
        <Object?>['209000.00', '11000.00', '0.00'],
      );
      // The sale receipt has the bill total only: tips are never on it
      final String sale = r.p.receipts.last;
      expect(sale, contains('209 000'));
      expect(sale.toLowerCase(), isNot(contains('choy puli')));
    },
  );

  testWidgets('tips: a card tip goes to the staff member picked at payment', (
    WidgetTester tester,
  ) async {
    final Restaurant r = Restaurant();
    final String uuid = r.order(waiterId: 4);
    await pumpHome(tester, r.p.h);
    await openBilling(tester, uuid);
    await tapButton(tester, 'Schyot tuzish');
    await tapText(tester, "To'lash: ", contains: true);
    await tester.tap(find.widgetWithText(ChoiceChip, 'Karta'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(ChoiceChip, 'Uzcard'));
    await tester.pumpAndSettle();
    await enter(tester, 'tender-txn', '123456');
    // Without the tip the switch "change as tip" is a cash-only thing
    expect(find.byKey(const ValueKey<String>('tip-from-change')), findsNothing);
    await enter(tester, 'tip-amount', '15000');
    await tapOn(tester, find.widgetWithText(ChoiceChip, 'Malika Rahimova'));
    await tapKey(tester, 'payment-submit');

    final Map<String, Object?> tip = r.restaurant.tips.single;
    expect(
      <Object?>[tip['amount'], tip['method'], tip['source'], tip['staff_id']],
      <Object?>['15000.00', 'card', 'tender', 2],
    );
    expect(r.p.backend.bills[uuid]!.single['total'], '209000.00');
  });

  testWidgets(
    'offline: the tip goes with the cash tender in the queue (bill.pay tip_amount, tip_staff_id)',
    (WidgetTester tester) async {
      final SyncStore store = await SyncStore.open(MemorySyncStorage());
      final Restaurant r = Restaurant(p: P22(offlineStore: store));
      FakeSyncBackend(r.p.state);
      final String uuid = r.order(waiterId: 4);
      await pumpHome(tester, r.p.h);
      await openBilling(tester, uuid);
      await tapButton(tester, 'Schyot tuzish');

      r.p.h.adapter.offline = true;
      await _drive(tester, r.p.h.data.floor.load());
      expect(r.p.h.data.offline!.active, isTrue);
      await tapText(tester, "To'lash: ", contains: true);
      await enter(tester, 'tender-given', '220000');
      await tapKey(tester, 'tip-from-change');
      await tapKey(tester, 'payment-submit');

      final SyncOperation op = store
          .operationsAfter(0)
          .lastWhere((SyncOperation o) => o.type == SyncOpType.billPay);
      final Map<String, Object?> tender =
          (op.payload['tenders']! as List<Object?>).single!
              as Map<String, Object?>;
      expect(
        <Object?>[
          tender['amount'],
          tender['tip_amount'],
          op.payload['tip_staff_id'],
        ],
        <Object?>['209000.00', '11000.00', 4],
      );

      // Back online: the queue reaches the server and the tip is recorded there (to the waiter)
      r.p.h.adapter.offline = false;
      await _drive(tester, r.p.h.data.offline!.syncNow());
      expect(r.p.h.data.offline!.store.pendingCount, 0);
      final Map<String, Object?> tip = r.restaurant.tips.single;
      expect(
        <Object?>[tip['amount'], tip['staff_id']],
        <Object?>['11000.00', 4],
      );
      expect(r.p.h.data.offline!.active, isFalse);
      // The messages of the offline payment go away
      await tester.pump(const Duration(seconds: 10));
      await tester.pumpAndSettle();
    },
  );

  test(
    'the tip is always sent as an amount, so the offline queue records it',
    () {
      final TenderDraft d = TenderDraft(
        amount: const PosMoney.som(209000),
        tendered: const PosMoney.som(220000),
        tipFromChange: true,
      );
      expect(d.effectiveTip, const PosMoney.som(11000));
      expect(d.change, PosMoney.zero);
      expect(d.validate(), isNull);
      expect(d.toInput().toJson(), <String, Object?>{
        'tender': 'cash',
        'amount': '209000.00',
        'tendered_amount': '220000.00',
        'tip_amount': '11000.00',
        'tip_from_change': true,
      });
      // Part of the change only; a tip the given cash does not cover is refused
      d.tip = const PosMoney.som(5000);
      expect(d.change, const PosMoney.som(6000));
      d
        ..tipFromChange = false
        ..tip = const PosMoney.som(20000);
      expect(d.validate(), "Berilgan pul to'lov va choy pulini qoplamaydi.");
    },
  );

  testWidgets(
    'split by guest: one bill per seat, the shared lines split equally; the preview before',
    (WidgetTester tester) async {
      final Restaurant r = Restaurant();
      // Osh (1.5 kg, 180 000) for guest 1, non (4 000) for guest 2, choy (2 × 5 000) shared
      final String uuid = r.order(
        lines: const <(int, String)>[(3, '1.500'), (2, '1'), (1, '2')],
        seats: const <int?>[1, 2, null],
      );
      await pumpHome(tester, r.p.h);
      await openBilling(tester, uuid);

      await tapText(tester, "Mehmonlar bo'yicha");
      // 194 000 + 10% = 213 400: guest 1 — 185 000 net, guest 2 — 9 000 net
      expect(
        find.byKey(const ValueKey<String>('guest-share-1')),
        findsOneWidget,
      );
      expect(
        find.byKey(const ValueKey<String>('guest-share-2')),
        findsOneWidget,
      );
      expect(find.text(uzs(203500)), findsOneWidget);
      expect(find.text(uzs(9900)), findsOneWidget);
      await tapButton(tester, 'Schyot tuzish');

      final List<Map<String, Object?>> bills = r.p.backend.bills[uuid]!
          .where((Map<String, Object?> b) => b['status'] != 'void')
          .toList();
      expect(bills.map((Map<String, Object?> b) => b['seat_no']), <Object?>[
        1,
        2,
      ]);
      expect(find.text('1-schyot · 1-mehmon'), findsOneWidget);
      expect(find.text('2-schyot · 2-mehmon'), findsOneWidget);
    },
  );

  testWidgets(
    'split by guest: no seats yet — a plain hint, no request; the option is gone without the module',
    (WidgetTester tester) async {
      final Restaurant r = Restaurant();
      final String uuid = r.order();
      await pumpHome(tester, r.p.h);
      await openBilling(tester, uuid);
      await tapText(tester, "Mehmonlar bo'yicha");
      expect(
        find.byKey(const ValueKey<String>('guest-split-empty')),
        findsOneWidget,
      );
      await tapButton(tester, 'Schyot tuzish');
      expect(r.p.backend.bills[uuid], anyOf(isNull, isEmpty));
    },
  );

  testWidgets('modules off: no split by guest, no tips, no clock', (
    WidgetTester tester,
  ) async {
    final Restaurant r = Restaurant(
      modules: const <String>['service_charge', 'rooms', 'kds'],
      tips: false,
    );
    final String uuid = r.order(seats: const <int?>[1, 2]);
    await pumpHome(tester, r.p.h);
    expect(find.byKey(const ValueKey<String>('clock-button')), findsNothing);
    await openBilling(tester, uuid);
    expect(find.text("Mehmonlar bo'yicha"), findsNothing);
    await tapButton(tester, 'Schyot tuzish');
    await tapText(tester, "To'lash: ", contains: true);
    expect(find.byKey(const ValueKey<String>('tip-amount')), findsNothing);
  });

  testWidgets(
    'weight later: the bill waits for the weight; the cashier weighs the fish, the price follows',
    (WidgetTester tester) async {
      final Restaurant r = Restaurant();
      final String uuid = r.order(awaitingWeight: true);
      await pumpHome(tester, r.p.h);
      await openOrderPage(tester, uuid);
      expect(find.byType(OrderPage), findsOneWidget);

      final Finder bill = find.byKey(const ValueKey<String>('order-bill'));
      expect(
        find.descendant(
          of: bill,
          matching: find.text("Og'irlik kutilmoqda (1)"),
        ),
        findsOneWidget,
      );
      expect(tester.widget<RzButton>(bill).onPressed, isNull);
      expect(find.text("Og'irlik kutilmoqda"), findsOneWidget);

      final int id = items(r.p.state.orders[uuid]!).first['id']! as int;
      await tapKey(tester, 'item-$id');
      await tapButton(tester, "Og'irlikni kiritish");
      expect(find.text("Xom og'irlik (kg)"), findsOneWidget);
      await tester.tap(find.bySemanticsLabel('2').first);
      await tester.pumpAndSettle();
      await tapText(tester, "Og'irlikni yozish", contains: true);

      final Map<String, Object?> line = items(r.p.state.orders[uuid]!).first;
      expect(line['weight_status'], 'weighed');
      expect(line['total_price'], '240000.00');
      expect(r.p.state.orders[uuid]!['weight_pending'], 0);
      expect(
        find.descendant(of: bill, matching: find.text('Schyot')),
        findsOneWidget,
      );
    },
  );

  testWidgets('the cashier sets the guest of a line from the order view', (
    WidgetTester tester,
  ) async {
    final Restaurant r = Restaurant();
    final String uuid = r.order();
    await pumpHome(tester, r.p.h);
    await openOrderPage(tester, uuid);
    final int id = items(r.p.state.orders[uuid]!).first['id']! as int;
    await tapKey(tester, 'item-$id');
    await tapButton(tester, 'Mehmon raqami');
    await tapKey(tester, 'seat-2');
    expect(items(r.p.state.orders[uuid]!).first['seat_no'], 2);
  });

  testWidgets('courses: the cashier fires the held course of a table', (
    WidgetTester tester,
  ) async {
    final Restaurant r = Restaurant();
    final String uuid = r.order(courses: const <int?>[1, 2]);
    await pumpHome(tester, r.p.h);
    await openOrderPage(tester, uuid);
    expect(find.text('1-kurs: Oshxonada'), findsOneWidget);
    expect(find.text('2-kurs: Kutmoqda'), findsOneWidget);
    await tapKey(tester, 'send-course-2');

    final Map<String, Object?> course2 = items(r.p.state.orders[uuid]!).last;
    expect(course2['kitchen_status'], 'sent');
    expect(find.text('2-kurs: Oshxonada'), findsOneWidget);
    expect(find.byKey(const ValueKey<String>('send-course-2')), findsNothing);
  });

  testWidgets(
    'the service charge notice is under the service charge line of the printed bill',
    (WidgetTester tester) async {
      final Restaurant r = Restaurant();
      final String uuid = r.order();
      r.restaurant.serviceChargeNotice = "Xizmat haqi: zalda 10%. Kuver yo'q.";
      await pumpHome(tester, r.p.h);
      await openBilling(tester, uuid);
      await tapButton(tester, 'Schyot tuzish');
      expect(find.byKey(const ValueKey<String>('bill-notice')), findsOneWidget);
      await tapButton(tester, 'Schyotni chiqarish');
      final String check = r.p.receipts.single;
      final int line = check.indexOf('Xizmat haqi 10%');
      expect(line, greaterThan(0));
      expect(check.indexOf('Xizmat haqi: zalda 10%'), greaterThan(line));
      expect(
        check.indexOf('Xizmat haqi: zalda 10%'),
        lessThan(check.indexOf('JAMI')),
      );
    },
  );
}

/// Runs [future] to the end, moving the fake clock (Dio and the sync sender wait on timers).
Future<T> _drive<T>(WidgetTester tester, Future<T> future) async {
  var done = false;
  late T value;
  Object? error;
  unawaited(
    future.then(
      (T v) {
        value = v;
        done = true;
      },
      onError: (Object e) {
        error = e;
        done = true;
      },
    ),
  );
  for (var i = 0; i < 300 && !done; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
  if (!done) throw StateError('did not finish');
  if (error != null) throw error!;
  await tester.pumpAndSettle();
  return value;
}
