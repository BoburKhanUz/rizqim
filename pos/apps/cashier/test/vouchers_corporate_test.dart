import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';
import 'package:rizqim_cashier/src/features/order/order_page.dart';

import 'support/harness.dart';
import 'support/p22.dart';
import 'support/restaurant.dart' show openOrderPage;
import 'support/wave4.dart';

/// P6-43/P6-44 (to'lqin 4, B): sertifikat bilan to'lash (kod → qoldiq → summa, aralash, rad sabablari, skaner),
/// sertifikat sotish (bo'nak cheki + varaq + ko'rinish), promo-kod buyurtmada, korxona xodimi (talon kodi, limitlar,
/// menejer PIN'isiz, rad etilsa naqd/karta), talon chop etish, X/Z dagi sertifikat va QR provayderlar.
void main() {
  P22 setUp({bool corporate = true, bool customerAccounts = true}) {
    final P22 p = P22();
    p.state.format = wave4Format(
      corporate: corporate,
      customerAccounts: customerAccounts,
    );
    p.state.openShift();
    p.backend.useVouchers();
    acceptDisplay(p);
    return p;
  }

  List<Object?> tenders(P22 p, String order) => <Object?>[
    for (final Object? x
        in p.backend.bills[order]!.single['payments']! as List<Object?>)
      (x! as Map<String, Object?>)['tender'],
  ];

  group('certificate as a tender', () {
    testWidgets(
      'code (any spelling) → balance and validity → part of the bill, the rest in cash; the balance goes down',
      (WidgetTester tester) async {
        final P22 p = setUp();
        final Map<String, Object?> v = p.backend.voucherBackend!.add(
          faceValue: '200000.00',
        );
        final String order = await openPayment(tester, p);
        await pickTender(tester, 'Sertifikat');
        await enter(tester, 'voucher-code', 'kqma cdef acde');
        await tapKey(tester, 'voucher-lookup');
        expect(
          find.byKey(const ValueKey<String>('voucher-found')),
          findsOneWidget,
        );
        expect(find.text('KQMA-CDEF-ACDE'), findsWidgets);
        expect(find.text(uzs(200000)), findsWidgets);
        expect(find.textContaining('22.10.2027 gacha'), findsOneWidget);
        // More than the balance is not taken
        await enter(tester, 'voucher-amount', '250000');
        await tapKey(tester, 'payment-add');
        expect(find.textContaining('Sertifikatda faqat'), findsOneWidget);
        await enter(tester, 'voucher-amount', '200000');
        await tapKey(tester, 'payment-add');
        expect(find.text('Sertifikat · KQMA-CDEF-ACDE'), findsOneWidget);
        // The rest in cash
        await tapKey(tester, 'payment-submit');
        expect(tenders(p, order), <String>['voucher', 'cash']);
        expect(v['balance'], '0.00');
        expect(v['status'], 'used');
        expect(p.receipts.last, contains('SOTUV CHEKI'));
        expect(find.byType(PaymentPage), findsNothing);
      },
    );

    testWidgets(
      'used on another till meanwhile: voucher_insufficient in plain words, the balance is read again; a blocked one '
      'cannot pay; the scanner opens the tab with the code',
      (WidgetTester tester) async {
        final P22 p = setUp();
        final Map<String, Object?> v = p.backend.voucherBackend!.add(
          faceValue: '100000.00',
        );
        p.backend.voucherBackend!.add(
          faceValue: '50000.00',
          code: 'BLOK-CDEF-FGHJ',
          status: 'blocked',
        );
        final String order = await openPayment(tester, p);
        // A keyboard scanner while the cash tab is open: the certificate tab opens and looks the code up
        p.h.services.hardware.scans.addKeyboard('KQMACDEFACDE');
        await tester.pumpAndSettle();
        expect(
          find.byKey(const ValueKey<String>('voucher-found')),
          findsOneWidget,
        );
        // Another till takes 90 000 of it first
        v['balance'] = '10000.00';
        await tapKey(tester, 'payment-add');
        await tapKey(tester, 'payment-submit');
        expect(
          find.textContaining(
            "Sertifikatda yetarli mablag' yo'q: qoldiq ${uzs(10000)}",
          ),
          findsWidgets,
        );
        expect(p.backend.bills[order]!.single['payments'], isEmpty);
        // The blocked one
        await pickTender(tester, 'Sertifikat');
        await enter(tester, 'voucher-code', 'BLOK-CDEF-FGHJ');
        await tapKey(tester, 'voucher-lookup');
        expect(find.textContaining('Sertifikat bloklangan'), findsWidgets);
        expect(
          find.byKey(const ValueKey<String>('voucher-amount')),
          findsNothing,
        );
        // Unknown code
        await enter(tester, 'voucher-code', 'XXXX-YYYY-ZZZZ');
        await tapKey(tester, 'voucher-lookup');
        expect(
          find.text('Sertifikat topilmadi: kodni tekshiring.'),
          findsOneWidget,
        );
      },
    );

    testWidgets(
      'a voucher part refunds back to the certificate (no cash, no drawer)',
      (WidgetTester tester) async {
        final P22 p = setUp();
        final Map<String, Object?> v = p.backend.voucherBackend!.add(
          faceValue: '600000.00',
        );
        final String order = await openPayment(
          tester,
          p,
          name: 'Malika Rahimova',
        );
        await pickTender(tester, 'Sertifikat');
        await enter(tester, 'voucher-code', '${v['code']}');
        await tapKey(tester, 'voucher-lookup');
        await tapKey(tester, 'payment-submit');
        expect(v['balance'], '91000.00');
        final String bill = '${p.backend.bills[order]!.single['uuid']}';
        await openBilling(tester, order);
        await tapKey(tester, 'refund-$bill');
        expect(
          find.textContaining('summa sertifikatga qaytadi'),
          findsOneWidget,
        );
        await tapText(tester, "Xato to'lov");
        await tapButton(tester, 'Qaytarish');
        expect(p.backend.refunds.single['method'], 'voucher');
        expect(v['balance'], '600000.00');
        expect(find.textContaining('sertifikatga qaytdi'), findsOneWidget);
        expect(p.drawerKicks, 0);
      },
    );
  });

  testWidgets(
    'selling a certificate: value, card fields, buyer; the advance receipt and the certificate sheet print; the X '
    'shows it apart from sales and the cash in the drawer',
    (WidgetTester tester) async {
      final P22 p = setUp();
      await pumpHome(tester, p.h);
      await openSection(tester, 'Smena');
      await tapKey(tester, 'voucher-sell');
      expect(find.text('Sertifikat sotish'), findsWidgets);
      await tapKey(tester, 'voucher-value-200000');
      await enter(tester, 'voucher-buyer', 'Dilnoza');
      await tapKey(tester, 'voucher-sell-submit');

      final Map<String, Object?> sold =
          p.backend.voucherBackend!.vouchers.values.single;
      expect(sold['face_value'], '200000.00');
      expect(p.backend.voucherSales.single['tender'], 'cash');
      final List<String> printed = p.receipts;
      expect(printed[printed.length - 2], contains("BO'NAK CHEKI"));
      expect(
        printed[printed.length - 2],
        contains("Sovg'a sertifikati (avans)"),
      );
      expect(printed.last, contains("SOVG'A SERTIFIKATI"));
      expect(printed.last, contains('${sold['code']}'));
      expect(printed.last, contains('Kimga: Dilnoza'));
      expect(p.drawerKicks, 1, reason: 'cash: the drawer opens');
      expect(
        p.h.printerFor('server-1').documents.last.qrCodes.single.data,
        '${sold['code']}',
      );
      // The preview, then a copy
      expect(find.text('Sertifikat tayyor'), findsOneWidget);
      expect(
        find.byKey(const ValueKey<String>('voucher-preview')),
        findsOneWidget,
      );
      await tapButton(tester, 'Qayta chop etish');
      expect(p.receipts.last, contains('NUSXA'));
      await tapButton(tester, 'Yopish');

      // X: the certificate section and the cash in the drawer
      await tapButton(tester, 'X hisobot');
      expect(find.text('Sertifikatlar'), findsOneWidget);
      expect(find.text("Sotildi (bo'nak) (1)"), findsOneWidget);
      expect(
        find.byKey(const ValueKey<String>('x-cash-vouchers')),
        findsOneWidget,
      );
      await tapButton(tester, 'Chop etish');
      expect(p.receipts.last, contains('SERTIFIKATLAR'));
      expect(p.receipts.last, contains('Sertifikat (naqd)'));
    },
  );

  testWidgets('a card sale needs its type and transaction id', (
    WidgetTester tester,
  ) async {
    final P22 p = setUp();
    await pumpHome(tester, p.h);
    await openSection(tester, 'Smena');
    await tapKey(tester, 'voucher-sell');
    await tapKey(tester, 'voucher-value-100000');
    await tester.tap(find.widgetWithText(ChoiceChip, 'Karta').last);
    await tester.pumpAndSettle();
    await tapKey(tester, 'voucher-sell-submit');
    expect(find.text('Karta turini tanlang.'), findsOneWidget);
    expect(p.backend.voucherSales, isEmpty);
  });

  testWidgets(
    'promo code on the order: applied, shown with the code, removed',
    (WidgetTester tester) async {
      final P22 p = setUp();
      p.backend
        ..seedPromo('BAHOR10', percent: '10.00')
        ..seedPromo('KATTA', amount: '20000.00', minOrder: '10000000.00');
      final String uuid = p.state.seedOrder(tableId: 1, guests: 3);
      // A room order with a discount line is taller than 800 px (as in discount_test)
      await pumpHome(tester, p.h, size: const Size(1280, 1000));
      await openOrderPage(tester, uuid);
      expect(find.byType(OrderPage), findsOneWidget);
      // Not enough for this code: plain words
      await tapKey(tester, 'order-promo');
      await enter(tester, 'promo-code', 'katta');
      await tapKey(tester, 'promo-submit');
      expect(
        find.textContaining("dan katta buyurtmaga ishlaydi"),
        findsOneWidget,
      );
      expect(p.state.orders[uuid]!['discount_reason'], isNull);

      await tapKey(tester, 'order-promo');
      await enter(tester, 'promo-code', 'bahor10');
      await tapKey(tester, 'promo-submit');
      expect(p.state.orders[uuid]!['discount_reason'], 'promo:BAHOR10');
      expect(p.backend.promoCodes['BAHOR10']!['used'], 1);
      expect(find.text('Promo-kod BAHOR10'), findsOneWidget);
      // One code per order: the button is gone while one is on
      expect(find.byKey(const ValueKey<String>('order-promo')), findsNothing);

      await tapText(tester, 'Olib tashlash');
      await tapButton(tester, 'Olib tashlash');
      expect(p.state.orders[uuid]!['discount_reason'], isNull);
      expect(p.backend.promoCodes['BAHOR10']!['used'], 0);
      expect(find.text('Promo-kod BAHOR10'), findsNothing);
    },
  );

  group('corporate member (talon)', () {
    late String account;

    P22 corporate() {
      final P22 p = setUp();
      account = p.backend.seedCorporate(
        name: 'Paxta Tekstil',
        company: '"Paxta Tekstil" AJ',
        monthlyLimit: '1000000.00',
      );
      p.backend
        ..seedMember(
          account,
          name: 'Aziz Rahimov',
          code: 'PT-0001',
          phone: '+998901112233',
          dailyLimit: '40000.00',
        )
        ..seedMember(
          account,
          name: 'Bobur Karimov',
          code: 'PT-0002',
          dailyLimit: '40000.00',
          active: false,
        )
        ..seedMember(account, name: 'Sardor', code: 'PT-0003', mealsPerDay: 1);
      return p;
    }

    testWidgets(
      'a cashier charges the lunch by the talon code with no manager PIN; what is left today is shown; over the limit '
      'is told before and refused with cash offered',
      (WidgetTester tester) async {
        final P22 p = corporate();
        final String order = await openPayment(
          tester,
          p,
          tableId: 11,
          lines: const <(int, String)>[(1, '2')],
        );
        final int due =
            (double.parse('${p.backend.bills[order]!.single['due_amount']}') *
                    100)
                .round();
        await pickTender(tester, 'Nasiya');
        // The format has both: the corporate mode is a choice
        await tester.tap(find.widgetWithText(ChoiceChip, 'Korxona'));
        await tester.pumpAndSettle();
        await enter(tester, 'member-code', 'PT-0001');
        await tapKey(tester, 'member-lookup');
        expect(
          find.byKey(const ValueKey<String>('member-found')),
          findsOneWidget,
        );
        expect(find.text('Aziz Rahimov'), findsOneWidget);
        expect(find.text('"Paxta Tekstil" AJ'), findsOneWidget);
        expect(
          find.byKey(const ValueKey<String>('member-today-left')),
          findsOneWidget,
        );
        expect(find.text('Yozish mumkin'), findsOneWidget);
        // Above the daily limit: told on the till already
        await enter(tester, 'member-amount', '50000');
        expect(find.text('Limit yetmaydi'), findsOneWidget);
        expect(find.textContaining("limitdan ko'p"), findsOneWidget);
        await enter(tester, 'member-amount', '${due ~/ 100}');
        await tapKey(tester, 'payment-submit');
        expect(find.text("Menejer tasdig'i"), findsNothing);
        expect(tenders(p, order), <String>['account']);
        final Map<String, Object?> pay =
            (p.backend.bills[order]!.single['payments']! as List<Object?>)
                    .single!
                as Map<String, Object?>;
        expect(pay['member_code'], 'PT-0001');
        expect(p.backend.entries[account]!.last['member_uuid'], isNotNull);
        expect(find.byType(PaymentPage), findsNothing);
      },
    );

    testWidgets('refusals in plain words with cash/card offered: an inactive talon, the subscription meal used, the daily limit '
        'the server counts', (WidgetTester tester) async {
      final P22 p = corporate();
      final String order = await openPayment(tester, p);
      // The scanner while the cash tab is open: a talon code goes to the corporate mode
      p.h.services.hardware.scans.addKeyboard('PT-0002');
      await tester.pumpAndSettle();
      expect(
        find.textContaining("Xodimning taloni to'xtatilgan"),
        findsWidgets,
      );
      expect(find.textContaining('Naqd yoki karta bilan oling'), findsWidgets);

      // Subscription: the meal of the day is used (charged elsewhere)
      p.backend.entries
          .putIfAbsent(account, () => <Map<String, Object?>>[])
          .add(<String, Object?>{
            'type': 'charge',
            'amount': '30000.00',
            'member_uuid': p.backend.members['PT-0003']!['uuid'],
          });
      await enter(tester, 'member-code', 'PT-0003');
      await tapKey(tester, 'member-lookup');
      expect(find.textContaining('bugungi ovqatini olgan'), findsWidgets);
      expect(find.text('tugadi'), findsOneWidget);

      // The till's numbers are old: the server refuses by the daily limit
      await enter(tester, 'member-code', 'PT-0001');
      await tapKey(tester, 'member-lookup');
      await enter(tester, 'member-amount', '40000');
      p.backend.entries
          .putIfAbsent(account, () => <Map<String, Object?>>[])
          .add(<String, Object?>{
            'type': 'charge',
            'amount': '30000.00',
            'member_uuid': p.backend.members['PT-0001']!['uuid'],
          });
      await tapKey(tester, 'payment-add');
      await tapKey(tester, 'payment-submit');
      expect(
        find.textContaining(
          "Xodimning kunlik limiti yetmaydi: bugun yana ${uzs(10000)} mumkin",
        ),
        findsWidgets,
      );
      expect(p.backend.bills[order]!.single['payments'], isEmpty);
    });

    testWidgets(
      'talons print from the accounts screen: the manager prints all, by code too',
      (WidgetTester tester) async {
        final P22 p = corporate();
        await pumpHome(tester, p.h, name: 'Malika Rahimova');
        await openSection(tester, 'Nasiya');
        await tapText(tester, 'Faqat qarzdorlar');
        await tapText(tester, 'Paxta Tekstil');
        await tapKey(tester, 'talon-print');
        expect(find.text('Talonlar · "Paxta Tekstil" AJ'), findsOneWidget);
        expect(find.text('Aziz Rahimov'), findsWidgets);
        await tapKey(tester, 'talon-print-all');
        final List<String> talons = p.receipts
            .where((String r) => r.contains('KORXONA TALONI'))
            .toList();
        expect(talons, hasLength(2), reason: 'the active members');
        expect(talons.first, contains('PT-0001'));
        expect(talons.first, contains('Kunlik limit:'));
        expect(talons.first, contains('40 000'));
        expect(talons.last, contains('1 marta'));
        await enter(tester, 'talon-code', 'PT-0003');
        await tapKey(tester, 'talon-print-code');
        expect(p.receipts.last, contains('Sardor'));
      },
    );
  });

  testWidgets('X shows the QR providers apart', (WidgetTester tester) async {
    final P22 p = setUp();
    p.backend.useQr();
    final String order = await openPayment(tester, p);
    await pickTender(tester, 'QR');
    await tapKey(tester, 'qr-create');
    p.backend.confirmQr('${p.backend.qrBackend!.qrs.values.single['uuid']}');
    await tester.pumpAndSettle();
    expect(p.backend.bills[order]!.single['status'], 'paid');
    await openSection(tester, 'Smena');
    await tapButton(tester, 'X hisobot');
    expect(find.text("QR to'lovlar"), findsOneWidget);
    expect(find.text('Click (1)'), findsOneWidget);
    await tapButton(tester, 'Chop etish');
    expect(p.receipts.last, contains("QR TO'LOVLAR"));
    expect(p.receipts.last, contains('Click (1):'));
  });
}
