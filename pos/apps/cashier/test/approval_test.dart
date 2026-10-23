import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/features/floor/floor_page.dart';
import 'package:rizqim_cashier/src/shell/approval_dialog.dart';

import 'support/harness.dart';

void main() {
  testWidgets(
    'the manager types the PIN → the dialog returns a token from the server',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      await pumpHome(tester, h);
      final BuildContext context = tester.element(find.byType(FloorPage));

      ApprovalToken? token;
      var done = false;
      showManagerApproval(
        context,
        permission: PosPermissions.ordersVoidItem,
      ).then((ApprovalToken? t) {
        token = t;
        done = true;
      }).ignore();
      await tester.pumpAndSettle();

      expect(find.text("Menejer tasdig'i"), findsOneWidget);
      expect(find.text("Yuborilgan taomni o'chirish"), findsOneWidget);
      // Bitta menejer: oldindan tanlangan
      expect(find.text('Malika Rahimova · Menejer'), findsOneWidget);
      expect(h.state.log, contains('GET /api/v1/terminal/staff'));

      await typePin(tester, '0000');
      expect(find.text("PIN noto'g'ri. Qayta urinib ko'ring."), findsOneWidget);
      expect(done, isFalse);

      await typePin(tester, '5926');
      expect(done, isTrue);
      expect(token, isNotNull);
      expect(token!.permission, PosPermissions.ordersVoidItem);
      expect(token!.approvedBy.name, 'Malika Rahimova');
      expect(find.text("Menejer tasdig'i"), findsNothing);
    },
  );

  testWidgets('cancel returns null', (WidgetTester tester) async {
    final CashierHarness h = CashierHarness();
    await pumpHome(tester, h);
    final BuildContext context = tester.element(find.byType(FloorPage));
    ApprovalToken? token = ApprovalToken(
      token: 'x',
      permission: 'x',
      expiresAt: DateTime.utc(1970),
      approvedBy: const PosRef(id: 0),
    );
    showManagerApproval(
      context,
      permission: PosPermissions.ordersCancel,
    ).then((ApprovalToken? t) => token = t).ignore();
    await tester.pumpAndSettle();
    await tester.tap(buttonLabeled('Bekor qilish'));
    await tester.pumpAndSettle();
    expect(token, isNull);
  });

  testWidgets(
    'withApproval: a sent line is voided after the manager approves',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      await pumpHome(tester, h);
      final PosOrder order = await settle(
        tester,
        h.data.orders.open(
          channel: PosChannel.hall,
          tableId: 2,
          guestsCount: 2,
          items: <PosLineInput>[
            PosLineInput(
              variantUuid: FakePosState.variantUuid(4),
              quantity: const PosQuantity.whole(2),
            ),
          ],
          send: true,
        ),
      );
      final BuildContext context = tester.element(find.byType(FloorPage));
      PosOrder? result;
      withApproval<PosOrder>(
        context,
        (String? token) => h.data.orders.voidItem(
          order.uuid,
          order.items.single.id,
          version: order.version,
          reason: 'xato',
          approvalToken: token,
        ),
      ).then((PosOrder o) => result = o).ignore();
      await tester.pumpAndSettle();
      expect(find.text("Menejer tasdig'i"), findsOneWidget);
      await typePin(tester, '5926');
      await tester.pumpAndSettle();
      expect(result, isNotNull);
      expect(result!.items.single.isVoid, isTrue);
    },
  );

  testWidgets('a cashier cannot approve: only managers and owners are listed', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    h.state.staff.add(<String, Object?>{
      'id': 9,
      'name': 'Olim Egamov',
      'role': 'owner',
      'staff_code': '1009',
    });
    h.state.pins[9] = '7777';
    h.state.rolePermissions['owner'] = h.state.rolePermissions['manager']!;
    await pumpHome(tester, h);
    final BuildContext context = tester.element(find.byType(FloorPage));
    showManagerApproval(context, permission: PosPermissions.refund).ignore();
    await tester.pumpAndSettle();
    expect(find.widgetWithText(ChoiceChip, 'Malika Rahimova'), findsOneWidget);
    expect(find.widgetWithText(ChoiceChip, 'Olim Egamov'), findsOneWidget);
    expect(find.widgetWithText(ChoiceChip, 'Kamola Karimova'), findsNothing);
    expect(find.text('Kim tasdiqlaydi? Ismni tanlang.'), findsOneWidget);
    await tester.tap(find.widgetWithText(ChoiceChip, 'Olim Egamov'));
    await tester.pumpAndSettle();
    expect(find.bySemanticsLabel('Kirish'), findsWidgets);
  });
}
