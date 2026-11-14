import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_cashier/src/features/banquets/banquet_settlement_page.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';
import 'package:rizqim_cashier/src/features/order/order_page.dart';
import 'package:rizqim_cashier/src/features/billing/cashier_ui.dart';

import 'support/banquets.dart';
import 'support/harness.dart';
import 'support/p22.dart';

/// P6 wave 5 review fixes at the till (H4): the event-day order is paid only through the final settlement (C1), the
/// schedule is read-only once the event is billed (C2), the event figures are net and show no remainder on a settled or
/// cancelled event (C4), lines added after the settlement took money are explained (C10), and a stuck event (its order
/// closed by a direct payment before the guard) can still be settled from the event card.
void main() {
  // Now: 2026-10-22 13:00 UTC. 200 × 150 000 + evening hall 10 000 000 = 40 000 000; deposit 12 000 000 paid.

  group('C1: the event-day order', () {
    testWidgets(
      'the order screen has no "Schyot": "Yakuniy hisob orqali to\'lanadi" opens the settlement; the title is the event',
      (WidgetTester tester) async {
        final BanquetTill t = BanquetTill();
        final String a = t.b.seedEvent(
          date: '2026-10-22',
          title: "Aziz va Malika to'yi",
          status: 'confirmed',
        );
        await pumpBanquets(tester, t, name: 'Malika Rahimova');
        await openEvent(tester, a);
        await tapKey(tester, 'banquet-start');

        expect(find.byType(OrderPage), findsOneWidget);
        // S2 follow-up: the title comes from the event, never from the order note
        expect(find.text("Tadbir: Aziz va Malika to'yi"), findsOneWidget);
        expect(find.byKey(const ValueKey<String>('order-bill')), findsNothing);
        expect(find.text("Yakuniy hisob orqali to'lanadi"), findsOneWidget);
        await tapKey(tester, 'order-banquet-settlement');
        expect(find.byType(BanquetSettlementPage), findsOneWidget);
        expect(find.byKey(const ValueKey<String>('banquet-settle')), findsOne);

        // Back on the order screen, then back on the event card
        await tester.pageBack();
        await tester.pumpAndSettle();
        expect(find.byType(OrderPage), findsOneWidget);
        await tester.pageBack();
        await tester.pumpAndSettle();
        await tapKey(tester, 'banquet-open-order');
        expect(find.byKey(const ValueKey<String>('order-bill')), findsNothing);
        expect(
          find.byKey(const ValueKey<String>('order-banquet-settlement')),
          findsOneWidget,
        );
      },
    );

    testWidgets(
      'reached another way (the queue, the billing page): 409 banquet_settle_first in plain words, no bill',
      (WidgetTester tester) async {
        final BanquetTill t = BanquetTill();
        final String a = t.b.seedEvent(date: '2026-10-22', status: 'confirmed');
        await pumpHome(tester, t.p.h);
        final String order = (await settle(
          tester,
          t.p.h.data.banquetOps.start(a, version: 2),
        ))!;
        await openBilling(tester, order);
        await tapButton(tester, 'Schyot tuzish');
        expect(
          find.text(PosReasons.describe('banquet_settle_first')!),
          findsOneWidget,
        );
        expect(t.p.backend.bills[order], anyOf(isNull, isEmpty));
        expect(t.b.events[a]!['status'], 'in_progress');
      },
    );
  });

  testWidgets(
    'C2: once billed the schedule is read-only ("Qoldiq yakuniy hisobda to\'lanadi"), no "To\'lash"',
    (WidgetTester tester) async {
      final BanquetTill t = BanquetTill();
      final String a = t.b.seedEvent(date: '2026-10-22', status: 'confirmed');
      await pumpHome(tester, t.p.h);
      // The settlement bill exists, the remainder is not paid yet (the event stays in progress)
      await settle(
        tester,
        t.p.h.data.banquetOps.settle(
          a,
          shiftUuid: '${t.p.state.shift!['uuid']}',
        ),
      );
      // A row put back in the schedule (the old panel editor allowed it): the till never offers to take it
      t.b.addDueRow(a, 1000000);
      await openBanquets(tester);
      await openEvent(tester, a);

      expect(find.text("O'tkazilmoqda"), findsWidgets);
      expect(
        find.byKey(const ValueKey<String>('banquet-schedule-billed')),
        findsOneWidget,
      );
      expect(find.text("Qoldiq yakuniy hisobda to'lanadi."), findsOneWidget);
      expect(
        find.byWidgetPredicate(
          (Widget w) =>
              w.key is ValueKey<String> &&
              (w.key! as ValueKey<String>).value.startsWith('banquet-pay-'),
        ),
        findsNothing,
      );
      // The settlement is the way to pay
      expect(
        find.byKey(const ValueKey<String>('banquet-settlement')),
        findsOneWidget,
      );
    },
  );

  group('C4: the event figures', () {
    testWidgets(
      'a settled and fully paid event: no "Qoldiq" on the card or in the calendar; paid = the full total',
      (WidgetTester tester) async {
        final BanquetTill t = BanquetTill();
        final String a = t.b.seedEvent(date: '2026-10-22', status: 'confirmed');
        await pumpHome(tester, t.p.h);
        final PosSettleResult r = await settle(
          tester,
          t.p.h.data.banquetOps.settle(
            a,
            shiftUuid: '${t.p.state.shift!['uuid']}',
          ),
        );
        final PosOrder o = await settle(
          tester,
          t.p.h.data.orders.get(r.settlement.orderUuid!),
        );
        await settle(
          tester,
          t.p.h.data.billing.pay(
            r.bill.uuid,
            tenders: <PosTenderInput>[PosTenderInput.cash(r.bill.dueAmount)],
            shiftUuid: '${t.p.state.shift!['uuid']}',
            version: o.version,
          ),
        );
        await openBanquets(tester);
        expect(find.text('Yakunlangan'), findsOneWidget);
        expect(find.textContaining('Qoldiq'), findsNothing);
        await openEvent(tester, a);
        expect(t.b.events[a]!['status'], 'settled');
        expect(
          find.byKey(const ValueKey<String>('banquet-event-outstanding')),
          findsNothing,
        );
        expect(
          tester
              .widget<AmountRow>(
                find.byKey(const ValueKey<String>('banquet-event-paid')),
              )
              .amount,
          const PosMoney.som(40000000),
        );
      },
    );

    testWidgets(
      'a cancelled event: no "Qoldiq"; paid is net of the refund, the refund shown',
      (WidgetTester tester) async {
        final BanquetTill t = BanquetTill();
        final String a = t.b.seedEvent(date: '2026-11-11', status: 'confirmed');
        await pumpBanquets(tester, t, name: 'Malika Rahimova');
        await openEvent(tester, a);
        await tapKey(tester, 'banquet-cancel');
        await tapText(tester, 'Mijoz voz kechdi');
        await tapKey(tester, 'banquet-cancel-submit');
        expect(t.b.events[a]!['status'], 'cancelled');

        expect(
          find.byKey(const ValueKey<String>('banquet-event-outstanding')),
          findsNothing,
        );
        // 12 000 000 paid, 8 400 000 back, 3 600 000 retained
        expect(
          tester
              .widget<AmountRow>(
                find.byKey(const ValueKey<String>('banquet-event-refunded')),
              )
              .amount,
          const PosMoney.som(8400000),
        );
        expect(
          tester
              .widget<AmountRow>(
                find.byKey(const ValueKey<String>('banquet-event-paid')),
              )
              .amount,
          const PosMoney.som(3600000),
        );
      },
    );
  });

  testWidgets(
    'C10: after the settlement bill the page explains lines added later (re-billed before the receipt, own receipt after)',
    (WidgetTester tester) async {
      final BanquetTill t = BanquetTill();
      final String a = t.b.seedEvent(date: '2026-10-22', status: 'confirmed');
      await pumpHome(tester, t.p.h);
      await settle(
        tester,
        t.p.h.data.banquetOps.settle(
          a,
          shiftUuid: '${t.p.state.shift!['uuid']}',
        ),
      );
      await openBanquets(tester);
      await openEvent(tester, a);
      await tapKey(tester, 'banquet-settlement');
      expect(
        find.byKey(const ValueKey<String>('banquet-settlement-addons')),
        findsOneWidget,
      );
      expect(
        find.textContaining("alohida schyot va alohida chek"),
        findsOneWidget,
      );
    },
  );

  testWidgets('a stuck event (its order closed by a direct payment): settled from the event card — one more sale receipt for '
      'the banquet charge, the advances offset', (WidgetTester tester) async {
    final BanquetTill t = BanquetTill();
    final String a = t.b.seedEvent(date: '2026-10-22', status: 'confirmed');
    await pumpHome(tester, t.p.h);
    await settle(tester, t.p.h.data.banquetOps.start(a, version: 2));
    // Before the guard: the cashier paid the event's order directly, it closed
    t.b.closeOrderDirectly(a);
    await openBanquets(tester);
    await openEvent(tester, a);
    expect(find.text("O'tkazilmoqda"), findsWidgets);
    await tapKey(tester, 'banquet-settlement');
    await tapKey(tester, 'banquet-settle');
    expect(find.byType(PaymentPage), findsOneWidget);
    // 40 000 000 − the 12 000 000 deposit
    expect(find.text(uzs(28000000)), findsWidgets);
    await tapKey(tester, 'payment-submit');
    expect(find.byType(PaymentPage), findsNothing);
    expect(t.receipts.last, contains('SOTUV CHEKI'));
    expect(t.receipts.last, contains('Banket paketi: Standart'));
    expect(t.b.events[a]!['status'], 'settled');
    expect(
      find.byKey(const ValueKey<String>('banquet-settled')),
      findsOneWidget,
    );
  });
}
