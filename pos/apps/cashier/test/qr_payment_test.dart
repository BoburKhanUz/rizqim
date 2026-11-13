import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';
import 'package:rizqim_cashier/src/features/floor/floor_page.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/harness.dart';
import 'support/p22.dart';
import 'support/wave4.dart';

/// P6-41/P6-42 (to'lqin 4, B): dinamik QR kassada — provayder tanlash, QR katta, mijoz ekraniga (bulut va LAN),
/// muddat, real-time va 3 soniyalik so'rash, to'langach chek o'zi, bekor qilish, yangi QR (`held` da yo'q), aralash
/// to'lov, statik QR (qo'lda), modul o'chiq, provayder ulanmagan, qaytarish (provayder rad etdi, faqat to'liq).
void main() {
  P22 setUp({bool realtime = true, bool display = true, bool qr = true}) {
    final P22 p = P22(realtime: realtime);
    p.state.format = wave4Format(display: display, qr: qr);
    p.state.openShift();
    if (qr) p.backend.useQr();
    acceptDisplay(p);
    return p;
  }

  Map<String, Object?> lastDisplayBody(P22 p) =>
      (p.h.adapter.to('POST', '/display/cart').last.data as Map)
          .cast<String, Object?>();

  testWidgets(
    'QR: create (default provider), big QR + countdown, on the display (cloud and LAN); the provider confirms → '
    'the bill is paid and the sale receipt prints with no cashier tap',
    (WidgetTester tester) async {
      final P22 p = setUp();
      final String order = await openPayment(tester, p);
      await pickTender(tester, 'QR');
      // Provider chips (Click default), no manual reference field
      expect(find.byKey(const ValueKey<String>('qr-provider')), findsOneWidget);
      expect(find.text("To'lov raqami (ixtiyoriy)"), findsNothing);
      await tapKey(tester, 'qr-create');

      final FakeQrBackend qr = p.backend.qrBackend!;
      final Map<String, Object?> made = qr.qrs.values.single;
      expect(made['provider'], 'click');
      expect(made['amount'], '509000.00');
      expect(find.byKey(const ValueKey<String>('qr-image')), findsOneWidget);
      expect(find.text('Click · ${uzs(509000)}'), findsOneWidget);
      expect(
        find.textContaining("Mijoz to'lashini kutmoqda · 5:00"),
        findsOneWidget,
      );
      // The customer display: payment with the QR, through the server and the LAN publisher
      final PosDisplayCart lan = p.h.services.lanPublisher.lastCart!;
      expect(lan.state, PosDisplayScreen.payment);
      expect(lan.payment!.qr!.payload, made['payload']);
      expect(lan.payment!.qr!.provider, 'click');
      await settle(tester, p.h.data.displayFeed.flush());
      final Map<String, Object?> cloud = lastDisplayBody(p);
      expect(cloud['state'], 'payment');
      expect(
        ((cloud['payment']! as Map)['qr']! as Map)['payload'],
        made['payload'],
      );

      // "To'lash" never takes a manual QR while the dynamic one is shown
      await tapKey(tester, 'payment-submit');
      expect(
        find.textContaining('QR to\'lovni provayder tasdiqlaydi'),
        findsOneWidget,
      );
      expect(p.backend.bills[order]!.single['payments'], isEmpty);

      // The provider's webhook: realtime qr.paid → the till reads the bill again → receipt, back to the floor
      p.backend.confirmQr('${made['uuid']}');
      await tester.pumpAndSettle();
      final Map<String, Object?> bill = p.backend.bills[order]!.single;
      expect(bill['status'], 'paid');
      final Map<String, Object?> pay =
          (bill['payments']! as List<Object?>).single! as Map<String, Object?>;
      expect(pay['tender'], 'qr');
      expect(pay['qr_payment_uuid'], made['uuid']);
      expect(p.receipts.last, contains('SOTUV CHEKI'));
      expect(p.drawerKicks, 0, reason: 'no cash, no drawer');
      expect(find.byType(PaymentPage), findsNothing);
      expect(find.byType(FloorPage), findsOneWidget);
      expect(p.h.services.lanPublisher.lastCart!.state, PosDisplayScreen.paid);
    },
  );

  testWidgets('no realtime: the till asks every 3 s and sees the payment', (
    WidgetTester tester,
  ) async {
    final P22 p = setUp(realtime: false);
    final String order = await openPayment(tester, p);
    await pickTender(tester, 'QR');
    // Payme this time
    await tester.tap(find.widgetWithText(ChoiceChip, 'Payme'));
    await tester.pumpAndSettle();
    await tapKey(tester, 'qr-create');
    final Map<String, Object?> made = p.backend.qrBackend!.qrs.values.single;
    expect(made['provider'], 'payme');
    p.backend.confirmQr('${made['uuid']}');
    await tester.pump(const Duration(seconds: 1));
    expect(find.byType(PaymentPage), findsOneWidget, reason: 'not asked yet');
    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();
    expect(p.backend.bills[order]!.single['status'], 'paid');
    expect(find.byType(PaymentPage), findsNothing);
    expect(
      p.h.adapter.to('GET', RegExp(r'/pos/qr/[^/]+$')),
      isNotEmpty,
      reason: 'polled',
    );
  });

  testWidgets(
    'cancel takes the QR off the display; expiry tells it; "Yangi QR" is off while the provider holds it',
    (WidgetTester tester) async {
      final P22 p = setUp();
      await openPayment(tester, p);
      await pickTender(tester, 'QR');
      await tapKey(tester, 'qr-create');
      final FakeQrBackend qr = p.backend.qrBackend!;
      final String first = '${qr.qrs.values.single['uuid']}';

      await tapKey(tester, 'qr-cancel');
      expect(qr.qrs[first]!['status'], 'cancelled');
      expect(qr.qrs[first]!['cancel_reason'], 'cashier');
      expect(find.text('QR bekor qilindi.'), findsOneWidget);
      expect(p.h.services.lanPublisher.lastCart?.payment?.qr, isNull);

      // A new one, then it expires (qr.expired)
      await tapKey(tester, 'qr-create');
      final String second = qr.qrs.keys.last;
      expect(second, isNot(first));
      p.backend.expireQr(second);
      await tester.pumpAndSettle();
      expect(find.textContaining('QR muddati tugadi'), findsOneWidget);
      expect(p.h.services.lanPublisher.lastCart?.payment?.qr, isNull);

      // A third: the provider starts taking the money (held) — no new QR, no cancel
      await tapKey(tester, 'qr-create');
      final String third = qr.qrs.keys.last;
      p.backend.holdQr(third);
      await tester.pump(const Duration(seconds: 3));
      await tester.pumpAndSettle();
      expect(find.textContaining("Mijoz to'lamoqda"), findsOneWidget);
      expect(
        tester
            .widget<RzButton>(find.byKey(const ValueKey<String>('qr-renew')))
            .onPressed,
        isNull,
      );
      expect(
        tester
            .widget<RzButton>(find.byKey(const ValueKey<String>('qr-cancel')))
            .onPressed,
        isNull,
      );
      // Leaving the page: the display goes back to what it showed before the QR
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(p.h.services.lanPublisher.lastCart?.payment?.qr, isNull);
    },
  );

  testWidgets(
    'mixed: QR for a part (the bill stays open), the rest in cash; the QR goes on the bill print',
    (WidgetTester tester) async {
      final P22 p = setUp();
      final String order = await openPayment(tester, p);
      await pickTender(tester, 'QR');
      await enter(tester, 'qr-amount', '300000');
      await tapKey(tester, 'qr-create');
      final Map<String, Object?> made = p.backend.qrBackend!.qrs.values.single;
      expect(made['amount'], '300000.00');
      await tapKey(tester, 'qr-print');
      expect(p.receipts.last, contains("QR orqali to'lash"));
      expect(p.receipts.last, contains('Bu fiskal chek emas'));
      expect(p.h.printerFor('server-1').documents.last.qrCodes, hasLength(1));

      p.backend.confirmQr('${made['uuid']}');
      await tester.pumpAndSettle();
      expect(find.textContaining("Qisman to'landi"), findsOneWidget);
      expect(p.backend.bills[order]!.single['due_amount'], '209000.00');
      expect(find.byType(PaymentPage), findsOneWidget);

      // The rest in cash (the cash tab is open again with what is left)
      await tapKey(tester, 'payment-submit');
      final Map<String, Object?> bill = p.backend.bills[order]!.single;
      expect(bill['status'], 'paid');
      expect(
        (bill['payments']! as List<Object?>).map(
          (Object? x) => (x! as Map<String, Object?>)['tender'],
        ),
        <String>['qr', 'cash'],
      );
      expect(p.receipts.last, contains('SOTUV CHEKI'));
      expect(p.drawerKicks, 1);
    },
  );

  testWidgets(
    'static QR by hand: the module off (today\'s flow), or the cashier picks it; a provider not set up is told plainly',
    (WidgetTester tester) async {
      final P22 p = setUp();
      p.backend.qrBackend!.notConfigured.add('click');
      final String order = await openPayment(tester, p);
      await pickTender(tester, 'QR');
      await tapKey(tester, 'qr-create');
      expect(
        find.textContaining("do'konning o'z merchant hisobiga ulanmagan"),
        findsOneWidget,
      );
      await tapKey(tester, 'qr-manual');
      expect(find.text("To'lov raqami (ixtiyoriy)"), findsOneWidget);
      await tapKey(tester, 'payment-submit');
      final Map<String, Object?> pay =
          (p.backend.bills[order]!.single['payments']! as List<Object?>).single!
              as Map<String, Object?>;
      expect(pay['tender'], 'qr');
      expect(pay['qr_payment_uuid'], isNull);
    },
  );

  testWidgets('module off: the QR tab is the static QR confirmed by hand', (
    WidgetTester tester,
  ) async {
    final P22 p = setUp(qr: false);
    await openPayment(tester, p);
    await pickTender(tester, 'QR');
    expect(find.byKey(const ValueKey<String>('qr-create')), findsNothing);
    expect(find.text("To'lov raqami (ixtiyoriy)"), findsOneWidget);
  });

  testWidgets(
    'refund of a provider QR: partial is refused, a failed reversal stays blocked with a plain message (no cash)',
    (WidgetTester tester) async {
      final P22 p = setUp();
      final String order = await openPayment(
        tester,
        p,
        name: 'Malika Rahimova',
      );
      await pickTender(tester, 'QR');
      await tapKey(tester, 'qr-create');
      p.backend.confirmQr('${p.backend.qrBackend!.qrs.values.single['uuid']}');
      await tester.pumpAndSettle();
      final String bill = '${p.backend.bills[order]!.single['uuid']}';
      await openBilling(tester, order);
      await tapKey(tester, 'refund-$bill');
      await tester.enterText(
        find.widgetWithText(TextField, 'Qaytariladigan summa'),
        '100000',
      );
      await tester.pumpAndSettle();
      await tapText(tester, "Xato to'lov");
      await tapButton(tester, 'Qaytarish');
      expect(
        find.textContaining("QR to'lovini faqat to'liq qaytarish mumkin"),
        findsOneWidget,
      );
      expect(p.backend.refunds, isEmpty);

      p.backend.qrRefundFails = true;
      await tester.enterText(
        find.widgetWithText(TextField, 'Qaytariladigan summa'),
        '509000',
      );
      await tester.pumpAndSettle();
      await tapButton(tester, 'Qaytarish');
      expect(find.textContaining("Click to'lovni qaytarmadi"), findsOneWidget);
      expect(p.backend.refunds, isEmpty);
      expect(p.drawerKicks, 0);

      p.backend.qrRefundFails = false;
      await tapButton(tester, 'Qaytarish');
      expect(p.backend.refunds.single['method'], 'qr');
      expect(p.backend.qrBackend!.qrs.values.single['status'], 'refunded');
    },
  );
}
