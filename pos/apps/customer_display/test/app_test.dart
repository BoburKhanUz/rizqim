import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_customer_display/src/settings/display_settings.dart';

import 'support/harness.dart';

/// Ulash, o'chirilgan terminal, modul yoqilmagan ekranlar va xodimsiz sozlamalar oynasi.
void main() {
  Future<void> openSettings(WidgetTester tester) async {
    final Offset corner = tester.getCenter(find.byKey(const ValueKey<String>('settings-corner')));
    final TestGesture g = await tester.startGesture(corner);
    await tester.pump(const Duration(milliseconds: 900));
    expect(find.text('Ekran sozlamalari'), findsNothing, reason: 'a short touch must not open the settings');
    await tester.pump(const Duration(milliseconds: 1300));
    await g.up();
    await tester.pumpAndSettle();
  }

  testWidgets('an unpaired display asks for the code and pairs as "Mijoz ekrani"', (WidgetTester tester) async {
    final DisplayHarness h = DisplayHarness();
    h.backend.queueCalling = false;
    await h.pump(tester, paired: false);

    expect(find.text('Mijoz ekranini ulash'), findsOneWidget);
    expect(find.textContaining('bepul'), findsOneWidget);
    await tester.enterText(find.byType(TextField), 'abcdefgh');
    await tester.pump();
    expect(tester.widget<TextField>(find.byType(TextField)).controller!.text, 'ABCD-EFGH');
    await tester.tap(find.text('Ulash'));
    await h.advance(tester, const Duration(seconds: 1));
    await tester.pumpAndSettle();

    expect(find.text('Xush kelibsiz!'), findsOneWidget);
    final Map<String, Object?> body = (h.adapter.requests.firstWhere((r) => r.uri.path.endsWith('/devices/pair')).data! as Map).cast<String, Object?>();
    expect(body['name'], 'Mijoz ekrani');
  });

  testWidgets('a wrong code is explained and the field stays', (WidgetTester tester) async {
    final DisplayHarness h = DisplayHarness();
    h.adapter.state.rejectPairing = true;
    await h.pump(tester, paired: false);
    await tester.enterText(find.byType(TextField), 'ZZZZ-ZZZZ');
    await tester.pump();
    await tester.tap(find.text('Ulash'));
    await h.advance(tester, const Duration(seconds: 1));
    await tester.pumpAndSettle();
    expect(find.textContaining("Kod noto'g'ri"), findsOneWidget);
    expect(find.text('Mijoz ekranini ulash'), findsOneWidget);
  });

  testWidgets('the owner removes the display in the panel: the server refuses the token and the pairing screen returns', (
    WidgetTester tester,
  ) async {
    final DisplayHarness h = DisplayHarness(realtime: false, pollInterval: const Duration(seconds: 2));
    h.backend.queueCalling = false;
    await h.pump(tester);
    expect(find.text('Xush kelibsiz!'), findsOneWidget);

    h.backend.refusal = const FakeResponse(401, <String, Object?>{'message': 'Unauthenticated.'});
    await h.advance(tester, const Duration(seconds: 3));
    await tester.pumpAndSettle();
    expect(find.text('Mijoz ekranini ulash'), findsOneWidget);
  });

  testWidgets('no module on: a plain message that tells the owner where to switch it on', (WidgetTester tester) async {
    final DisplayHarness h = DisplayHarness();
    h.backend
      ..customerDisplay = false
      ..queueCalling = false;
    await h.pump(tester);
    expect(find.text("Bu ekran hozircha o'chirilgan"), findsOneWidget);
    expect(find.textContaining('Sozlamalar → Format'), findsOneWidget);
  });

  testWidgets('a display paired without a branch shows the server\'s reason, not a blank screen', (WidgetTester tester) async {
    final DisplayHarness h = DisplayHarness();
    h.backend.refusal = const FakeResponse(422, <String, Object?>{
      'success': false,
      'message': 'Bu qurilma filialga ulanmagan. Uni filialni tanlab qayta ulang.',
      'data': <String, Object?>{'reason': 'branch_required'},
    });
    await h.pump(tester);
    expect(find.text('Ekran sozlanmagan'), findsOneWidget);
    expect(find.textContaining('filialga ulanmagan'), findsOneWidget);
    expect(find.byType(CircularProgressIndicator), findsNothing);
  });

  testWidgets('settings need a 2-second hold on the corner; the mode switch changes the screen at once and is remembered', (
    WidgetTester tester,
  ) async {
    final DisplayHarness h = DisplayHarness();
    h.backend.ready.add(FakeDisplayBackend.entry(15, 'ready', readyAt: DateTime.utc(2026, 10, 22, 12, 58)));
    await h.pump(tester);
    expect(find.text('Tayyorlanmoqda'), findsOneWidget, reason: 'auto: no cart, the board');

    await openSettings(tester);
    expect(find.text('Ekran sozlamalari'), findsOneWidget);
    await tester.tap(find.text('Ikkinchi ekran'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Yopish'));
    await tester.pumpAndSettle();

    expect(h.settings.value.mode, DisplayMode.cart);
    expect(find.text('Xush kelibsiz!'), findsOneWidget);
    expect(find.text('Tayyorlanmoqda'), findsNothing);
    await h.advance(tester, const Duration(milliseconds: 100));
    expect((h.settings.value.toMap())['mode'], 'cart');
  });

  testWidgets('sound off, light theme and a bigger font from the settings take effect', (WidgetTester tester) async {
    final DisplayHarness h = DisplayHarness();
    h.backend.customerDisplay = false;
    await h.pump(tester);

    await openSettings(tester);
    await tester.tap(find.text('Tayyor raqam ovozi'));
    await tester.pump();
    await tester.tap(find.text("Qorong'i mavzu"));
    await tester.pump();
    await tester.tap(find.text('150%'));
    await tester.pumpAndSettle();

    expect(h.settings.value.muted, isTrue);
    expect(h.settings.value.dark, isFalse);
    expect(h.settings.value.fontScale, 1.5);
    await tester.tap(find.text('Yopish'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  test('settings survive a restart; broken stored values fall back to the defaults', () async {
    final MemorySettingsStore store = MemorySettingsStore();
    final DisplaySettingsController a = DisplaySettingsController(store);
    a.update(const DisplaySettings(mode: DisplayMode.board, muted: true, dark: false, fontScale: 1.25));
    await Future<void>.delayed(Duration.zero);

    final DisplaySettingsController b = DisplaySettingsController(store);
    await b.load();
    expect(b.value, const DisplaySettings(mode: DisplayMode.board, muted: true, dark: false, fontScale: 1.25));

    final DisplaySettings broken = DisplaySettings.fromMap(<String, Object?>{'mode': 'weird', 'muted': 'yes', 'font_scale': 9.0});
    expect(broken, const DisplaySettings());
  });
}
