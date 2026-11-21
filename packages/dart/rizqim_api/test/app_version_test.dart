import 'package:dio/dio.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/fake_backend.dart';

/// M5 S5: the app version goes on every request, and a version below the minimum is refused at pairing (426).
void main() {
  RizqimClient clientWith(FakeAdapter adapter, {String? appVersion}) =>
      RizqimClient(
        baseUrl: 'https://api.test/api',
        store: MemoryKeyValueStore(),
        dio: Dio()..httpClientAdapter = adapter,
        app: 'cashier',
        appVersion: appVersion,
      );

  test('the version is sent on every request next to the app', () async {
    final FakeAdapter adapter = FakeAdapter(
      (RequestOptions o) => ok(<String, Object?>{}),
    );
    final RizqimClient client = clientWith(adapter, appVersion: '1.4.0+7');
    addTearDown(client.auth.dispose);

    await client.dio.get<Object?>('/v1/terminal');
    await client.dio.post<Object?>('/v1/pos/sync', data: <String, Object?>{});

    for (final RequestOptions r in adapter.requests) {
      expect(r.headers[RizqimClient.appVersionHeader], '1.4.0+7');
      expect(r.headers[RizqimClient.appHeader], 'cashier');
    }
    expect(RizqimClient.appVersionHeader, 'X-Rizqim-App-Version');
  });

  test('without a version no header is sent', () async {
    final FakeAdapter adapter = FakeAdapter(
      (RequestOptions o) => ok(<String, Object?>{}),
    );
    final RizqimClient client = clientWith(adapter);
    addTearDown(client.auth.dispose);
    await client.dio.get<Object?>('/v1/terminal');
    expect(
      adapter.requests.single.headers.containsKey(
        RizqimClient.appVersionHeader,
      ),
      isFalse,
    );
  });

  test('426 app_outdated at pairing is a typed exception', () async {
    final FakeAdapter adapter = FakeAdapter(
      (RequestOptions o) => json(426, <String, Object?>{
        'success': false,
        'message': "Ilova eskirgan, yangilang.",
        'data': <String, Object?>{
          'reason': 'app_outdated',
          'current': '1.0.0+1',
          'minimum': '1.2.0+5',
          'url': 'https://pos.rizqim.uz/files/cashier/android/a.apk',
        },
      }),
    );
    final RizqimClient client = clientWith(adapter, appVersion: '1.0.0+1');
    addTearDown(client.auth.dispose);

    try {
      await client.auth.pairTerminal(
        'ABCD-EFGH',
        platform: 'android',
        appVersion: '1.0.0+1',
        app: 'cashier',
      );
      fail('must throw');
    } on AppOutdatedException catch (e) {
      expect(e.message, 'Ilova eskirgan, yangilang.');
      expect(e.current, '1.0.0+1');
      expect(e.minimum, '1.2.0+5');
      expect(e.url, 'https://pos.rizqim.uz/files/cashier/android/a.apk');
      expect(e.statusCode, 426);
      expect(e.reason, 'app_outdated');
      // An old `on ApiException` handler still catches it
      expect(e, isA<ApiException>());
    }
    expect(client.auth.isPaired, isFalse);
  });

  test('another 426 or another pairing error stays an ApiException', () async {
    final FakeAdapter adapter = FakeAdapter(
      (RequestOptions o) =>
          refuse(422, 'Kod noto\'g\'ri', reason: 'pairing_invalid'),
    );
    final RizqimClient client = clientWith(adapter);
    addTearDown(client.auth.dispose);
    await expectLater(
      client.auth.pairTerminal('ABCD-EFGH'),
      throwsA(
        isA<ApiException>()
            .having(
              (ApiException e) => e is AppOutdatedException,
              'typed',
              false,
            )
            .having((ApiException e) => e.reason, 'reason', 'pairing_invalid'),
      ),
    );
  });

  // Review M3: a till holding queued operations says so at pairing; the server then never refuses it with 426
  test(
    'pairing sends pending_operations when given, and leaves it out otherwise',
    () async {
      final FakeAdapter adapter = FakeAdapter(
        (RequestOptions o) => ok(pairData()),
      );
      final RizqimClient client = clientWith(adapter, appVersion: '1.0.0+1');
      addTearDown(client.auth.dispose);
      Map<Object?, Object?> body(int i) =>
          adapter.requests[i].data as Map<Object?, Object?>;

      await client.auth.pairTerminal('ABCD-EFGH', pendingOperations: 3);
      expect(body(0)['pending_operations'], 3);

      await client.auth.remote.pair('ABCD-EFGH', pendingOperations: 0);
      expect(body(1)['pending_operations'], 0);

      await client.auth.pairTerminal('ABCD-EFGH');
      expect(body(2).containsKey('pending_operations'), isFalse);
    },
  );
}
