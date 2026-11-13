import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/features/billing/qr_offline_guard.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';
import 'package:rizqim_cashier/src/features/floor/floor_page.dart';
import 'package:rizqim_cashier/src/features/order/order_page.dart';

import 'support/harness.dart';
import 'support/p22.dart';
import 'support/restaurant.dart' show openOrderPage;
import 'support/wave4.dart';

/// Review P6 wave 4 (C3, C11): a pending dynamic QR when the till goes offline; the QR confirmation racing "To'lash",
/// "Yangi QR" after parts were added, and the scanner's certificate/talon routing.

/// Runs [future] to the end, moving the fake clock (Dio and the sync sender wait on timers).
Future<T> drive<T>(WidgetTester tester, Future<T> future) async {
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

void main() {
  P22 setUp() {
    final P22 p = P22();
    p.state.format = wave4Format();
    p.state.openShift();
    p.backend
      ..useQr()
      ..useVouchers();
    acceptDisplay(p);
    return p;
  }

  int sales(P22 p) =>
      p.receipts.where((String r) => r.contains('SOTUV CHEKI')).length;

  testWidgets(
    'a QR confirmation arriving while "To\'lash" pays the rest: one sale receipt, one pop',
    (WidgetTester tester) async {
      final P22 p = setUp();
      final String order = await openPayment(tester, p);
      await pickTender(tester, 'QR');
      await enter(tester, 'qr-amount', '300000');
      await tapKey(tester, 'qr-create');
      final String qr = '${p.backend.qrBackend!.qrs.values.single['uuid']}';
      // The provider's callback lands just before the cashier's cash reaches the server
      final FakeResponse? Function(RequestOptions)? before =
          p.h.adapter.respond;
      p.h.adapter.respond = (RequestOptions o) {
        if (o.method == 'POST' && o.uri.path.endsWith('/payments')) {
          p.backend.confirmQr(qr);
        }
        return before?.call(o);
      };
      await pickTender(tester, 'Naqd');
      await enter(tester, 'tender-amount', '209000');
      await tapKey(tester, 'payment-submit');
      await tester.pump(const Duration(seconds: 4));
      await tester.pumpAndSettle();
      expect(p.backend.bills[order]!.single['status'], 'paid');
      expect(sales(p), 1, reason: 'the sale receipt prints once');
      expect(find.byType(PaymentPage), findsNothing);
      expect(find.byType(FloorPage), findsOneWidget, reason: 'popped once');
    },
  );

  testWidgets('"Yangi QR" after a part was added asks for what is due now', (
    WidgetTester tester,
  ) async {
    final P22 p = setUp();
    await openPayment(tester, p);
    await pickTender(tester, 'QR');
    await tapKey(tester, 'qr-create');
    final FakeQrBackend qr = p.backend.qrBackend!;
    expect(qr.qrs.values.single['amount'], '509000.00');
    // A part in cash is added meanwhile; the QR tab again, a new QR
    await pickTender(tester, 'Naqd');
    await enter(tester, 'tender-amount', '200000');
    await tapKey(tester, 'payment-add');
    await pickTender(tester, 'QR');
    await tapKey(tester, 'qr-renew');
    expect(find.byKey(const ValueKey<String>('qr-error')), findsNothing);
    expect(qr.qrs.values.last['amount'], '309000.00');
    expect(qr.qrs.values.first['status'], 'cancelled');
    await tester.pageBack();
    await tester.pumpAndSettle();
  });

  group('scanner routing', () {
    P22 corporate() {
      final P22 p = setUp();
      final String account = p.backend.seedCorporate(
        name: 'Paxta Tekstil',
        company: '"Paxta Tekstil" AJ',
        monthlyLimit: '1000000.00',
      );
      p.backend
        ..seedMember(
          account,
          name: 'Aziz Rahimov',
          code: 'TALON0000001',
          phone: '+998901112233',
          dailyLimit: '600000.00',
        )
        ..seedMember(
          account,
          name: 'Dilnoza Karimova',
          code: 'ACDEFGHJKMNP',
          dailyLimit: '600000.00',
        );
      return p;
    }

    testWidgets(
      'a 12-character talon code outside the certificate alphabet goes to the corporate lookup',
      (WidgetTester tester) async {
        final P22 p = corporate();
        await openPayment(tester, p);
        p.h.services.hardware.scans.addKeyboard('TALON0000001');
        await tester.pumpAndSettle();
        expect(
          find.byKey(const ValueKey<String>('member-found')),
          findsOneWidget,
        );
        expect(find.text('Aziz Rahimov'), findsOneWidget);
        expect(
          p.h.adapter.to('GET', RegExp(r'/pos/vouchers/')),
          isEmpty,
          reason: 'not looked up as a certificate',
        );
      },
    );

    testWidgets(
      'a certificate-shaped code that is no certificate (404) is tried as a talon',
      (WidgetTester tester) async {
        final P22 p = corporate();
        await openPayment(tester, p);
        p.h.services.hardware.scans.addKeyboard('ACDEFGHJKMNP');
        await tester.pumpAndSettle();
        expect(
          find.byKey(const ValueKey<String>('member-found')),
          findsOneWidget,
        );
        expect(find.text('Dilnoza Karimova'), findsOneWidget);
      },
    );
  });

  group('backend changes at the till', () {
    testWidgets(
      'S5: found by phone the talon code comes masked; the charge goes by the phone (not the masked code)',
      (WidgetTester tester) async {
        final P22 p = setUp();
        final String account = p.backend.seedCorporate(
          name: 'Paxta Tekstil',
          company: '"Paxta Tekstil" AJ',
          monthlyLimit: '1000000.00',
        );
        p.backend.seedMember(
          account,
          name: 'Aziz Rahimov',
          code: 'TALON0000001',
          phone: '+998901112233',
          dailyLimit: '600000.00',
        );
        final String order = await openPayment(tester, p);
        await pickTender(tester, 'Nasiya');
        await tester.tap(find.widgetWithText(ChoiceChip, 'Korxona'));
        await tester.pumpAndSettle();
        await enter(tester, 'member-code', '90 111 22 33');
        await tapKey(tester, 'member-lookup');
        expect(find.text('Aziz Rahimov'), findsOneWidget);
        expect(find.textContaining('TA********01'), findsOneWidget);
        await tapKey(tester, 'payment-submit');
        final Map<String, Object?> body =
            (p.h.adapter.to('POST', RegExp(r'/payments$')).last.data as Map)
                .cast<String, Object?>();
        final Map<Object?, Object?> tender =
            (body['tenders']! as List<Object?>).single!
                as Map<Object?, Object?>;
        expect(tender['member_code'], isNull);
        expect(tender['member_phone'], '90 111 22 33');
        expect(p.backend.bills[order]!.single['status'], 'paid');
        expect(find.byType(PaymentPage), findsNothing);
      },
    );
  });

  testWidgets(
    'C2 at the till: qr_refund_unknown in plain words with "Holatni tekshirish"; the check records the refund',
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
      await tapText(tester, "Xato to'lov");
      p.backend.qrRefundUnknown = true;
      await tapButton(tester, 'Qaytarish');
      expect(
        find.textContaining(
          "To'lov tizimi javob bermadi — holat tekshirilmoqda",
        ),
        findsOneWidget,
      );
      expect(p.backend.refunds, isEmpty);
      expect(p.drawerKicks, 0);
      // The provider's answer comes: the check (the same refund again) records it
      p.backend.qrRefundUnknown = false;
      await tapKey(tester, 'refund-check');
      expect(p.backend.refunds.single['method'], 'qr');
      expect(find.byKey(const ValueKey<String>('refund-check')), findsNothing);
    },
  );

  testWidgets(
    'C1 at the till: a promo code the server dropped (items under its minimum) is told in plain words',
    (WidgetTester tester) async {
      final P22 p = setUp();
      final String uuid = p.state.seedOrder(tableId: 1, guests: 3);
      p.state.orders[uuid]!['promo_note'] = "Promo-kod KATTA olib tashlandi: taomlar summasi kamida 80 000 so'm bo'lishi kerak.";
      await pumpHome(tester, p.h, size: const Size(1280, 1000));
      await openOrderPage(tester, uuid);
      expect(find.byType(OrderPage), findsOneWidget);
      expect(
        find.byKey(const ValueKey<String>('order-promo-note')),
        findsOneWidget,
      );
      expect(
        find.textContaining('Promo-kod KATTA olib tashlandi'),
        findsOneWidget,
      );
    },
  );

  testWidgets(
    'C7 at the till: an account below zero after a refund reads "Ortiqcha to\'lov", not a negative debt',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      p.backend.seedAccount(
        name: 'Jamshid aka',
        phone: '+998901112233',
        debt: '-15000.00',
      );
      await pumpHome(tester, p.h);
      await openSection(tester, 'Nasiya');
      await tapText(tester, 'Faqat qarzdorlar');
      await enter(tester, 'accounts-search', '1112');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();
      expect(find.textContaining("Ortiqcha to'lov"), findsOneWidget);
      await tester.tap(find.text('Jamshid aka'));
      await tester.pumpAndSettle();
      expect(find.textContaining("Ortiqcha to'lov"), findsWidgets);
      expect(find.text(uzs(15000)), findsWidgets);
    },
  );

  group('C3: a pending QR when the till goes offline', () {
    late FakeSyncBackend sync;

    Future<P22> offlineTill() async {
      final P22 p = P22(
        offlineStore: await SyncStore.open(MemorySyncStorage()),
      );
      p.state.format = wave4Format();
      p.state.openShift();
      p.backend.useQr();
      acceptDisplay(p);
      sync = FakeSyncBackend(p.state);
      return p;
    }

    /// The payment page with a Click QR for the whole bill, then the network goes.
    Future<(String, String)> qrThenOffline(WidgetTester tester, P22 p) async {
      final String order = await openPayment(tester, p);
      await pickTender(tester, 'QR');
      await tapKey(tester, 'qr-create');
      final String qr = '${p.backend.qrBackend!.qrs.values.single['uuid']}';
      p.h.adapter.offline = true;
      await drive(
        tester,
        p.h.data.floor.load().catchError((_) => p.h.data.floor.load()),
      );
      await tester.pump(const Duration(seconds: 3));
      await tester.pumpAndSettle();
      expect(p.h.data.offline!.active, isTrue);
      return (order, qr);
    }

    Future<void> reconnect(WidgetTester tester, P22 p) async {
      p.h.adapter.offline = false;
      await drive(tester, p.h.data.offline!.syncNow());
      await tester.pumpAndSettle();
    }

    List<Object?> sentTypes() => <Object?>[
      for (final Map<String, Object?> o in sync.received) o['type'],
    ];

    testWidgets(
      'offline the QR stays in sight ("Aloqa yo\'q"), no new QR or cancel; cash asks for a confirmation (declined: nothing taken)',
      (WidgetTester tester) async {
        final P22 p = await offlineTill();
        await qrThenOffline(tester, p);
        expect(find.byKey(const ValueKey<String>('qr-image')), findsOneWidget);
        expect(
          find.text("Aloqa yo'q — to'lov tizimi hali tasdiqlashi mumkin"),
          findsOneWidget,
        );
        expect(find.byKey(const ValueKey<String>('qr-renew')), findsNothing);
        expect(find.byKey(const ValueKey<String>('qr-cancel')), findsNothing);
        await tapKey(tester, 'payment-submit');
        expect(
          find.textContaining(
            "Mijoz QR orqali to'lamaganiga ishonch hosil qiling",
          ),
          findsOneWidget,
        );
        await tapButton(tester, "Yo'q");
        expect(p.h.data.offline!.store.pendingCount, 0);
        expect(find.byType(PaymentPage), findsOneWidget);
        // Back online with nothing queued: the panel reads the QR again (still waiting), then the page is left
        p.h.adapter.offline = false;
        // (the till's probe finds the server)
        for (var i = 0; i < 70 && p.h.data.offline!.active; i++) {
          await tester.pump(const Duration(seconds: 1));
        }
        await tester.pump(const Duration(seconds: 3));
        await tester.pumpAndSettle();
        expect(p.h.data.offline!.active, isFalse);
        await pickTender(tester, 'QR');
        expect(find.byKey(const ValueKey<String>('qr-cancel')), findsOneWidget);
        expect(
          find.textContaining("Mijoz to'lashini kutmoqda"),
          findsOneWidget,
        );
        await tester.pageBack();
        await tester.pumpAndSettle();
      },
    );

    testWidgets('cash taken offline (confirmed and recorded), the customer did not pay: back online the QR is cancelled on '
        'the server first, then the cash goes', (WidgetTester tester) async {
      final P22 p = await offlineTill();
      final (String order, String qr) = await qrThenOffline(tester, p);
      await tapKey(tester, 'payment-submit');
      await tapButton(tester, "To'lamagan — olaman");
      expect(find.byType(PaymentPage), findsNothing);
      final QrOfflineTender rec = p.h.services.qrGuard!.records.single;
      expect(rec.qrUuid, qr);
      expect(rec.staffName, isNotNull);
      expect(rec.outcome, isNull);

      await reconnect(tester, p);
      final Map<String, Object?> q = p.backend.qrBackend!.qrs[qr]!;
      expect(q['status'], 'cancelled');
      expect(q['cancel_reason'], 'offline_tender');
      expect(sentTypes(), contains('bill.pay'));
      final Map<String, Object?> bill = p.backend.bills[order]!.single;
      expect(bill['status'], 'paid');
      expect(
        (bill['payments']! as List<Object?>).map(
          (Object? x) => (x! as Map<String, Object?>)['tender'],
        ),
        <String>['cash'],
      );
      expect(
        p.h.services.qrGuard!.records.single.outcome,
        QrOfflineTender.qrCancelled,
      );
      expect(find.textContaining('mijozga qaytaring'), findsNothing);
    });

    testWidgets(
      'the provider paid while the till was offline: the offline cash is not sent, the cashier is told to give it '
      'back and marks it returned (kept in the log)',
      (WidgetTester tester) async {
        final P22 p = await offlineTill();
        final (String order, String qr) = await qrThenOffline(tester, p);
        await tapKey(tester, 'payment-submit');
        await tapButton(tester, "To'lamagan — olaman");
        // The customer's phone paid meanwhile (the server took the provider's callback)
        p.backend.confirmQr(qr);

        await reconnect(tester, p);
        expect(sentTypes(), isNot(contains('bill.pay')));
        final Map<String, Object?> bill = p.backend.bills[order]!.single;
        expect(
          (bill['payments']! as List<Object?>).map(
            (Object? x) => (x! as Map<String, Object?>)['tender'],
          ),
          <String>['qr'],
          reason: 'no second payment for the bill',
        );
        expect(p.h.data.offline!.store.pendingCount, 0);
        expect(
          p.h.data.offline!.store.errors.single.reason,
          QrOfflineGuard.droppedReason,
        );
        expect(
          find.textContaining("serverga yuborilmadi — pulni mijozga qaytaring"),
          findsOneWidget,
        );
        await tapText(tester, 'Qaytarildi');
        expect(find.textContaining('mijozga qaytaring'), findsNothing);
        final QrOfflineTender rec = p.h.services.qrGuard!.records.single;
        expect(rec.outcome, QrOfflineTender.dropped);
        expect(rec.returnedAt, isNotNull);
        expect(rec.returnedBy, isNotNull);
      },
    );
  });
}
