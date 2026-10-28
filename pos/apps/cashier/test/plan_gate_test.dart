import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';
import 'package:rizqim_cashier/src/features/bookings/booking_detail_page.dart';
import 'package:rizqim_cashier/src/features/bookings/booking_form_page.dart';
import 'package:rizqim_cashier/src/features/floor/floor_page.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';

/// P4-21: the till reads the plan from the bootstrap. A feature the plan does not have is locked with "Asosiy tarifda bor"
/// (a new booking, deposit, nasiya account, taking a waived service charge back); an unpaid invoice shows a plain notice.
/// NEVER blocked, whatever the plan or the debt: taking payments, closing bills, receipts, shifts, refunds.
void main() {
  /// A store on Start; [overdue]: an unpaid invoice (reminder, or locked from [locked]); the settings close in 4 days.
  P22 start({bool overdue = false, bool locked = false}) {
    final P22 p = P22();
    final DateTime lockAt = p.h.now.add(const Duration(days: 4));
    p.state.entitlements = fakeEntitlements(
      plan: 'start',
      status: locked
          ? 'locked'
          : overdue
          ? 'past_due'
          : 'active',
      lockAt: overdue || locked ? lockAt : null,
      notice: overdue || locked
          ? fakeBillingNotice(
              locked: locked,
              daysOverdue: locked ? 9 : 3,
              lockAt: lockAt,
            )
          : null,
    );
    return p;
  }

  Finder lockLabel() => find.text('Asosiy tarifda bor');

  group('bookings', () {
    testWidgets(
      'on Start a new booking is locked and explained; the calendar and a booking that exists stay open',
      (WidgetTester tester) async {
        final P22 p = start();
        final String uuid = p.backend.seedBooking(
          tableId: 2,
          start: DateTime.utc(2026, 10, 22, 14),
          name: 'Jamshid aka',
        );
        await pumpHome(tester, p.h);
        await openSection(tester, 'Bronlar');
        expect(find.text('Jamshid aka · 0 kishi'), findsNothing);
        expect(lockLabel(), findsOneWidget);
        expect(
          tester
              .widget<RzButton>(
                find.descendant(
                  of: find.byKey(const ValueKey<String>('booking-new')),
                  matching: find.byType(RzButton),
                ),
              )
              .onPressed,
          isNull,
        );

        // An empty slot does not open the form: it says why
        await tapKey(tester, 'slot-3-15');
        expect(find.byType(BookingFormPage), findsNothing);
        expect(
          find.text(
            'Yangi bron Asosiy tarifda bor. Hozirgi tarifingizda yo\'q.',
          ),
          findsNothing,
        );
        expect(
          find.textContaining(
            "Bron Asosiy tarifda bor. Hozirgi tarifingizda yo'q.",
          ),
          findsOneWidget,
        );
        expect(p.backend.bookings, hasLength(1));

        // The booking that exists opens, and the guests can arrive
        await tapKey(tester, 'booking-$uuid');
        expect(find.byType(BookingDetailPage), findsOneWidget);
        await tapKey(tester, 'booking-arrive');
        await tapKey(tester, 'arrival-confirm');
        expect(p.backend.bookings[uuid]!['status'], 'arrived');
      },
    );

    testWidgets(
      'a new deposit is locked on Start; returning or keeping an existing one is not',
      (WidgetTester tester) async {
        final P22 p = start();
        final String uuid = p.backend.seedBooking(
          tableId: 2,
          start: DateTime.utc(2026, 10, 22, 14),
          name: 'Jamshid aka',
          deposit: '300000.00',
        );
        await pumpHome(tester, p.h);
        await openSection(tester, 'Bronlar');
        await tapKey(tester, 'booking-$uuid');
        expect(find.byType(BookingDetailPage), findsOneWidget);
        final TextButton deposit = tester.widget<TextButton>(
          find.descendant(
            of: find.byKey(const ValueKey<String>('booking-deposit')),
            matching: find.byType(TextButton),
          ),
        );
        expect(deposit.onPressed, isNull);
        expect(lockLabel(), findsWidgets);
        // The deposit that was taken is shown and can be settled
        expect(find.text(uzs(300000)), findsWidgets);
      },
    );

    testWidgets('on Asosiy nothing is locked', (WidgetTester tester) async {
      final P22 p = P22();
      p.state.entitlements = fakeEntitlements();
      await pumpHome(tester, p.h);
      await openSection(tester, 'Bronlar');
      expect(lockLabel(), findsNothing);
      await tapKey(tester, 'slot-3-15');
      expect(find.byType(BookingFormPage), findsOneWidget);
    });

    testWidgets(
      'an old server without the block, or with a legacy store, locks nothing',
      (WidgetTester tester) async {
        final P22 p = P22();
        await pumpHome(tester, p.h);
        await openSection(tester, 'Bronlar');
        expect(lockLabel(), findsNothing);
        await tapKey(tester, 'slot-3-15');
        expect(find.byType(BookingFormPage), findsOneWidget);
      },
    );
  });

  group('nasiya', () {
    testWidgets(
      'on Start a new account is locked; existing accounts, their statement and repayments go on',
      (WidgetTester tester) async {
        final P22 p = start();
        p.state.openShift();
        final String jamshid = p.backend.seedAccount(
          name: 'Jamshid aka',
          phone: '+998901112233',
          debt: '850000.00',
        );
        await pumpHome(tester, p.h);
        await openSection(tester, 'Nasiya');
        expect(lockLabel(), findsOneWidget);
        expect(find.text('Yangi hisob'), findsOneWidget);
        expect(
          tester
              .widget<RzButton>(
                find.widgetWithText(RzButton, 'Yangi hisob').first,
              )
              .onPressed,
          isNull,
        );

        await tester.tap(find.text('Jamshid aka'));
        await tester.pumpAndSettle();
        await tapButton(tester, "Qarz to'lovi");
        await enter(tester, 'tender-amount', '350000');
        await tapKey(tester, 'repay-submit');
        expect(p.backend.accounts[jamshid]!['balance'], '500000.00');
        expect(p.receipts.single, contains('KREDIT CHEKI'));
      },
    );

    testWidgets(
      'paying a bill: "Yangi hisob ochish" is locked on Start, the payment itself is not',
      (WidgetTester tester) async {
        final P22 p = start();
        p.state.openShift();
        final String uuid = p.state.seedOrder(tableId: 1, guests: 3);
        p.backend.seedAccount(name: 'Jamshid aka', phone: '+998901112233');
        await pumpHome(tester, p.h, name: 'Malika Rahimova');
        await openBilling(tester, uuid);
        await tapButton(tester, 'Schyot tuzish');
        await tapText(tester, "To'lash: ", contains: true);
        expect(find.byType(PaymentPage), findsOneWidget);
        await tester.tap(find.widgetWithText(ChoiceChip, 'Nasiya'));
        await tester.pumpAndSettle();
        expect(find.text('Yangi hisob ochish'), findsOneWidget);
        expect(
          tester
              .widget<TextButton>(
                find.widgetWithText(TextButton, 'Yangi hisob ochish'),
              )
              .onPressed,
          isNull,
        );
        expect(lockLabel(), findsOneWidget);
      },
    );
  });

  group('service charge', () {
    testWidgets(
      'on Start a waived service charge cannot be taken back, but it can be waived and everything is paid',
      (WidgetTester tester) async {
        final P22 p = start();
        final String uuid = p.state.seedOrder(tableId: 1);
        await pumpHome(tester, p.h);
        await openPlace(tester, '1-xona');
        // The service charge is on the order: it can be taken off
        expect(find.text('Xizmat haqi 10%'), findsOneWidget);
        await tester.tap(find.text('Olib tashlash').last);
        await tester.pumpAndSettle();
        await tester.tap(find.widgetWithText(ChoiceChip, "Mehmon e'tirozi"));
        await tester.pumpAndSettle();
        await tester.tap(buttonLabeled('Olib tashlash'));
        await tester.pumpAndSettle();
        await typePin(tester, '5926');
        await tester.pumpAndSettle();
        expect(
          (p.state.orders[uuid]!['charges']! as Map<String, Object?>)['waived'],
          contains('service_charge'),
        );
        // ... and taking it back is the plan's service_charge
        expect(find.text('Qaytarish'), findsNothing);
        expect(
          find.text('Olib tashlandi · Asosiy tarifda bor'),
          findsOneWidget,
        );
      },
    );
  });

  group('the unpaid-invoice notice', () {
    testWidgets(
      'a reminder counts the days to the lock and says the till keeps working',
      (WidgetTester tester) async {
        final P22 p = start(overdue: true);
        await pumpHome(tester, p.h);
        expect(
          find.text(
            "To'lov muddati o'tdi — 4 kundan keyin sozlamalar yopiladi",
          ),
          findsOneWidget,
        );
        expect(
          find.textContaining('OB-202610-000042 hisob-faktura, qoldiq'),
          findsOneWidget,
        );
        expect(
          find.textContaining('Kassa ishlashda davom etadi'),
          findsOneWidget,
        );
        expect(find.byType(FloorPage), findsOneWidget);
      },
    );

    testWidgets(
      'locked: "Sozlamalar yopiq", and the till still takes the payment, prints the receipt and closes the order',
      (WidgetTester tester) async {
        final P22 p = start(locked: true);
        p.state.openShift();
        final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
        await pumpHome(tester, p.h);
        expect(find.text("Sozlamalar yopiq — to'lov qiling"), findsOneWidget);
        expect(find.textContaining('darhol ochiladi'), findsOneWidget);
        expect(
          find.textContaining(
            "Kassa to'lov qabul qilish, chek chiqarish va smenada davom etadi",
          ),
          findsOneWidget,
        );

        await openBilling(tester, uuid);
        await tapButton(tester, 'Schyot tuzish');
        await tapText(tester, "To'lash: ", contains: true);
        await tester.tap(find.widgetWithText(ChoiceChip, 'QR'));
        await tester.pumpAndSettle();
        await tapKey(tester, 'payment-submit');
        expect(p.backend.bills[uuid]!.single['status'], 'paid');
        expect(p.state.orders[uuid]!['status'], 'delivered');
        expect(p.receipts.last, contains('SOTUV CHEKI'));
        expect(p.receipts.last, contains('Fiskal belgi:'));
      },
    );

    testWidgets(
      'the plan is read again: paying the invoice removes the notice and opens the locks',
      (WidgetTester tester) async {
        final P22 p = start(locked: true);
        await pumpHome(tester, p.h);
        await openSection(tester, 'Bronlar');
        expect(lockLabel(), findsOneWidget);
        expect(find.text("Sozlamalar yopiq — to'lov qiling"), findsOneWidget);

        // The owner pays and moves to Asosiy; the till reads the plan again
        p.state.entitlements = fakeEntitlements();
        await settle(tester, p.h.services.session.refreshEntitlements());
        await tester.pumpAndSettle();
        expect(find.text("Sozlamalar yopiq — to'lov qiling"), findsNothing);
        expect(lockLabel(), findsNothing);
        await tapKey(tester, 'slot-3-15');
        expect(find.byType(BookingFormPage), findsOneWidget);
      },
    );

    testWidgets(
      'a screen that has not heard of the plan change shows the server\'s words and locks itself',
      (WidgetTester tester) async {
        final P22 p = P22();
        p.state.entitlements = fakeEntitlements();
        await pumpHome(tester, p.h);
        await openSection(tester, 'Bronlar');
        // The plan changes on the server after the till loaded it
        p.state.entitlements = fakeEntitlements(plan: 'start');
        await tapKey(tester, 'slot-3-15');
        expect(find.byType(BookingFormPage), findsOneWidget);
        await enter(tester, 'booking-name', 'Dilshod');
        await enter(tester, 'booking-phone', '90 111 22 33');
        await tester.tap(find.widgetWithText(ChoiceChip, "Tug'ilgan kun"));
        await tester.pumpAndSettle();
        await tapKey(tester, 'booking-save');
        expect(find.textContaining('Asosiy tarifida bor'), findsOneWidget);
        expect(p.backend.bookings, isEmpty);
        // The plan was read again: the lock shows on the calendar
        await tester.pageBack();
        await tester.pumpAndSettle();
        expect(lockLabel(), findsOneWidget);
      },
    );
  });
}
