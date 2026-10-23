import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_waiter/src/features/rooms/rooms_page.dart';
import 'package:rizqim_waiter/src/shell/approval_dialog.dart';
import 'package:rizqim_waiter/src/shell/staff_sign_in_screen.dart';

import 'support/harness.dart';

void main() {
  testWidgets('pairing → staff list → PIN → "Mening xonalarim" on a phone', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    await pumpWaiter(tester, h);
    expect(find.text('Telefonni ulash'), findsOneWidget);
    await tester.enterText(find.byType(TextField), 'ABCD-EFGH');
    await tester.pump();
    await tester.tap(buttonLabeled('Ulash'));
    await tester.pumpAndSettle();

    // Telefonda avval ro'yxat, ism bosilsa PIN; ofitsiant birinchi
    expect(find.byType(StaffSignInScreen), findsOneWidget);
    expect(find.text('Ofitsiant'), findsWidgets);
    expect(
      tester.getTopLeft(find.text('Botir Aliyev')).dy,
      lessThan(tester.getTopLeft(find.text('Kamola Karimova')).dy),
    );
    await tester.tap(find.text('Botir Aliyev'));
    await tester.pumpAndSettle();
    expect(find.text('Boshqa xodim'), findsOneWidget);
    await typePin(tester, '3691');

    expect(find.byType(RoomsPage), findsOneWidget);
    expect(find.text('Mening xonalarim'), findsOneWidget);
    expect(find.text('Botir Aliyev'), findsOneWidget);
    expect(find.text('Ofitsiant · Chilonzor'), findsOneWidget);
    expect(find.byTooltip('Ekranni qulflash'), findsOneWidget);
    // Ofitsiantda terminalni uzish yo'q
    expect(find.byTooltip('Menyu'), findsNothing);
    expect(h.state.log, contains('GET /api/v1/pos/bootstrap'));
  });

  testWidgets('idle lock returns to the PIN screen', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    h.state.screenLockMinutes = 2;
    await pumpHome(tester, h);
    await tester.pump(const Duration(minutes: 1));
    expect(find.byType(RoomsPage), findsOneWidget);
    await tester.pump(const Duration(minutes: 1, seconds: 1));
    await tester.pumpAndSettle();
    expect(find.byType(StaffSignInScreen), findsOneWidget);
    expect(
      find.textContaining('Ekranga uzoq tegilmagani uchun'),
      findsOneWidget,
    );
  });

  testWidgets(
    'the approval dialog returns a token (waiter rule: someone else\'s order)',
    (WidgetTester tester) async {
      final WaiterHarness h = WaiterHarness();
      await pumpHome(tester, h);
      final BuildContext context = tester.element(find.byType(RoomsPage));
      ApprovalToken? token;
      showManagerApproval(
        context,
        permission: PosPermissions.ordersManage,
      ).then((ApprovalToken? t) => token = t).ignore();
      await tester.pumpAndSettle();
      expect(find.text("Buyurtmani o'zgartirish"), findsOneWidget);
      await typePin(tester, '5926');
      expect(token!.permission, PosPermissions.ordersManage);
    },
  );

  testWidgets('a manager can unpair the phone', (WidgetTester tester) async {
    final WaiterHarness h = WaiterHarness();
    await pumpHome(tester, h, name: 'Malika Rahimova');
    await tester.tap(find.byTooltip('Menyu'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Terminalni uzish'));
    await tester.pumpAndSettle();
    await tester.tap(buttonLabeled('Uzish'));
    await tester.pumpAndSettle();
    expect(find.text('Telefonni ulash'), findsOneWidget);
  });

  testWidgets('lock button', (WidgetTester tester) async {
    final WaiterHarness h = WaiterHarness();
    await pumpHome(tester, h);
    await tester.tap(find.byTooltip('Ekranni qulflash'));
    await tester.pumpAndSettle();
    expect(find.byType(StaffSignInScreen), findsOneWidget);
    expect(find.text('Botir Aliyev'), findsOneWidget);
  });
}
