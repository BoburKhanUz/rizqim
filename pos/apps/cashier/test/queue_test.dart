import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/features/order/order_page.dart';

import 'support/harness.dart';
import 'support/p22.dart';

void main() {
  testWidgets(
    'online orders: late warning, a sound for a new one, accept moves it to the open list; a cashier cannot reject',
    (WidgetTester tester) async {
      final P22 p = P22();
      final List<MethodCall> sounds = recordSystemSounds(tester);
      final String late = p.backend.seedOnlineOrder(
        minutesAgo: 6,
        customer: 'Aziz',
        emit: false,
      );
      final String hallOrder = p.state.seedOrder(tableId: 1, guests: 5);
      await pumpHome(tester, p.h);
      await openSection(tester, 'Navbat');

      expect(find.text('Yangi onlayn buyurtmalar (1)'), findsOneWidget);
      expect(find.textContaining('6 daqiqa qabul qilinmadi'), findsOneWidget);
      expect(find.textContaining('Aziz'), findsOneWidget);
      expect(find.text('1 kg (guruch) × Osh (Choyxona oshi)'), findsOneWidget);
      expect(find.text('Ochiq buyurtmalar (1)'), findsOneWidget);
      expect(find.text('1-xona'), findsWidgets);
      expect(sounds, isEmpty);

      // Yangi buyurtma real-time bilan keladi: ovoz chiqadi
      p.backend.seedOnlineOrder(
        channel: 'mobile',
        type: 'delivery',
        minutesAgo: 0,
        customer: 'Nodira',
      );
      await tester.pump(const Duration(seconds: 1));
      await tester.pumpAndSettle();
      expect(find.text('Yangi onlayn buyurtmalar (2)'), findsOneWidget);
      expect(find.text('Hozir keldi'), findsOneWidget);
      expect(sounds, hasLength(1));

      // Kassirda orders.cancel yo'q: rad etish o'chirilgan
      await tapKey(tester, 'reject-$late');
      expect(p.backend.onlineOrders.first['status'], 'new');

      await tapKey(tester, 'accept-$late');
      expect(p.backend.onlineOrders.first['status'], 'accepted');
      expect(find.text('Yangi onlayn buyurtmalar (1)'), findsOneWidget);
      expect(find.text('Ochiq buyurtmalar (2)'), findsOneWidget);
      expect(find.text('Telegram'), findsWidgets);

      // Zal buyurtmasini bosish — buyurtma muharriri
      await tapKey(tester, 'open-$hallOrder');
      expect(find.byType(OrderPage), findsOneWidget);
    },
  );

  testWidgets('a manager rejects with a reason', (WidgetTester tester) async {
    final P22 p = P22();
    final String uuid = p.backend.seedOnlineOrder(minutesAgo: 1, emit: false);
    await pumpHome(tester, p.h, name: 'Malika Rahimova');
    await openSection(tester, 'Navbat');
    await tapKey(tester, 'reject-$uuid');
    await tester.tap(find.text(PosRejectReason.outOfStock.label));
    await tester.pumpAndSettle();
    expect(p.backend.onlineOrders.single['status'], 'rejected');
    expect(p.backend.onlineOrders.single['cancel_reason'], 'out_of_stock');
    expect(find.text("Navbat bo'sh"), findsOneWidget);
  });

  testWidgets('fiscal pending list: retry issues the receipt and prints it', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    p.state.openShift();
    p.backend.fiscalMode = FakeFiscalMode.pending;
    final String uuid = p.state.seedOrder(tableId: 2, guests: 2);
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
    expect(find.text('Fiskal kutmoqda (1)'), findsOneWidget);
    expect(find.text('Fiskal kutmoqda'), findsOneWidget);
    await tapKey(tester, 'fiscal-retry-$uuid');
    expect(find.text('Chek chiqdi va chop etildi.'), findsOneWidget);
    expect(p.receipts.last, contains('SOTUV CHEKI'));
    expect(p.state.orders[uuid]!['status'], 'delivered');
    expect(find.textContaining('Fiskal kutmoqda ('), findsNothing);
    expect(find.text('Bugun yopilgan (1)'), findsOneWidget);
  });
}
