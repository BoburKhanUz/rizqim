import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';
import 'package:rizqim_cashier/src/features/shift/z_close_page.dart';

import 'support/harness.dart';
import 'support/p22.dart';

/// P6-16: bank terminali oqimi kassada — kartada RRN va oxirgi 4 raqam, Z da bank terminali jami va farq.
Map<String, Object?> _format(List<String> extra) => <String, Object?>{
  'profile': 'choyxona',
  'profile_label': 'Choyxona',
  'mode': 'hybrid',
  'first_screen': 'hall_map',
  'modules': <String>[
    'rooms',
    'room_fee',
    'guest_items',
    'bookings',
    'customer_accounts',
    'service_charge',
    'kds',
    'quick_counter',
    ...extra,
  ],
  'locks': <String, Object?>{'service_charge_zero': false},
};

Map<String, Object?> _flow({
  String? cardMode = 'terminal_only',
  bool smartKassa = false,
  bool askBankTotal = true,
  String threshold = '5000.00',
  bool checklist = false,
}) => <String, Object?>{
  'card_mode': cardMode,
  'smart_kassa': smartKassa,
  'fiscal_kassa_id': smartKassa ? 'KASSA-7' : null,
  'ask_bank_total': askBankTotal,
  'difference_threshold': threshold,
  'checklist_complete': checklist,
};

void _enable(P22 p, Map<String, Object?>? flow) {
  p.state.format = _format(
    flow == null ? <String>[] : <String>['terminal_card_flow'],
  );
  if (flow != null) p.state.bootstrapExtras['terminal_flow'] = flow;
}

Future<String> _toPayment(WidgetTester tester, P22 p) async {
  p.state.openShift();
  final String uuid = p.state.seedOrder(tableId: 1, guests: 3);
  await pumpHome(tester, p.h);
  await openBilling(tester, uuid);
  await tapButton(tester, 'Schyot tuzish');
  await tapText(tester, "To'lash: ", contains: true);
  expect(find.byType(PaymentPage), findsOneWidget);
  await tester.tap(find.widgetWithText(ChoiceChip, 'Karta'));
  await tester.pumpAndSettle();
  await tester.tap(find.widgetWithText(ChoiceChip, 'Humo'));
  await tester.pumpAndSettle();
  return uuid;
}

void main() {
  testWidgets(
    'terminal only: the RRN is asked and the last four digits are optional; both reach the server and the receipt',
    (WidgetTester tester) async {
      final P22 p = P22();
      _enable(p, _flow());
      final String uuid = await _toPayment(tester, p);

      expect(
        find.text('RRN (terminal chekidagi tranzaksiya raqami)'),
        findsOneWidget,
      );
      expect(
        find.byKey(const ValueKey<String>('tender-last4')),
        findsOneWidget,
      );
      expect(
        find.byKey(const ValueKey<String>('smart-kassa-hint')),
        findsNothing,
      );

      await enter(tester, 'tender-txn', '123456789012');
      await enter(tester, 'tender-last4', '43');
      expect(find.textContaining('oxirgi 4 raqamini kiriting'), findsWidgets);
      await tapKey(tester, 'payment-submit');
      expect(
        p.backend.bills[uuid]!.single['payments'],
        isEmpty,
        reason: 'not four digits: nothing is paid',
      );

      await enter(tester, 'tender-last4', '4321');
      await tapKey(tester, 'payment-submit');
      final Map<String, Object?> pay =
          (p.backend.bills[uuid]!.single['payments']! as List<Object?>).single!
              as Map<String, Object?>;
      expect(pay['transaction_id'], '123456789012');
      expect(pay['card_last4'], '4321');
      expect(p.receipts.last, contains('Tranzaksiya: 123456789012 **** 4321'));
    },
  );

  testWidgets(
    'the last four digits can be left empty: the payment still goes through',
    (WidgetTester tester) async {
      final P22 p = P22();
      _enable(p, _flow());
      final String uuid = await _toPayment(tester, p);
      await enter(tester, 'tender-txn', 'RRN9');
      await tapKey(tester, 'payment-submit');
      final Map<String, Object?> pay =
          (p.backend.bills[uuid]!.single['payments']! as List<Object?>).single!
              as Map<String, Object?>;
      expect(pay['transaction_id'], 'RRN9');
      expect(pay['card_last4'], isNull);
      expect(p.receipts.last, contains('Tranzaksiya: RRN9'));
      expect(p.receipts.last, isNot(contains('****')));
    },
  );

  testWidgets(
    'without the module (or the card mode) the card form is the old one',
    (WidgetTester tester) async {
      final P22 p = P22();
      _enable(p, null);
      await _toPayment(tester, p);
      expect(
        find.text('Tranzaksiya raqami (terminal chekidan)'),
        findsOneWidget,
      );
      expect(find.byKey(const ValueKey<String>('tender-last4')), findsNothing);
    },
  );

  testWidgets(
    'smart kassa: a hint instead of the bank terminal wording, the RRN is still taken',
    (WidgetTester tester) async {
      final P22 p = P22();
      _enable(p, _flow(cardMode: 'smart_kassa', smartKassa: true));
      await _toPayment(tester, p);
      expect(
        find.byKey(const ValueKey<String>('smart-kassa-hint')),
        findsOneWidget,
      );
      expect(find.byKey(const ValueKey<String>('tender-last4')), findsNothing);
      expect(find.byKey(const ValueKey<String>('tender-txn')), findsOneWidget);
    },
  );

  testWidgets(
    'Z close: the bank total is asked, the difference and the warning show, and the shift closes anyway; the summary stays on the shift page',
    (WidgetTester tester) async {
      final P22 p = P22();
      _enable(p, _flow());
      p.state.openShift();
      final String uuid = p.state.seedOrder(tableId: 1, guests: 3);
      await pumpHome(tester, p.h);
      await openBilling(tester, uuid);
      await tapButton(tester, 'Schyot tuzish');
      await tapText(tester, "To'lash: ", contains: true);
      await tester.tap(find.widgetWithText(ChoiceChip, 'Karta'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(ChoiceChip, 'Humo'));
      await tester.pumpAndSettle();
      await enter(tester, 'tender-txn', 'RRN1');
      await tapKey(tester, 'payment-submit');
      expect(p.backend.bills[uuid]!.single['status'], 'paid');

      await openSection(tester, 'Smena');
      await tapKey(tester, 'z-open');
      expect(find.byType(ZClosePage), findsOneWidget);
      expect(
        find.byKey(const ValueKey<String>('bank-total-section')),
        findsOneWidget,
      );
      expect(find.byKey(const ValueKey<String>('bank-provider')), findsNothing);
      expect(
        find.byKey(const ValueKey<String>('bank-checklist')),
        findsOneWidget,
      );
      expect(find.byKey(const ValueKey<String>('bank-warning')), findsNothing);

      // 509 000 on the card; the terminal says 500 000: a 9 000 difference, above the 5 000 threshold
      await enter(tester, 'bank-total', '500000');
      expect(
        tester
            .widget<Text>(find.byKey(const ValueKey<String>('bank-difference')))
            .data,
        '−${uzs(9000)}',
      );
      expect(
        find.byKey(const ValueKey<String>('bank-warning')),
        findsOneWidget,
      );
      await enter(tester, 'bank-count', '1');

      await tapKey(tester, 'z-close');
      final Map<String, Object?> closed = p.backend.closedShifts.values.single;
      expect(closed['status'], 'closed');
      final Map<String, Object?> bank =
          closed['bank_terminal']! as Map<String, Object?>;
      expect(bank['bank_total'], '500000.00');
      expect(bank['bank_count'], 1);
      expect(bank['over_threshold'], isTrue);

      expect(find.byType(ZClosePage), findsNothing);
      expect(
        find.byKey(const ValueKey<String>('bank-terminal-summary')),
        findsOneWidget,
      );
      expect(find.text('Bank terminali'), findsOneWidget);
      expect(
        find.byKey(const ValueKey<String>('bank-terminal-alert')),
        findsOneWidget,
      );
    },
  );

  testWidgets(
    'Z close with the total left empty closes the shift and sends nothing about the bank',
    (WidgetTester tester) async {
      final P22 p = P22();
      _enable(p, _flow());
      p.state.openShift();
      await pumpHome(tester, p.h);
      await openSection(tester, 'Smena');
      await tapKey(tester, 'z-open');
      expect(
        find.byKey(const ValueKey<String>('bank-total-section')),
        findsOneWidget,
      );
      await tapKey(tester, 'z-close');
      final Map<String, Object?> closed = p.backend.closedShifts.values.single;
      expect(closed['status'], 'closed');
      expect(closed.containsKey('bank_terminal'), isFalse);
      expect(
        find.byKey(const ValueKey<String>('bank-terminal-summary')),
        findsNothing,
      );
    },
  );

  testWidgets(
    'Z close does not ask when the branch does not want it or the module is off',
    (WidgetTester tester) async {
      final P22 p = P22();
      _enable(p, _flow(askBankTotal: false));
      p.state.openShift();
      await pumpHome(tester, p.h);
      await openSection(tester, 'Smena');
      await tapKey(tester, 'z-open');
      expect(
        find.byKey(const ValueKey<String>('bank-total-section')),
        findsNothing,
      );
    },
  );

  testWidgets(
    'smart kassa: the provider total fills the field (sandbox) and shows the server difference',
    (WidgetTester tester) async {
      final P22 p = P22();
      _enable(
        p,
        _flow(cardMode: 'smart_kassa', smartKassa: true, checklist: true),
      );
      p.backend.providerCardTotal = 0;
      p.state.openShift();
      await pumpHome(tester, p.h);
      await openSection(tester, 'Smena');
      await tapKey(tester, 'z-open');
      expect(
        find.byKey(const ValueKey<String>('bank-provider')),
        findsOneWidget,
      );
      expect(
        find.byKey(const ValueKey<String>('bank-checklist')),
        findsNothing,
      );
      await tapKey(tester, 'bank-provider');
      expect(p.backend.closedShifts, isEmpty);
      expect(
        find.byKey(const ValueKey<String>('bank-difference')),
        findsOneWidget,
      );
      expect(find.text('Rizqim (karta)'), findsWidgets);
    },
  );
}
