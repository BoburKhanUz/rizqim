import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_kds/src/app.dart';
import 'package:rizqim_kds/src/pairing_code_formatter.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/fake_kds.dart';
import 'support/fake_kitchen_state.dart';
import 'support/helpers.dart';

void main() {
  // Harness test ichida yaratiladi: setUp zonasida yaratilgan Dio/omborlar soxta soat zonasida qotib qoladi.
  RzButton pairButton(WidgetTester tester) =>
      tester.widget<RzButton>(buttonLabeled('Ulash'));

  group('PairingCodeFormatter', () {
    TextEditingValue type(String text) => const PairingCodeFormatter()
        .formatEditUpdate(TextEditingValue.empty, TextEditingValue(text: text));

    test('accepts the code with or without the dash', () {
      expect(type('abcdefgh').text, 'ABCD-EFGH');
      expect(type('abcd-efgh').text, 'ABCD-EFGH');
      expect(type('abcd efgh').text, 'ABCD-EFGH');
      expect(type('abcd').text, 'ABCD');
      expect(type('abcde').text, 'ABCD-E');
      expect(type('abcdefghxyz').text, 'ABCD-EFGH');
      expect(type('abcd-efgh').selection.baseOffset, 9);
    });
  });

  testWidgets(
    'an unpaired terminal starts on the pairing screen with a disabled button',
    (WidgetTester tester) async {
      final KdsHarness h = KdsHarness(realtime: false);
      await pumpKds(tester, h);

      expect(find.text('Terminalni ulash'), findsOneWidget);
      expect(pairButton(tester).onPressed, isNull);
      // the same path and button names as the partner panel
      expect(
        find.textContaining(
          'Sozlamalar → Qurilmalar → «Ulash kodini yaratish»',
        ),
        findsOneWidget,
      );
    },
  );

  testWidgets(
    'typing the code without the dash formats it, pairs, and opens the PIN screen with the staff list',
    (WidgetTester tester) async {
      final KdsHarness h = KdsHarness(realtime: false);
      await pumpKds(tester, h);

      await tester.enterText(find.byType(TextField), 'abcdefg');
      await tester.pump();
      expect(
        pairButton(tester).onPressed,
        isNull,
        reason: '7 characters are not a code',
      );

      await tester.enterText(find.byType(TextField), 'abcdefgh');
      await tester.pump();
      expect(
        tester.widget<TextField>(find.byType(TextField)).controller!.text,
        'ABCD-EFGH',
      );
      await tester.tap(buttonLabeled('Ulash'));
      await tester.pumpAndSettle();

      final RequestOptions pair = h.backend.requests.first;
      expect(pair.uri.path, '/api/v1/devices/pair');
      expect((pair.data as Map<Object?, Object?>)['code'], 'ABCDEFGH');
      expect(find.text('Terminalni ulash'), findsNothing);
      expect(find.text('Oshxona ekrani'), findsOneWidget);
      expect(find.text('Rizqim Osh Markazi / Chilonzor'), findsOneWidget);
      expect(find.text('Kim ishlaydi?'), findsOneWidget);
      // cooks first, then by name
      expect(
        tester.getTopLeft(find.text('Aziz Karimov')).dy,
        lessThanOrEqualTo(tester.getTopLeft(find.text('Bobur Aliyev')).dy),
      );
      expect(find.text('Oshpaz'), findsNWidgets(2));
      expect(find.text('Menejer'), findsOneWidget);
      final RequestOptions staff = h.backend
          .to('GET', '/v1/terminal/staff')
          .single;
      expect(
        staff.headers['Authorization'],
        'Bearer ${FakeKitchenState.terminalToken}',
      );
      expect(h.store.values[CredentialStorage.key], contains('terminal-token'));
    },
  );

  testWidgets('a code typed with the dash pairs the same way', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = KdsHarness(realtime: false);
    await pumpKds(tester, h);
    await tester.enterText(find.byType(TextField), 'ab12-cd34');
    await tester.pump();
    await tester.tap(buttonLabeled('Ulash'));
    await tester.pumpAndSettle();
    expect(
      (h.backend.requests.first.data as Map<Object?, Object?>)['code'],
      'AB12CD34',
    );
  });

  testWidgets("the backend's refusal is shown and the form stays usable", (
    WidgetTester tester,
  ) async {
    final KdsHarness h = KdsHarness(realtime: false);
    h.backend.respond = (RequestOptions o) =>
        const FakeResponse(422, <String, Object?>{
          'success': false,
          'message': "Kod noto'g'ri yoki muddati o'tgan.",
          'data': <String, Object?>{'reason': 'pairing_invalid'},
        });
    await pumpKds(tester, h);

    await tester.enterText(find.byType(TextField), 'ABCDEFGH');
    await tester.pump();
    await tester.tap(buttonLabeled('Ulash'));
    await tester.pumpAndSettle();

    expect(find.text("Kod noto'g'ri yoki muddati o'tgan."), findsOneWidget);
    expect(find.text('Terminalni ulash'), findsOneWidget);
    expect(pairButton(tester).onPressed, isNotNull);

    await tester.enterText(find.byType(TextField), 'ABCDEFGJ');
    await tester.pump();
    expect(find.text("Kod noto'g'ri yoki muddati o'tgan."), findsNothing);
  });

  testWidgets(
    'a paired terminal opens the PIN screen at once after a restart',
    (WidgetTester tester) async {
      final KdsHarness h = KdsHarness(realtime: false);
      await settle(tester, h.client.auth.pairTerminal('ABCD-EFGH'));
      final KdsHarness second = KdsHarness(realtime: false);
      second.store.values.addAll(h.store.values);

      await pumpKds(tester, second);

      expect(find.text('Terminalni ulash'), findsNothing);
      expect(find.text('Kim ishlaydi?'), findsOneWidget);
      expect(second.backend.to('POST', '/devices/pair'), isEmpty);
    },
  );

  testWidgets(
    'the PIN screen offers neither settings nor unpairing: nobody changes the screen without a PIN',
    (WidgetTester tester) async {
      final KdsHarness h = KdsHarness(realtime: false);
      await pumpPaired(tester, h);
      expect(find.text('Kim ishlaydi?'), findsOneWidget);
      expect(find.byTooltip('Terminalni uzish'), findsNothing);
      expect(find.byTooltip('Sozlamalar'), findsNothing);
      expect(find.byIcon(Icons.link_off), findsNothing);
    },
  );

  testWidgets('a cook cannot unpair: the settings have no "Terminalni uzish"', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = KdsHarness(realtime: false);
    await pumpBoard(tester, h);
    await tester.tap(find.byTooltip('Sozlamalar'));
    await tester.pumpAndSettle();
    expect(find.text('Sozlamalar'), findsOneWidget);
    expect(buttonLabeled('Terminalni uzish'), findsNothing);
  });

  testWidgets(
    'a manager signs in with their own PIN and unpairs: asks first, revokes the terminal on the server, returns to pairing',
    (WidgetTester tester) async {
      final KdsHarness h = KdsHarness(realtime: false);
      await pumpPaired(tester, h);
      await tester.tap(find.text('Bobur Aliyev'));
      await tester.pumpAndSettle();
      await typePin(tester, h.state.pin);
      expect(find.text('Kim ishlaydi?'), findsNothing);

      await tester.tap(find.byTooltip('Sozlamalar'));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled('Terminalni uzish'));
      await tester.pumpAndSettle();
      expect(find.text('Sozlamalar'), findsNothing, reason: 'settings closed');
      expect(
        find.textContaining(
          "Bu qurilma do'kondan uziladi va serverda ham o'chiriladi.",
        ),
        findsOneWidget,
      );
      await tester.tap(buttonLabeled('Bekor qilish'));
      await tester.pumpAndSettle();
      expect(find.text('Terminalni ulash'), findsNothing);
      expect(h.client.auth.staff, isNotNull);

      await tester.tap(find.byTooltip('Sozlamalar'));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled('Terminalni uzish'));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled('Uzish'));
      await tester.pumpAndSettle();

      expect(find.text('Terminalni ulash'), findsOneWidget);
      expect(find.byType(Dialog), findsNothing);
      expect(h.store.values, isEmpty);
      expect(
        h.backend.to('DELETE', RegExp(r'/v1/terminal$')),
        hasLength(1),
        reason: 'the terminal revokes itself on the server before forgetting',
      );
      expect(
        h.backend
            .to('DELETE', RegExp(r'/v1/terminal$'))
            .single
            .headers['Authorization'],
        'Bearer ${FakeKitchenState.terminalToken}',
      );
      expect(find.textContaining('boshqaruv panelida'), findsNothing);
    },
  );

  testWidgets(
    'unpairing without a connection still unpairs here and tells to remove the device in the panel',
    (WidgetTester tester) async {
      final KdsHarness h = KdsHarness(realtime: false);
      await pumpPaired(tester, h);
      await tester.tap(find.text('Bobur Aliyev'));
      await tester.pumpAndSettle();
      await typePin(tester, h.state.pin);
      await tester.tap(find.byTooltip('Sozlamalar'));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled('Terminalni uzish'));
      await tester.pumpAndSettle();
      h.backend.respond = (RequestOptions o) =>
          o.method == 'DELETE' && o.uri.path.endsWith('/v1/terminal')
          ? throw DioException.connectionError(
              requestOptions: o,
              reason: 'offline',
            )
          : null;
      await tester.tap(buttonLabeled('Uzish'));
      await tester.pumpAndSettle();

      expect(find.text('Terminalni ulash'), findsOneWidget);
      expect(h.store.values, isEmpty);
      expect(find.textContaining('boshqaruv panelida'), findsOneWidget);
    },
  );

  testWidgets(
    'a server that revokes the terminal sends the app back to pairing',
    (WidgetTester tester) async {
      final KdsHarness h = KdsHarness(realtime: false);
      await pumpPaired(tester, h);
      h.backend.respond = (RequestOptions o) => const FakeResponse(
        401,
        <String, Object?>{'message': 'Unauthenticated.'},
      );

      await settle(
        tester,
        expectLater(h.client.auth.loadStaff(), throwsA(isA<ApiException>())),
      );
      await tester.pumpAndSettle();

      expect(find.text('Terminalni ulash'), findsOneWidget);
    },
  );

  testWidgets(
    'a secure store that cannot write is reported on the PIN screen',
    (WidgetTester tester) async {
      final KdsHarness broken = KdsHarness(realtime: false);
      final RizqimClient client = RizqimClient(
        baseUrl: 'https://api.test/api',
        store: _BrokenStore(),
        dio: broken.client.dio,
      );
      await settle(tester, client.auth.pairTerminal('ABCD-EFGH'));
      setScreen(tester, const Size(1280, 800));
      await tester.pumpWidget(
        KdsApp(
          services: KdsServices(client: client, settings: broken.settings),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.textContaining('saqlanmadi'), findsOneWidget);
    },
  );

  testWidgets(
    'the PIN screen builds without overflow on a phone, a tablet and a 1080p TV, light and dark',
    (WidgetTester tester) async {
      final KdsHarness h = KdsHarness(realtime: false);
      await settle(tester, h.client.auth.pairTerminal('ABCD-EFGH'));
      for (final Size size in const <Size>[
        Size(360, 640),
        Size(1280, 800),
        Size(1920, 1080),
      ]) {
        setScreen(tester, size);
        for (final bool dark in <bool>[true, false]) {
          h.settings.value = h.settings.value.copyWith(dark: dark);
          await tester.pumpWidget(KdsApp(services: h.services));
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
          expect(find.text('Kim ishlaydi?'), findsOneWidget);
        }
      }
    },
  );

  testWidgets('the dark theme is the default for kitchens', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = KdsHarness(realtime: false);
    await pumpKds(tester, h);
    final MaterialApp app = tester.widget<MaterialApp>(
      find.byType(MaterialApp),
    );
    expect(app.themeMode, ThemeMode.dark);
  });
}

class _BrokenStore implements SecureKeyValueStore {
  @override
  Future<String?> read(String key) =>
      Future<String?>.error(StateError('no keyring'));

  @override
  Future<void> write(String key, String value) =>
      Future<void>.error(StateError('no keyring'));

  @override
  Future<void> delete(String key) =>
      Future<void>.error(StateError('no keyring'));
}
