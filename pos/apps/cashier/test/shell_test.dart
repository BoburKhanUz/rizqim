import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart' show PosMoney;
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_cashier/src/features/billing/billing_page.dart';
import 'package:rizqim_cashier/src/features/floor/floor_page.dart';
import 'package:rizqim_cashier/src/shell/routes.dart';
import 'package:rizqim_cashier/src/shell/staff_sign_in_screen.dart';

import 'support/harness.dart';

void main() {
  testWidgets('pairing → PIN → home with the six sections', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    await pumpCashier(tester, h);

    expect(find.text('Kassani ulash'), findsOneWidget);
    await tester.enterText(find.byType(TextField), 'abcdefgh');
    await tester.pump();
    expect(
      tester.widget<TextField>(find.byType(TextField)).controller!.text,
      'ABCD-EFGH',
    );
    await tester.tap(buttonLabeled('Ulash'));
    await tester.pumpAndSettle();

    // PIN ekrani: kassir birinchi, menejer keyin; ofitsiant oxirida
    expect(find.byType(StaffSignInScreen), findsOneWidget);
    expect(find.text('Kassa'), findsOneWidget);
    final double cashierY = tester.getTopLeft(find.text('Kamola Karimova')).dy;
    final double waiterY = tester.getTopLeft(find.text('Botir Aliyev')).dy;
    expect(cashierY, lessThanOrEqualTo(waiterY));
    expect(h.state.log, contains('POST /api/v1/devices/pair'));

    await tester.tap(find.text('Kamola Karimova'));
    await tester.pumpAndSettle();
    await typePin(tester, '4827');

    // Ish ekrani: yuqori panel va bo'limlar
    expect(find.text('Rohat choyxonasi'), findsOneWidget);
    expect(find.text('Chilonzor'), findsOneWidget);
    expect(find.text('Smena yopiq'), findsOneWidget);
    expect(find.text('Kamola Karimova'), findsOneWidget);
    for (final String label in <String>[
      'Zal',
      'Navbat',
      'Bronlar',
      'Nasiya',
      'Smena',
      'Sozlamalar',
    ]) {
      expect(find.text(label), findsOneWidget, reason: label);
    }
    expect(find.byType(NavigationRail), findsOneWidget);
    expect(find.byType(FloorPage), findsOneWidget);
    // Zal xaritasi: soxta backend'dagi xonalar
    expect(find.text('1-xona'), findsOneWidget);
    expect(h.state.log, contains('GET /api/v1/pos/bootstrap'));
    // Menyu fonda keshga olinadi
    expect(h.data.catalog.cached(PosChannel.hall), isNotNull);
    // Printerlar bootstrap'dan
    expect(h.services.printing.receiptPrinter!.name, 'Kassa printeri');
    // Real-time jonli
    expect(find.text('Jonli'), findsOneWidget);

    await tester.tap(find.text('Navbat'));
    await tester.pumpAndSettle();
    expect(
      find.text(
        "Barcha ochiq buyurtmalar (zal, olib ketish, onlayn) shu yerda bo'ladi.",
      ),
      findsOneWidget,
    );
    await tester.tap(find.text('Smena'));
    await tester.pumpAndSettle();
    expect(find.textContaining('X va Z hisobot'), findsOneWidget);
  });

  testWidgets('a wrong PIN says so and keeps the PIN screen', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    await pumpPaired(tester, h);
    await tester.tap(find.text('Kamola Karimova'));
    await tester.pumpAndSettle();
    await typePin(tester, '1111');
    expect(find.text("PIN noto'g'ri. Qayta urinib ko'ring."), findsOneWidget);
    expect(find.byType(StaffSignInScreen), findsOneWidget);
  });

  testWidgets(
    'sections follow permissions: a waiter sees no bookings, nasiya or shift',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      await pumpHome(tester, h, name: 'Botir Aliyev');
      expect(find.text('Zal'), findsOneWidget);
      expect(find.text('Navbat'), findsOneWidget);
      expect(find.text('Bronlar'), findsNothing);
      expect(find.text('Nasiya'), findsNothing);
      expect(find.text('Smena'), findsNothing);
      expect(find.text('Sozlamalar'), findsOneWidget);
    },
  );

  testWidgets(
    'shift chip: open shift from bootstrap, updated on shift.changed',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      await pumpHome(tester, h);
      expect(find.text('Smena yopiq'), findsOneWidget);

      // Boshqa ekran (yoki P3-22 smena sahifasi) smena ochdi: shift.changed → GET /pos/shifts/current
      await settle(tester, h.data.shifts.open(openingCash: PosMoney.zero));
      await tester.pumpAndSettle();
      expect(find.textContaining('Smena ochiq'), findsOneWidget);
      expect(
        h.state.log.where((String l) => l == 'GET /api/v1/pos/shifts/current'),
        isNotEmpty,
      );

      await tester.tap(find.textContaining('Smena ochiq'));
      await tester.pumpAndSettle();
      expect(find.textContaining('X va Z hisobot'), findsOneWidget);
    },
  );

  testWidgets(
    'idle lock: back to the PIN screen after the branch screen-lock time, pages closed',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      h.state.screenLockMinutes = 1;
      await pumpHome(tester, h);
      final BuildContext context = tester.element(find.byType(FloorPage));
      CashierRoutesTest.openOrder(context);
      await tester.pumpAndSettle();
      expect(find.text('1-xona: yangi buyurtma'), findsOneWidget);

      await tester.pump(const Duration(seconds: 59));
      expect(find.byType(StaffSignInScreen), findsNothing);
      await tester.pump(const Duration(seconds: 2));
      await tester.pumpAndSettle();
      expect(find.byType(StaffSignInScreen), findsOneWidget);
      expect(find.text('1-xona: yangi buyurtma'), findsNothing);
      expect(
        find.textContaining('Ekranga uzoq tegilmagani uchun'),
        findsOneWidget,
      );
      expect(h.client.auth.staff, isNull);
      expect(h.client.auth.terminal, isNotNull);
    },
  );

  testWidgets('screen_lock_minutes 0: never locks', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    h.state.screenLockMinutes = 0;
    await pumpHome(tester, h);
    await tester.pump(const Duration(hours: 1));
    expect(find.byType(FloorPage), findsOneWidget);
  });

  testWidgets('lock button and staff menu', (WidgetTester tester) async {
    final CashierHarness h = CashierHarness();
    await pumpHome(tester, h);
    // Kassirda "Terminalni uzish" yo'q
    await tester.tap(find.byTooltip('Xodim menyusi'));
    await tester.pumpAndSettle();
    expect(find.text('Ekranni qulflash'), findsWidgets);
    expect(find.text('Terminalni uzish'), findsNothing);
    await tester.tap(find.text("Mavzu: qorong'i"));
    await tester.pumpAndSettle();
    expect(h.settings.value, ThemeMode.dark);
    expect(
      Theme.of(tester.element(find.byType(FloorPage))).brightness,
      Brightness.dark,
    );

    await tester.tap(find.byTooltip('Ekranni qulflash'));
    await tester.pumpAndSettle();
    expect(find.byType(StaffSignInScreen), findsOneWidget);
  });

  testWidgets('a manager unpairs the till from the staff menu', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    await pumpHome(tester, h, name: 'Malika Rahimova');
    await tester.tap(find.byTooltip('Xodim menyusi'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Terminalni uzish'));
    await tester.pumpAndSettle();
    await tester.tap(buttonLabeled('Uzish'));
    await tester.pumpAndSettle();
    expect(find.text('Kassani ulash'), findsOneWidget);
    expect(h.state.log, contains('DELETE /api/v1/terminal'));
    expect(h.client.auth.terminal, isNull);
  });

  testWidgets('narrow screen: bottom navigation bar', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    await pumpHome(tester, h, size: const Size(600, 900));
    expect(find.byType(NavigationBar), findsOneWidget);
    expect(find.byType(NavigationRail), findsNothing);
  });

  testWidgets('offline: the bar shows "Aloqa yo\'q"', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness(realtime: false);
    await pumpHome(tester, h);
    expect(find.text('Har 15 soniyada'), findsOneWidget);
    h.adapter.offline = true;
    Object? error;
    await settle(
      tester,
      h.data.floor.load().then<void>((_) {}, onError: (Object e) => error = e),
    );
    expect(error, isA<PosNetworkException>());
    await tester.pumpAndSettle();
    expect(find.text("Aloqa yo'q"), findsOneWidget);
  });

  testWidgets('pages pushed over the shell keep the scope', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    await pumpHome(tester, h);
    final BuildContext context = tester.element(find.byType(FloorPage));
    CashierRoutesTest.openBilling(context);
    await tester.pumpAndSettle();
    expect(find.byType(BillingPage), findsOneWidget);
    await tester.pageBack();
    await tester.pumpAndSettle();
    expect(find.byType(FloorPage), findsOneWidget);
  });
}

/// Testlarda natijani kutmasdan ochish.
abstract final class CashierRoutesTest {
  static void openOrder(BuildContext context) =>
      CashierRoutes.openOrder<void>(context, tableId: 1).ignore();

  static void openBilling(BuildContext context) =>
      CashierRoutes.openBilling<void>(context, orderUuid: 'o1').ignore();
}
