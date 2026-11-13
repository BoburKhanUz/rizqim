import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';

import 'support/harness.dart';
import 'support/p22.dart';

/// Review P6 uskunalar/LAN (C2): ofitsiantning "Schyot so'rash"i kassa ekrani qulflangan paytda ham chiqadi; hodisa
/// yo'qolsa (kassa bir lahza internetsiz, qayta ulandi, qulfdan ochildi) server saqlagan so'rov
/// (`GET /pos/bill-print-requests`) chiqariladi — bir so'rov bitta qog'oz (`POST .../printed` bilan olinadi).
void main() {
  Future<(String, String)> billedOrder(WidgetTester tester, P22 p) async {
    final String uuid = p.state.seedOrder(tableId: 1, guests: 3);
    await pumpHome(tester, p.h);
    final PosOrder order = await settle(tester, p.h.data.orders.get(uuid));
    final PosBilling billing = await settle(
      tester,
      p.h.data.billing.createBills(uuid, version: order.version),
    );
    return (uuid, billing.bills.single.uuid);
  }

  Future<void> wait(WidgetTester tester) async {
    for (var i = 0; i < 20; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
  }

  List<Object?> claimed(P22 p) => <Object?>[
    for (final Map<String, Object?> row in p.backend.printInboxRows)
      row['printed'],
  ];

  testWidgets(
    'the till\'s screen locked itself (idle): the waiter\'s bill request still prints, once',
    (WidgetTester tester) async {
      final P22 p = P22();
      final (String order, String bill) = await billedOrder(tester, p);

      // Nobody touched the till for screen_lock_minutes: it locks (PIN screen, no staff session)
      await settle(tester, p.h.data.session.lock(reason: 'idle'));
      await tester.pump(const Duration(seconds: 1));

      // The waiter asks for the bill; auto print is on, this till is named
      p.backend.announcePrint(order, bill);
      await wait(tester);
      expect(p.receipts, hasLength(1));
      expect(p.receipts.single, contains('SCHYOT'));
      expect(claimed(p), <Object?>[FakePosState.deviceUuid]);

      // Unlocking catches up: nothing more (it was claimed)
      await tester.tap(find.text('Kamola Karimova'));
      await tester.pumpAndSettle();
      await typePin(tester, p.state.pins[3]!);
      await wait(tester);
      expect(p.receipts, hasLength(1));
    },
  );

  testWidgets(
    'a broadcast the till missed is printed when it reconnects, and only once',
    (WidgetTester tester) async {
      final P22 p = P22();
      final (String order, String bill) = await billedOrder(tester, p);

      // The till was off the internet for a moment: the event never reached it
      p.backend.announcePrint(order, bill, broadcast: false);
      await wait(tester);
      expect(p.receipts, isEmpty);

      // Back on: the subscription is confirmed again and the till reads what it missed
      p.h.hub.dropAll();
      await wait(tester);
      expect(p.receipts, hasLength(1));
      expect(claimed(p), <Object?>[FakePosState.deviceUuid]);

      p.h.hub.dropAll();
      await wait(tester);
      expect(p.receipts, hasLength(1));
    },
  );

  testWidgets('a request missed while locked prints when someone signs in', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    final (String order, String bill) = await billedOrder(tester, p);
    await settle(tester, p.h.data.session.lock(reason: 'idle'));
    p.backend.announcePrint(order, bill, broadcast: false);
    await wait(tester);
    expect(p.receipts, isEmpty);

    await tester.tap(find.text('Kamola Karimova'));
    await tester.pumpAndSettle();
    await typePin(tester, p.state.pins[3]!);
    await wait(tester);
    expect(p.receipts, hasLength(1));
  });

  testWidgets('a request another till took already is not printed here', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    final (String order, String bill) = await billedOrder(tester, p);
    p.backend.announcePrint(order, bill, broadcast: false);
    p.backend.printInboxRows.single['printed'] = 'another-till';

    p.state.emit(
      'bill.print_requested',
      <String, Object?>{...p.backend.printInboxRows.single}..remove('printed'),
    );
    await wait(tester);
    expect(p.receipts, isEmpty);
  });
}
