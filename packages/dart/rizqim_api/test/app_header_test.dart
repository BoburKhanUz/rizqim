import 'package:dio/dio.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/fake_backend.dart';

/// P6 review (S4): a POS app says which app it is on every request (`X-Rizqim-App`), so the server records it on a
/// terminal paired before the app was recorded and never makes a waiter's phone the LAN hub.
void main() {
  RizqimClient clientWith(FakeAdapter adapter, {String? app}) => RizqimClient(
    baseUrl: 'https://api.test/api',
    store: MemoryKeyValueStore(),
    dio: Dio()..httpClientAdapter = adapter,
    app: app,
  );

  test('the app is sent on every request', () async {
    final FakeAdapter adapter = FakeAdapter(
      (RequestOptions o) => ok(<String, Object?>{}),
    );
    final RizqimClient client = clientWith(adapter, app: 'waiter');
    addTearDown(client.auth.dispose);

    await client.dio.get<Object?>('/v1/pos/lan');
    await client.dio.post<Object?>('/v1/pos/sync', data: <String, Object?>{});

    expect(
      adapter.requests.map(
        (RequestOptions r) => r.headers[RizqimClient.appHeader],
      ),
      <String>['waiter', 'waiter'],
    );
  });

  test('a client without an app sends no such header', () async {
    final FakeAdapter adapter = FakeAdapter(
      (RequestOptions o) => ok(<String, Object?>{}),
    );
    final RizqimClient client = clientWith(adapter);
    addTearDown(client.auth.dispose);

    await client.dio.get<Object?>('/v1/terminal');

    expect(
      adapter.requests.single.headers.containsKey(RizqimClient.appHeader),
      isFalse,
    );
  });
}
