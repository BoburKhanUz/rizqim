import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_kds/src/app.dart';
import 'package:rizqim_kds/src/pairing_code_formatter.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

class _Adapter implements HttpClientAdapter {
  _Adapter(this.handler);

  ResponseBody Function(RequestOptions) handler;
  final List<RequestOptions> requests = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    return handler(options);
  }

  @override
  void close({bool force = false}) {}
}

ResponseBody _json(
  int status,
  Object body, {
  Map<String, List<String>> headers = const <String, List<String>>{},
}) => ResponseBody.fromString(
  jsonEncode(body),
  status,
  headers: <String, List<String>>{
    Headers.contentTypeHeader: <String>[Headers.jsonContentType],
    ...headers,
  },
);

ResponseBody _paired() => _json(201, <String, Object?>{
  'success': true,
  'message': 'OK',
  'data': <String, Object?>{
    'token': 'terminal-token',
    'device': <String, Object?>{
      'uuid': '11111111-1111-4111-8111-111111111111',
      'type': 'kds',
      'name': 'Oshxona ekrani',
      'platform': 'linux',
      'app_version': '0.1.0',
      'store_id': 7,
      'branch_id': 3,
    },
    'store': <String, Object?>{'id': 7, 'name': 'Rizqim Cafe'},
    'branch': <String, Object?>{'id': 3, 'name': 'Chilonzor'},
  },
});

/// Dio needs timers to run; in a widget test they only fire when the fake clock is pumped.
Future<T> settle<T>(WidgetTester tester, Future<T> future) async {
  await tester.pumpAndSettle();
  return future;
}

void main() {
  late MemoryKeyValueStore store;
  late _Adapter adapter;

  RizqimClient client() => RizqimClient(
    baseUrl: 'https://api.test/api',
    store: store,
    dio: Dio()..httpClientAdapter = adapter,
  );

  setUp(() {
    store = MemoryKeyValueStore();
    adapter = _Adapter((RequestOptions o) => _paired());
  });

  Future<void> pumpApp(WidgetTester tester, RizqimClient c) async {
    tester.view
      ..physicalSize =
          const Size(1280, 800) // a kitchen tablet
      ..devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    await c.auth.restore();
    await tester.pumpWidget(KdsApp(client: c));
  }

  RzButton pairButton(WidgetTester tester) =>
      tester.widget<RzButton>(find.widgetWithText(RzButton, 'Ulash'));

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
      await pumpApp(tester, client());

      expect(find.text('Terminalni ulash'), findsOneWidget);
      expect(find.text('Oshxona ekrani'), findsNothing);
      expect(pairButton(tester).onPressed, isNull);
    },
  );

  testWidgets(
    'typing the code without the dash formats it, enables the button and pairs',
    (WidgetTester tester) async {
      final RizqimClient c = client();
      await pumpApp(tester, c);

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
      expect(pairButton(tester).onPressed, isNotNull);

      await tester.tap(find.widgetWithText(RzButton, 'Ulash'));
      await tester.pumpAndSettle();

      expect(adapter.requests.single.uri.path, '/api/v1/devices/pair');
      expect(
        (adapter.requests.single.data as Map<Object?, Object?>)['code'],
        'ABCDEFGH',
      );
      expect(find.text('Terminalni ulash'), findsNothing);
      expect(find.text('Oshxona ekrani'), findsOneWidget);
      expect(find.text('Rizqim Cafe / Chilonzor'), findsOneWidget);
      expect(find.text("Hozircha buyurtma yo'q"), findsOneWidget);
      expect(store.values[CredentialStorage.key], contains('terminal-token'));
    },
  );

  testWidgets('a code typed with the dash pairs the same way', (
    WidgetTester tester,
  ) async {
    await pumpApp(tester, client());

    await tester.enterText(find.byType(TextField), 'ab12-cd34');
    await tester.pump();
    await tester.tap(find.widgetWithText(RzButton, 'Ulash'));
    await tester.pumpAndSettle();

    expect(
      (adapter.requests.single.data as Map<Object?, Object?>)['code'],
      'AB12CD34',
    );
  });

  testWidgets("the backend's refusal is shown and the form stays usable", (
    WidgetTester tester,
  ) async {
    adapter.handler = (RequestOptions o) => _json(422, <String, Object?>{
      'success': false,
      'message': "Kod noto'g'ri yoki muddati o'tgan.",
      'data': <String, Object?>{'reason': 'pairing_invalid'},
    });
    await pumpApp(tester, client());

    await tester.enterText(find.byType(TextField), 'ABCDEFGH');
    await tester.pump();
    await tester.tap(find.widgetWithText(RzButton, 'Ulash'));
    await tester.pumpAndSettle();

    expect(find.text("Kod noto'g'ri yoki muddati o'tgan."), findsOneWidget);
    expect(find.text('Terminalni ulash'), findsOneWidget);
    expect(pairButton(tester).onPressed, isNotNull);

    // typing again clears the message
    await tester.enterText(find.byType(TextField), 'ABCDEFGJ');
    await tester.pump();
    expect(find.text("Kod noto'g'ri yoki muddati o'tgan."), findsNothing);
  });

  testWidgets(
    'a paired terminal opens the kitchen screen at once after a restart',
    (WidgetTester tester) async {
      // first run pairs and stores the token...
      final RizqimClient first = client();
      await settle(tester, first.auth.pairTerminal('ABCD-EFGH'));
      adapter.requests.clear();

      // ...a new client over the same secure store is paired without asking for a code
      await pumpApp(tester, client());

      expect(find.text('Terminalni ulash'), findsNothing);
      expect(find.text('Oshxona ekrani'), findsWidgets);
      expect(adapter.requests, isEmpty);
    },
  );

  testWidgets('unpairing asks first, then returns to the pairing screen', (
    WidgetTester tester,
  ) async {
    final RizqimClient c = client();
    await settle(tester, c.auth.pairTerminal('ABCD-EFGH'));
    await pumpApp(tester, c);

    await tester.tap(find.byTooltip('Terminalni uzish'));
    await tester.pumpAndSettle();
    expect(
      find.text(
        "Bu qurilma do'kondan uziladi. Qayta ulash uchun yangi kod kerak bo'ladi.",
      ),
      findsOneWidget,
    );
    await tester.tap(find.widgetWithText(RzButton, 'Bekor qilish'));
    await tester.pumpAndSettle();
    expect(find.text('Terminalni ulash'), findsNothing);

    await tester.tap(find.byTooltip('Terminalni uzish'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(RzButton, 'Uzish'));
    await tester.pumpAndSettle();

    expect(find.text('Terminalni ulash'), findsOneWidget);
    expect(store.values, isEmpty);
  });

  testWidgets(
    'a server that revokes the terminal sends the app back to pairing',
    (WidgetTester tester) async {
      final RizqimClient c = client();
      await settle(tester, c.auth.pairTerminal('ABCD-EFGH'));
      adapter.handler = (RequestOptions o) =>
          _json(401, <String, Object?>{'message': 'Unauthenticated.'});
      await pumpApp(tester, c);
      expect(find.text('Terminalni ulash'), findsNothing);

      await settle(
        tester,
        expectLater(c.auth.loadStaff(), throwsA(isA<ApiException>())),
      );
      await tester.pumpAndSettle();

      expect(find.text('Terminalni ulash'), findsOneWidget);
    },
  );

  testWidgets(
    'a secure store that cannot write is reported on the kitchen screen',
    (WidgetTester tester) async {
      final RizqimClient c = RizqimClient(
        baseUrl: 'https://api.test/api',
        store: _BrokenStore(),
        dio: Dio()..httpClientAdapter = adapter,
      );
      await settle(tester, c.auth.pairTerminal('ABCD-EFGH'));
      await tester.pumpWidget(
        KdsApp(client: c),
      ); // no restore(): the store is empty and unreadable

      expect(find.textContaining('saqlanmadi'), findsOneWidget);
    },
  );

  testWidgets(
    'light and dark themes both build the kitchen screen without overflow on a phone and a tablet',
    (WidgetTester tester) async {
      final RizqimClient c = client();
      await settle(tester, c.auth.pairTerminal('ABCD-EFGH'));
      for (final Size size in const <Size>[Size(360, 640), Size(1280, 800)]) {
        tester.view
          ..physicalSize = size
          ..devicePixelRatio = 1;
        for (final ThemeData theme in <ThemeData>[
          RzTheme.light(),
          RzTheme.dark(),
        ]) {
          await tester.pumpWidget(
            MaterialApp(
              theme: theme,
              home: AuthGate(auth: c.auth),
            ),
          );
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
        }
      }
      addTearDown(tester.view.reset);
    },
  );
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
