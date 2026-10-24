import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/features/billing/billing_page.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';
import 'package:rizqim_cashier/src/features/billing/split_builder.dart';
import 'package:rizqim_cashier/src/features/floor/floor_page.dart';

import 'support/harness.dart';
import 'support/p22.dart';

void main() {
  test(
    'split math: equal shares keep the remainder tiyin on the first bill',
    () {
      expect(equalShares(const PosMoney.som(509000), 3), <PosMoney>[
        PosMoney.parse('169666.68'),
        PosMoney.parse('169666.66'),
        PosMoney.parse('169666.66'),
      ]);
      expect(
        perPerson(const PosMoney.som(509000), 3),
        const PosMoney.som(169667),
      );
    },
  );

  test('a bill being paid is told by its payments, as the server keeps it open/printed (no "paying")', () {
    PosBill bill(String status, {int paid = 0, int total = 200000}) => PosBill(
      uuid: 'b',
      orderUuid: 'o',
      number: 1,
      status: status,
      total: PosMoney.som(total),
      paidAmount: PosMoney.som(paid),
      dueAmount: PosMoney.som(total - paid),
    );
    expect(bill('printed').isTaken, isFalse);
    expect(bill('printed', paid: 50000).isTaken, isTrue);
    expect(bill('printed', paid: 50000).awaitingReceipt, isFalse);
    expect(bill('printed', paid: 200000).awaitingReceipt, isTrue);
    expect(bill('paid', paid: 200000).awaitingReceipt, isFalse);
    expect(bill('void', paid: 50000).isTaken, isFalse);
    expect(billStatus(bill('printed', paid: 50000)).$1, "Qisman to'langan");
    expect(billStatus(bill('open', paid: 200000)).$1, 'Fiskal kutmoqda');
    expect(billStatus(bill('printed')).$1, 'Chiqarilgan');
  });

  test('a deposit is shared over the split bills, not taken off each one', () {
    PosBill bill(int n, {String status = 'open', int paid = 0}) => PosBill(
      uuid: 'b$n',
      orderUuid: 'o',
      number: n,
      status: status,
      total: const PosMoney.som(200000),
      paidAmount: PosMoney.som(paid),
      dueAmount: PosMoney.som(200000 - paid),
    );
    final List<PosBill> bills = <PosBill>[bill(3), bill(1), bill(2)];
    const PosMoney deposit = PosMoney.som(300000);
    expect(
      bills.map((PosBill b) => depositShare(bills, b, deposit)),
      <PosMoney>[
        PosMoney.zero,
        const PosMoney.som(200000),
        const PosMoney.som(100000),
      ],
    );
    // A void or covered bill takes none of it
    final List<PosBill> other = <PosBill>[
      bill(1, status: 'void'),
      bill(2, paid: 200000),
      bill(3),
    ];
    expect(depositShare(other, other[0], deposit), PosMoney.zero);
    expect(depositShare(other, other[1], deposit), PosMoney.zero);
    expect(depositShare(other, other[2], deposit), const PosMoney.som(200000));
  });

  testWidgets(
    'equal split shows each share, creates 3 bills; pre-check prints, a reprint is a NUSXA with approval',
    (WidgetTester tester) async {
      final P22 p = P22();
      // 1.5 kg osh + 2 choy = 190 000, xizmat haqi 10% = 19 000, xona puli 300 000 → 509 000
      final String uuid = p.state.seedOrder(tableId: 1, guests: 3);
      await pumpHome(tester, p.h);
      await openBilling(tester, uuid);

      expect(find.byType(BillingPage), findsOneWidget);
      expect(find.text('Schyot tuzish'), findsWidgets);
      expect(find.text('Kishi boshiga (3 kishi)'), findsOneWidget);
      expect(find.text(uzs(169667)), findsOneWidget);
      await tester.tap(find.text("Teng bo'lish"));
      await tester.pumpAndSettle();
      expect(find.text('1-schyot'), findsOneWidget);
      expect(find.text(uzs(169666.68)), findsOneWidget);
      expect(find.text(uzs(169666.66)), findsNWidgets(2));
      expect(find.text(uzs(509000)), findsWidgets);

      await tapButton(tester, 'Schyot tuzish');
      final List<Map<String, Object?>> bills = p.backend.bills[uuid]!;
      expect(bills, hasLength(3));
      expect(
        bills.map((Map<String, Object?> b) => b['split_mode']).toSet(),
        <Object?>{'equal'},
      );
      expect(find.text('Schyotlar (3)'), findsOneWidget);
      expect(find.text('Schyot 1/3'), findsOneWidget);
      expect(find.text('Bu fiskal chek emas'), findsOneWidget);
      expect(find.text('Xona puli'), findsOneWidget);
      expect(find.text('Xizmat haqi'), findsOneWidget);

      await tapButton(tester, 'Schyotni chiqarish');
      final String check = p.receipts.single;
      expect(check, contains('SCHYOT'));
      expect(check, contains('Schyot 1/3'));
      expect(check, contains('Bu fiskal chek emas'));
      expect(check, contains('1-xona'));
      expect(check, isNot(contains('NUSXA')));

      // Qayta chiqarish: kassirda pos.reprint_bill yo'q — menejer tasdig'i
      await tapButton(tester, 'Nusxa chiqarish');
      expect(find.text("Menejer tasdig'i"), findsOneWidget);
      expect(find.text('Schyotni qayta chiqarish'), findsOneWidget);
      await typePin(tester, '5926');
      await tester.pumpAndSettle();
      expect(p.receipts, hasLength(2));
      expect(p.receipts.last, contains('NUSXA'));
    },
  );

  testWidgets('items split: a kg dish is shared between two bills', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    final String uuid = p.state.seedOrder(tableId: 1, guests: 4);
    await pumpHome(tester, p.h);
    await openBilling(tester, uuid);

    await tester.tap(find.text("Taom bo'yicha"));
    await tester.pumpAndSettle();
    // Hammasi 1-schyotda: 2-schyot bo'sh — tuzib bo'lmaydi
    expect(
      find.text("Har schyotda kamida bitta taom bo'lsin."),
      findsOneWidget,
    );
    await tapButton(tester, 'Schyot tuzish');
    expect(p.backend.bills[uuid], anyOf(isNull, isEmpty));

    // 2-schyot tanlangan: oshdan 0.5 kg unga
    await tester.tap(find.text('Ulush').first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('0.5'));
    await tester.pumpAndSettle();
    await tester.tap(find.textContaining('Belgilash'));
    await tester.pumpAndSettle();
    expect(find.text('Osh (Choyxona oshi) · 1.5 kg'), findsOneWidget);
    expect(find.text('1-sch: 1 kg · 2-sch: 0.5 kg'), findsOneWidget);

    await tapButton(tester, 'Schyot tuzish');
    final List<Map<String, Object?>> bills = p.backend.bills[uuid]!;
    expect(bills, hasLength(2));
    final List<Object?> second = bills.last['items']! as List<Object?>;
    expect(second, hasLength(1));
    expect((second.single! as Map<String, Object?>)['quantity'], 0.5);
  });

  testWidgets(
    'card needs type and transaction id; mixed cash + card; the SALE receipt prints, the drawer opens, back to the floor',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      final String uuid = p.state.seedOrder(tableId: 1, guests: 3);
      await pumpHome(tester, p.h);
      await openBilling(tester, uuid);
      await tapButton(tester, 'Schyot tuzish');
      await tapText(tester, "To'lash: ", contains: true);
      expect(find.byType(PaymentPage), findsOneWidget);
      expect(find.byKey(const ValueKey<String>('payment-due')), findsOneWidget);
      expect(find.text(uzs(509000)), findsWidgets);

      // Karta: turi va tranzaksiya raqami majburiy
      await tester.tap(find.widgetWithText(ChoiceChip, 'Karta'));
      await tester.pumpAndSettle();
      await tapKey(tester, 'payment-submit');
      expect(find.text('Karta turini tanlang.'), findsOneWidget);
      expect(p.backend.bills[uuid]!.single['payments'], isEmpty);

      // Naqd 100 000 (mijoz 200 000 berdi, qaytim 100 000) + qolgani kartada
      await tester.tap(find.widgetWithText(ChoiceChip, 'Naqd'));
      await tester.pumpAndSettle();
      await enter(tester, 'tender-amount', '100000');
      await enter(tester, 'tender-given', '200000');
      expect(
        tester
            .widget<Text>(find.byKey(const ValueKey<String>('tender-change')))
            .data,
        uzs(100000),
      );
      await tapKey(tester, 'payment-add');
      expect(
        find.text('Naqd · berildi 200${' '}000, qaytim 100${' '}000'),
        findsOneWidget,
      );
      await tester.tap(find.widgetWithText(ChoiceChip, 'Karta'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(ChoiceChip, 'Humo'));
      await tester.pumpAndSettle();
      await tapKey(tester, 'payment-submit');
      expect(
        find.text('Terminal chekidagi tranzaksiya raqamini kiriting.'),
        findsOneWidget,
      );
      await enter(tester, 'tender-txn', 'RRN123');
      await tapKey(tester, 'payment-submit');

      // To'landi: sotuv cheki, kassa qutisi, buyurtma yopildi → zal
      final Map<String, Object?> bill = p.backend.bills[uuid]!.single;
      expect(bill['status'], 'paid');
      expect(
        (bill['payments']! as List<Object?>).map(
          (Object? x) => (x! as Map<String, Object?>)['tender'],
        ),
        <String>['cash', 'card'],
      );
      expect(p.state.orders[uuid]!['status'], 'delivered');
      final String receipt = p.receipts.last;
      expect(receipt, contains('SOTUV CHEKI'));
      expect(receipt, contains('Naqd:'));
      expect(receipt, contains('Karta (Humo):'));
      expect(receipt, contains('Tranzaksiya: RRN123'));
      expect(receipt, contains('Aralash'));
      expect(receipt, contains('Qaytim:'));
      // P3-26: to'lov javobi ro'yxatdan o'tgan chekni olib keladi — kassir haqiqiy fiskal belgini chop etadi
      expect(receipt, contains('Fiskal belgi:'));
      expect(receipt, contains('Kassa: EP000000000123'));
      expect(receipt, isNot(contains("Fiskal ma'lumot kutilmoqda")));
      expect(p.drawerKicks, 1);
      expect(find.byType(PaymentPage), findsNothing);
      expect(find.byType(BillingPage), findsNothing);
      expect(find.byType(FloorPage), findsOneWidget);
    },
  );

  testWidgets(
    'the cashier prints the issued receipt: fiscal sign and QR on paper',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h);
      await openBilling(tester, uuid);
      await tapButton(tester, 'Schyot tuzish');
      await tapText(tester, "To'lash: ", contains: true);
      await tester.tap(find.widgetWithText(ChoiceChip, 'QR'));
      await tester.pumpAndSettle();
      await tapKey(tester, 'payment-submit');
      final String receipt = p.receipts.last;
      expect(receipt, contains('SOTUV CHEKI'));
      expect(receipt, contains('Fiskal belgi:'));
      expect(receipt, contains('MXIK: 10202001001000000'));
      expect(receipt, contains('Bu fiskal chek emas (sinov rejimi).'));
      expect(p.h.printerFor('server-1').documents.last.qrCodes, hasLength(1));
      expect(p.drawerKicks, 0);
    },
  );

  testWidgets(
    'fiscal pending: money kept, "Fiskal kutmoqda", retry issues and prints the receipt',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      p.backend
        ..fiscalMode = FakeFiscalMode.pending
        ..retryFailures = 1;
      final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h);
      await openBilling(tester, uuid);
      await tapButton(tester, 'Schyot tuzish');
      await tapText(tester, "To'lash: ", contains: true);
      await tapKey(tester, 'payment-submit');

      expect(find.text('Fiskal kutmoqda'), findsOneWidget);
      expect(p.state.orders[uuid]!['status'], isNot('delivered'));
      // Pul olindi: kassa qutisi ochildi, chek hali chiqmadi
      expect(p.drawerKicks, 1);
      expect(
        p.receipts.where((String r) => r.contains('SOTUV CHEKI')),
        isEmpty,
      );

      await tapButton(tester, 'Qayta urinish');
      expect(find.textContaining('Chek hali kutilmoqda'), findsOneWidget);
      expect(find.text('Fiskal kutmoqda'), findsOneWidget);

      await tapButton(tester, 'Qayta urinish');
      expect(p.receipts.last, contains('SOTUV CHEKI'));
      expect(p.state.orders[uuid]!['status'], 'delivered');
      expect(find.byType(FloorPage), findsOneWidget);
    },
  );

  testWidgets(
    'prepayment from the booking deposit + nasiya with the manager approval',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      final String account = p.backend.seedAccount(
        name: 'Jamshid aka',
        phone: '+998901112233',
        limit: '2000000.00',
      );
      final String booking = p.backend.seedBooking(
        tableId: 2,
        start: p.h.now.subtract(const Duration(minutes: 30)),
        deposit: '100000.00',
      );
      await pumpHome(tester, p.h);
      final PosArrival arrival = await settle(
        tester,
        p.h.data.bookings.arrive(booking),
      );
      p.state.seedRound(arrival.orderUuid, const <(int, String)>[
        (3, '2'),
        (1, '4'),
      ]);
      await openBilling(tester, arrival.orderUuid);
      expect(find.text('Bron avansi (mavjud)'), findsOneWidget);
      await tapButton(tester, 'Schyot tuzish');
      final Map<String, Object?> bill =
          p.backend.bills[arrival.orderUuid]!.single;
      final PosMoney total = PosMoney.parse(bill['total']);
      await tapText(tester, "To'lash: ", contains: true);

      // Avans birinchi taklif qilinadi
      expect(find.text('Bron avansi: mavjud'), findsOneWidget);
      await tapKey(tester, 'payment-add');
      expect(find.text('Avans hisobidan'), findsOneWidget);

      // Qolgani nasiyaga
      await tester.tap(find.widgetWithText(ChoiceChip, 'Nasiya'));
      await tester.pumpAndSettle();
      expect(find.textContaining("menejer tasdig'i"), findsOneWidget);
      await enter(tester, 'account-search', '1112');
      await tapButton(tester, 'Qidirish');
      await tester.tap(find.text('Jamshid aka'));
      await tester.pumpAndSettle();
      await tapKey(tester, 'payment-submit');
      expect(find.text("Menejer tasdig'i"), findsOneWidget);
      expect(find.text('Nasiya hisobi'), findsOneWidget);
      await typePin(tester, '5926');
      await tester.pumpAndSettle();

      expect(bill['status'], 'paid');
      expect(
        p.backend.accounts[account]!['balance'],
        (total - const PosMoney.som(100000)).toApiString(),
      );
      expect(p.backend.bookings[booking]!['deposit_available'], '0.00');
      final String receipt = p.receipts.last;
      expect(receipt, contains('Avans hisobidan:'));
      expect(receipt, contains('Nasiyaga:'));
      expect(p.drawerKicks, 0);
    },
  );

  testWidgets(
    'no open shift: payment is refused with a way to the shift page',
    (WidgetTester tester) async {
      final P22 p = P22();
      final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h);
      await openBilling(tester, uuid);
      await tapButton(tester, 'Schyot tuzish');
      await tapText(tester, "To'lash: ", contains: true);
      await tapKey(tester, 'payment-submit');
      expect(find.textContaining('Smena ochilmagan'), findsOneWidget);
      await tapButton(tester, 'Smenani ochish');
      expect(find.byKey(const ValueKey<String>('shift-open')), findsOneWidget);
    },
  );

  testWidgets(
    'refund of a closed order from the queue: reason, manager approval, REFUND receipt, drawer',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h);
      final PosOrder order = await settle(tester, p.h.data.orders.get(uuid));
      final PosBill bill = (await settle(
        tester,
        p.h.data.billing.createBills(uuid, version: order.version),
      )).bills.single;
      await settle(
        tester,
        p.h.data.billing.pay(
          bill.uuid,
          tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        ),
      );

      await openSection(tester, 'Navbat');
      expect(find.text('Bugun yopilgan (1)'), findsOneWidget);
      await tester.tap(find.byKey(ValueKey<String>('finished-$uuid')));
      await tester.pumpAndSettle();
      expect(find.text('Buyurtma yopildi'), findsOneWidget);
      await tester.tap(find.byKey(ValueKey<String>('refund-${bill.uuid}')));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.widgetWithText(TextField, 'Qaytariladigan summa'),
        '20000',
      );
      await tester.tap(find.text('Taom sifatsiz'));
      await tester.pumpAndSettle();
      await tapButton(tester, 'Qaytarish');
      expect(find.text('Pul qaytarish'), findsOneWidget);
      await typePin(tester, '5926');
      await tester.pumpAndSettle();
      expect(p.backend.refunds.single['amount'], '20000.00');
      expect(p.backend.refunds.single['reason'], 'Taom sifatsiz');
      final String receipt = p.receipts.last;
      expect(receipt, contains('QAYTARISH CHEKI'));
      expect(receipt, contains('Sabab: Taom sifatsiz'));
      expect(p.drawerKicks, 1);
    },
  );
}
