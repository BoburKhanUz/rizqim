import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';
import 'package:rizqim_cashier/src/features/settings/cashier_prefs.dart';
import 'package:rizqim_cashier/src/shell/update_notice.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'support/counter.dart';
import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';

/// Review M1 (M5 S5): the update notice never comes over a payment, and "Yangilash" waits for the payment.
const Key _banner = ValueKey<String>('pos-update-banner');
const Key _notice = ValueKey<String>('update-required-notice');
const Key _continue = ValueKey<String>('update-notice-continue');

const PosAppRelease _required = PosAppRelease(
  state: PosReleaseState.updateRequired,
  current: '0.1.0+1',
  latest: '0.2.0+5',
  minimum: '0.2.0+5',
  url: 'https://pos.rizqim.uz/files/cashier/android/x.apk',
);

Finder _inBanner(String text) =>
    find.descendant(of: find.byKey(_banner), matching: find.text(text));

Future<void> _backToShell(WidgetTester tester) async {
  tester
      .state<NavigatorState>(find.byType(Navigator).first)
      .popUntil((Route<dynamic> r) => r.isFirst);
  await tester.pumpAndSettle();
}

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues(<String, Object>{});
    CashierPrefs.instance.reset();
  });

  testWidgets(
    'a required notice read while the PaymentPage is open waits until the cashier is back on the shell',
    (WidgetTester tester) async {
      PosAppRelease? answer;
      final List<Uri> opened = <Uri>[];
      final P22 p = P22();
      p.h.services
        ..releases = PosReleaseRepository(fetch: () async => answer)
        ..linkOpener = (Uri u) async {
          opened.add(u);
          return true;
        };
      p.state.openShift();
      final String uuid = p.state.seedOrder(tableId: 1, guests: 3);
      await pumpHome(tester, p.h);
      await openBilling(tester, uuid);
      await tapButton(tester, 'Schyot tuzish');
      await tapText(tester, "To'lash: ", contains: true);
      expect(find.byType(PaymentPage), findsOneWidget);
      // The payment page holds the "payment in progress" signal: "Yangilash" waits for it
      expect(p.h.services.payments.active, isTrue);
      expect(updateGateOf(p.h.services).reason, PosUpdateReasons.paying);

      answer = _required;
      await p.h.services.releases!.refresh();
      await tester.pumpAndSettle();
      expect(find.byKey(_notice), findsNothing);
      expect(find.byType(PaymentPage), findsOneWidget);

      // Back on the shell: the deferred notice comes once, and "Yangilash" is open again
      await _backToShell(tester);
      expect(find.byType(PaymentPage), findsNothing);
      expect(p.h.services.payments.active, isFalse);
      expect(find.byKey(_notice), findsOneWidget);
      await tester.tap(
        find.descendant(
          of: find.byKey(_notice),
          matching: find.text('Yangilash'),
        ),
      );
      await tester.pump();
      expect(opened, hasLength(1));
      await tester.tap(find.byKey(_continue));
      await tester.pumpAndSettle();
      expect(find.byKey(_notice), findsNothing);
    },
  );

  testWidgets(
    'counter mode: "Yangilash" is disabled with the payment reason while a sale waits for payment',
    (WidgetTester tester) async {
      final List<Uri> opened = <Uri>[];
      final SyncStore store = await SyncStore.open(MemorySyncStorage());
      final P22 p = P22(offlineStore: store);
      p.state.format = formatBlock();
      seedCounterMenu(p.state);
      p.state.openShift();
      p.h.services
        ..releases = PosReleaseRepository(fetch: () async => _required)
        ..linkOpener = (Uri u) async {
          opened.add(u);
          return true;
        };
      await p.h.services.releases!.refresh();
      await pumpHome(tester, p.h);
      await tester.tap(find.byKey(_continue));
      await tester.pumpAndSettle();
      expect(_inBanner(PosUpdateReasons.paying), findsNothing);

      await tester.tap(menuTile(oshId));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
      await tester.pumpAndSettle();
      expect(find.byType(PaymentPage), findsOneWidget);
      expect(p.h.services.payments.active, isTrue);
      await tester.pageBack();
      await tester.pumpAndSettle();

      // "To'lov kutilmoqda": the sale is mid-payment, the red banner stays and its button waits
      expect(find.text("To'lov kutilmoqda"), findsOneWidget);
      expect(p.h.services.payments.active, isTrue);
      expect(_inBanner(PosUpdateReasons.paying), findsOneWidget);
      await tester.tap(_inBanner('Yangilash'));
      await tester.pump();
      expect(opened, isEmpty);

      // Paid: the signal is released and "Yangilash" opens the link
      await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('payment-submit')));
      await tester.pumpAndSettle();
      expect(p.h.services.payments.active, isFalse);
      expect(_inBanner(PosUpdateReasons.paying), findsNothing);
      await tester.tap(_inBanner('Yangilash'));
      await tester.pump();
      expect(opened, hasLength(1));
    },
  );
}
