import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:pos_hardware/pos_hardware.dart';

import 'support/harness.dart';
import 'support/p22.dart';

/// P6 (A bosqichi): ofitsiant schyot so'raganda (yoki qayta chiqarishni so'raganda) nomlangan kassa pre-check'ni o'zi
/// chiqaradi: bir so'rov bir marta, fiskal emas, schyotning "chiqarilgan" hisobiga tegmaydi.
void main() {
  const String till = FakePosState.deviceUuid;

  Map<String, Object?> event(
    String order,
    String bill, {
    String request = 'r1',
    bool reprint = false,
    String? target = till,
    int? printer,
  }) => <String, Object?>{
    'order': <String, Object?>{
      'uuid': order,
      'status': 'preparing',
      'store_id': 1,
      'branch_id': 1,
      'courier_id': null,
      'updated_at': null,
    },
    'bill': <String, Object?>{'uuid': bill},
    'request_id': request,
    'reprint': reprint,
    'printer_id': printer,
    'target_device': target,
    'requested_by': 4,
  };

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

  Future<void> hear(
    WidgetTester tester,
    P22 p,
    Map<String, Object?> data,
  ) async {
    p.state.emit('bill.print_requested', data);
    await tester.pumpAndSettle();
  }

  testWidgets(
    'the named till prints the pre-check once, a reprint is a NUSXA, and nothing is marked printed',
    (WidgetTester tester) async {
      final P22 p = P22();
      final (String order, String bill) = await billedOrder(tester, p);

      await hear(tester, p, event(order, bill));
      expect(p.receipts, hasLength(1));
      expect(p.receipts.single, contains('SCHYOT'));
      expect(p.receipts.single, contains('Bu fiskal chek emas'));
      expect(p.receipts.single, isNot(contains('NUSXA')));

      // The same event again (reconnect, or the LAN and the cloud both): nothing more
      await hear(tester, p, event(order, bill));
      expect(p.receipts, hasLength(1));

      await hear(tester, p, event(order, bill, request: 'r2', reprint: true));
      expect(p.receipts, hasLength(2));
      expect(p.receipts.last, contains('NUSXA'));

      // A pre-check never hands the bill over: no print call, the bill is not counted as printed
      expect(p.h.adapter.to('POST', RegExp(r'/bills/[^/]+/print$')), isEmpty);
      expect(p.backend.bills[order]!.single['printed_count'], 0);
    },
  );

  testWidgets(
    'another till\'s request is left alone; no name: every till prints',
    (WidgetTester tester) async {
      final P22 p = P22();
      final (String order, String bill) = await billedOrder(tester, p);

      await hear(tester, p, event(order, bill, target: 'some-other-till'));
      expect(p.receipts, isEmpty);

      await hear(tester, p, event(order, bill, request: 'r2', target: null));
      expect(p.receipts, hasLength(1));
    },
  );

  testWidgets(
    'the named printer is used, an unknown one falls back to the till\'s receipt printer',
    (WidgetTester tester) async {
      final P22 p = P22();
      final (String order, String bill) = await billedOrder(tester, p);

      // Printer 1 is the receipt printer; 99 is not one this till knows
      await hear(tester, p, event(order, bill, printer: 1));
      await hear(tester, p, event(order, bill, request: 'r2', printer: 99));
      expect(p.receipts, hasLength(2));
    },
  );

  testWidgets('a voided or unknown bill is dropped quietly', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    final (String order, String bill) = await billedOrder(tester, p);
    p.backend.bills[order]!.single['status'] = 'void';

    await hear(tester, p, event(order, bill));
    await hear(tester, p, event('nope', 'nope', request: 'r2'));
    expect(p.receipts, isEmpty);
    expect(find.textContaining('Schyot chiqmadi'), findsNothing);
  });

  testWidgets('a printer that fails shows the print warning with a retry', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    final (String order, String bill) = await billedOrder(tester, p);
    p.h
        .printerFor('server-1')
        .failNext(
          1,
          const PrinterException(PrinterErrorKind.paperOut, "Qog'oz tugadi"),
        );

    await hear(tester, p, event(order, bill));
    expect(p.receipts, isEmpty);
    expect(find.text('Chek chop etilmadi (1)'), findsOneWidget);

    await tapButton(tester, 'Qayta urinish');
    expect(p.receipts, hasLength(1));
    expect(find.text('Chek chop etilmadi (1)'), findsNothing);
  });

  testWidgets(
    'without a receipt printer the cashier is told, and the request can come again',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.printers.clear();
      final (String order, String bill) = await billedOrder(tester, p);

      await hear(tester, p, event(order, bill));
      expect(find.textContaining('chek printeri sozlanmagan'), findsOneWidget);
      expect(p.receipts, isEmpty);
    },
  );

  testWidgets(
    'a lost connection to the bill tells the cashier to print it by hand',
    (WidgetTester tester) async {
      final P22 p = P22();
      final (String order, String bill) = await billedOrder(tester, p);
      p.h.adapter.offline = true;

      await hear(tester, p, event(order, bill));
      expect(find.textContaining("aloqa yo'q"), findsOneWidget);

      // The request is tried again when it comes again
      p.h.adapter.offline = false;
      await hear(tester, p, event(order, bill));
      expect(p.receipts, hasLength(1));
    },
  );
}
