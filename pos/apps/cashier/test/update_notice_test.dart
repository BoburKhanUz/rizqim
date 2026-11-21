import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/features/settings/cashier_prefs.dart';
import 'package:rizqim_cashier/src/shell/update_notice.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'support/harness.dart';
import 'support/p22.dart';

const Key _banner = ValueKey<String>('pos-update-banner');
const Key _notice = ValueKey<String>('update-required-notice');
const Key _continue = ValueKey<String>('update-notice-continue');

PosAppRelease _release(String state) => PosAppRelease(
  state: PosReleaseState.of(state),
  current: '0.1.0+1',
  latest: '0.2.0+5',
  minimum: state == 'update_required' ? '0.2.0+5' : null,
  url: 'https://pos.rizqim.uz/files/cashier/android/rizqim-cashier-0.2.0.apk',
  notes: 'Yangi hisobotlar.',
);

/// A till whose server answers [state] (`null`: no notice); the opened links are collected in [opened].
P22 _till(String? state, List<Uri> opened) {
  final P22 p = P22();
  p.h.services
    ..releases = PosReleaseRepository(
      fetch: () async => state == null ? null : _release(state),
    )
    ..linkOpener = (Uri url) async {
      opened.add(url);
      return true;
    };
  return p;
}

Future<void> _read(P22 p) => p.h.services.releases!.refresh();

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues(<String, Object>{});
    CashierPrefs.instance.reset();
  });

  testWidgets('no notice from the server: no banner, no settings line', (
    WidgetTester tester,
  ) async {
    final P22 p = _till(null, <Uri>[]);
    await _read(p);
    await pumpHome(tester, p.h);
    expect(find.byKey(_banner), findsNothing);
    expect(find.byKey(_notice), findsNothing);
    await openSection(tester, 'Sozlamalar');
    expect(
      find.byKey(const ValueKey<String>('update-settings-line')),
      findsNothing,
    );
  });

  testWidgets(
    'update_available: banner on the hall map, dismissed stays hidden the same day, back the next day; '
    'a quiet line stays in settings; Yangilash opens the link',
    (WidgetTester tester) async {
      final List<Uri> opened = <Uri>[];
      final P22 p = _till('update_available', opened);
      await _read(p);
      await pumpHome(tester, p.h);
      expect(find.byKey(_banner), findsOneWidget);
      expect(find.text('Yangi versiya mavjud'), findsOneWidget);
      expect(find.textContaining('0.2.0+5 versiyasi chiqdi'), findsOneWidget);

      await tapButton(tester, 'Keyinroq');
      expect(find.byKey(_banner), findsNothing);
      expect(CashierPrefs.instance.updateDismissedOn, '2026-10-22');
      final SharedPreferences prefs = await SharedPreferences.getInstance();
      expect(prefs.getString('cashier.update_dismissed_on'), '2026-10-22');

      // The same day, the app opened again: still hidden
      await tester.pumpWidget(const SizedBox.shrink());
      await pumpCashier(tester, p.h);
      expect(find.byKey(_banner), findsNothing);

      // Not on the other screens, but the quiet line is in settings
      await openSection(tester, 'Sozlamalar');
      expect(
        find.byKey(const ValueKey<String>('update-settings-line')),
        findsOneWidget,
      );
      await tester.tap(find.widgetWithText(TextButton, 'Yangilash'));
      await tester.pump();
      expect(opened.single.toString(), startsWith('https://pos.rizqim.uz/'));

      // Dismissed yesterday: the banner comes back on the hall map today
      await CashierPrefs.instance.setUpdateDismissedOn('2026-10-21');
      await tester.pumpWidget(const SizedBox.shrink());
      await pumpCashier(tester, p.h);
      expect(find.byKey(_banner), findsOneWidget);
    },
  );

  testWidgets('update_available: never on a screen other than the hall map', (
    WidgetTester tester,
  ) async {
    final P22 p = _till('update_available', <Uri>[]);
    await _read(p);
    await pumpHome(tester, p.h);
    expect(find.byKey(_banner), findsOneWidget);
    await openSection(tester, 'Navbat');
    expect(find.byKey(_banner), findsNothing);
  });

  testWidgets(
    'update_required: start notice closes with "Davom etish" and the till works; the red banner stays and cannot be dismissed',
    (WidgetTester tester) async {
      final List<Uri> opened = <Uri>[];
      final P22 p = _till('update_required', opened);
      await _read(p);
      await pumpHome(tester, p.h);

      expect(find.byKey(_notice), findsOneWidget);
      expect(find.text('Ilovani yangilash kerak'), findsWidgets);
      await tester.tap(find.byKey(_continue));
      await tester.pumpAndSettle();
      expect(find.byKey(_notice), findsNothing);

      // The till works: the hall map and other sections open; the banner is permanent, with no "Keyinroq"
      expect(find.byKey(_banner), findsOneWidget);
      expect(find.text('Keyinroq'), findsNothing);
      await openSection(tester, 'Navbat');
      expect(find.byKey(_banner), findsOneWidget);
      await openSection(tester, 'Zal');
      expect(find.byKey(_notice), findsNothing);

      await tapButton(tester, 'Yangilash');
      expect(opened, hasLength(1));
    },
  );

  testWidgets('update_required: the shift-open screen and a shift close', (
    WidgetTester tester,
  ) async {
    final P22 p = _till('update_required', <Uri>[]);
    await _read(p);
    p.state.openShift();
    await pumpHome(tester, p.h);
    await tester.tap(find.byKey(_continue));
    await tester.pumpAndSettle();

    // An open shift: no notice on the shift screen. Close it: the notice comes after the close
    await openSection(tester, 'Smena');
    expect(find.byKey(_notice), findsNothing);
    await tapKey(tester, 'z-open');
    await enter(tester, 'denom-100000', '1');
    await tapKey(tester, 'z-close');
    expect(p.state.shift, isNull);
    expect(find.byKey(_notice), findsOneWidget);
    await tester.tap(find.byKey(_continue));
    await tester.pumpAndSettle();
    expect(find.byKey(_notice), findsNothing);
    expect(find.byKey(const ValueKey<String>('shift-open')), findsOneWidget);

    // Another section and back: the notice came once for this visit, the screen is not blocked
    await openSection(tester, 'Zal');
    await openSection(tester, 'Smena');
    expect(find.byKey(_notice), findsNothing); // the same visit: once
    expect(find.byKey(const ValueKey<String>('shift-open')), findsOneWidget);
    // The shift opens (no gate) while the red banner stays
    await enter(tester, 'opening-cash', '100000');
    await tapKey(tester, 'shift-open');
    expect(p.state.shift, isNotNull);
    expect(find.byKey(_banner), findsOneWidget);
  });

  testWidgets('update_required: the shift-open screen shows the notice', (
    WidgetTester tester,
  ) async {
    final P22 p = _till('update_required', <Uri>[]);
    await _read(p);
    await pumpHome(tester, p.h);
    await tester.tap(find.byKey(_continue));
    await tester.pumpAndSettle();
    await openSection(tester, 'Smena');
    expect(find.byKey(_notice), findsOneWidget);
    await tester.tap(find.byKey(_continue));
    await tester.pumpAndSettle();
    expect(find.byKey(_notice), findsNothing);
    expect(find.byKey(const ValueKey<String>('shift-open')), findsOneWidget);
  });

  test('the update gate is open with an empty queue', () {
    final P22 p = P22();
    expect(updateGateOf(p.h.services).allowed, isTrue);
  });

  testWidgets('pairing 426: the message and a download link', (
    WidgetTester tester,
  ) async {
    final List<Uri> opened = <Uri>[];
    final CashierHarness h = CashierHarness();
    h.services.linkOpener = (Uri url) async {
      opened.add(url);
      return true;
    };
    h.adapter.respond = (RequestOptions o) {
      if (o.method == 'POST' && o.uri.path.endsWith('/v1/devices/pair')) {
        return const FakeResponse(426, <String, Object?>{
          'success': false,
          'message': 'Ilova eskirgan: yangi versiyani yuklab oling.',
          'data': <String, Object?>{
            'reason': 'app_outdated',
            'current': '0.1.0+1',
            'minimum': '0.2.0+5',
            'url': 'https://pos.rizqim.uz/files/cashier/android/new.apk',
          },
        });
      }
      return null;
    };
    await pumpCashier(tester, h);
    await tester.enterText(find.byType(TextField), 'ABCD-EFGH');
    await tester.pump();
    await tester.tap(buttonLabeled('Ulash'));
    await tester.pumpAndSettle();

    expect(
      find.text('Ilova eskirgan: yangi versiyani yuklab oling.'),
      findsOne,
    );
    await tester.tap(buttonLabeled('Yuklab olish'));
    await tester.pump();
    expect(opened.single.toString(), endsWith('/cashier/android/new.apk'));
    expect(find.text('Kassani ulash'), findsOneWidget);
  });
}
