import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_waiter/src/common/errors.dart';
import 'package:rizqim_waiter/src/features/order/order_page.dart';
import 'package:rizqim_waiter/src/shell/approval_dialog.dart';

import 'support/harness.dart';

/// P6 (A bosqichi): ofitsiant "Schyot so'rash"ni bosganda schyot kassada o'zi chiqadi (filial sozlamasi) va
/// "Schyotni qayta chiqarish" menejer tasdig'i bilan nusxani yana chiqartiradi.
void main() {
  Future<(String, FakeCashierBackend)> openOwnOrder(
    WidgetTester tester,
    WaiterHarness h,
  ) async {
    final FakeCashierBackend backend = FakeCashierBackend(h.state);
    final String uuid = seedWaiterOrder(h, tableId: 1);
    await pumpHome(tester, h);
    await tester.tap(find.text('1-xona'));
    await tester.pumpAndSettle();
    expect(find.byType(OrderPage), findsOneWidget);
    return (uuid, backend);
  }

  Future<void> askForBill(WidgetTester tester) async {
    await tester.tap(buttonLabeled("Schyot so'rash"));
    await tester.pumpAndSettle();
    await tapButton(tester, "So'rash");
  }

  /// The server makes the bill when the waiter asks (the fake does not): the cashier's way, as the waiter.
  Future<String> makeBill(
    WidgetTester tester,
    WaiterHarness h,
    String uuid,
  ) async {
    final PosOrder order = await settle(
      tester,
      h.services.data.orders.get(uuid),
    );
    final PosBilling billing = await settle(
      tester,
      h.services.data.billing.createBills(uuid, version: order.version),
    );
    return billing.bills.single.uuid;
  }

  testWidgets(
    'the branch that prints by itself says so; one that does not keeps the old words',
    (WidgetTester tester) async {
      final WaiterHarness h = WaiterHarness()..state.autoPrintBill = false;
      await openOwnOrder(tester, h);

      await tester.tap(buttonLabeled("Schyot so'rash"));
      await tester.pumpAndSettle();
      expect(find.textContaining("kassada o'zi chiqadi"), findsNothing);
      await tapButton(tester, "So'rash");
      expect(
        find.textContaining("Schyot so'raldi: kassir uni chiqaradi"),
        findsOneWidget,
      );
    },
  );

  testWidgets('no reprint before the bill is asked for', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    await openOwnOrder(tester, h);
    expect(buttonLabeled('Schyotni qayta chiqarish'), findsNothing);
  });

  testWidgets(
    'a reprint asks for a manager\'s PIN and sends the approval with the request',
    (WidgetTester tester) async {
      final WaiterHarness h = WaiterHarness();
      final (String uuid, FakeCashierBackend backend) = await openOwnOrder(
        tester,
        h,
      );
      await askForBill(tester);
      final String bill = await makeBill(tester, h, uuid);
      backend
        ..printRequestPrinterId = 1
        ..printRequestTargetDevice = FakePosState.deviceUuid;

      await tapButton(tester, 'Schyotni qayta chiqarish');
      expect(find.textContaining('nusxasi yana chiqadi'), findsOneWidget);
      await tapButton(tester, 'Chiqarish');
      // The waiter has no pos.reprint_bill: the manager approves with a PIN
      expect(find.byType(ManagerApprovalDialog), findsOneWidget);
      expect(find.text('Schyotni qayta chiqarish'), findsWidgets);
      expect(backend.printRequests, isEmpty);
      await typePin(tester, '5926');

      expect(backend.printRequests, hasLength(1));
      expect(backend.printRequests.single['bill'], bill);
      final List<Map<String, Object?>> sent = bodies(
        h,
        'POST',
        '/v1/pos/bills/$bill/print-request',
      );
      expect(sent.last['approval_token'], isNotNull);
      expect(
        find.textContaining('qayta chiqarishga yuborildi'),
        findsOneWidget,
      );
      // Never a print of the bill itself or a payment from the waiter's phone
      expect(h.adapter.to('POST', RegExp(r'/print$')), isEmpty);
      expect(h.adapter.to('POST', '/payments'), isEmpty);
    },
  );

  testWidgets('cancelling the manager dialog sends nothing', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    final (String uuid, FakeCashierBackend backend) = await openOwnOrder(
      tester,
      h,
    );
    await askForBill(tester);
    await makeBill(tester, h, uuid);

    await tapButton(tester, 'Schyotni qayta chiqarish');
    await tapButton(tester, 'Chiqarish');
    expect(find.byType(ManagerApprovalDialog), findsOneWidget);
    await tapButton(tester, 'Bekor qilish');

    expect(find.byType(ManagerApprovalDialog), findsNothing);
    expect(backend.printRequests, isEmpty);
  });

  testWidgets('a bill that is not made yet is explained, not sent', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    final (_, FakeCashierBackend backend) = await openOwnOrder(tester, h);
    await askForBill(tester);

    await tapButton(tester, 'Schyotni qayta chiqarish');
    await tapButton(tester, 'Chiqarish');
    expect(find.textContaining('Schyot hali tuzilmagan'), findsOneWidget);
    expect(find.byType(ManagerApprovalDialog), findsNothing);
    expect(backend.printRequests, isEmpty);
  });

  test(
    'a reprint of an outgrown split bill says the cashier makes it again',
    () {
      // P6 review C4: 409 bill_stale (the server never prints the old total)
      expect(
        describeError(
          const PosConflict(
            message: 'x',
            statusCode: 409,
            reason: 'bill_stale',
          ),
        ),
        contains('yangi schyotni kassir tuzadi'),
      );
    },
  );
}
