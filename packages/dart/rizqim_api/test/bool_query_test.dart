import 'package:dio/dio.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/fake_backend.dart';

/// Laravel's `boolean` rule accepts 1/0 but rejects the text "true"/"false", which Dio writes for a `bool` query value.
void main() {
  late FakeAdapter adapter;
  late RizqimClient client;

  setUp(() {
    adapter = FakeAdapter((RequestOptions o) => ok(<String, Object?>{}));
    client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: MemoryKeyValueStore(),
      dio: Dio()..httpClientAdapter = adapter,
    );
  });

  tearDown(() => client.auth.dispose());

  test('a generated call sends bool query values as 1/0', () async {
    // The response body does not matter here: only the request is checked
    await client.api.ownerDevices
        .storeDeviceIndex(includeRevoked: false)
        .then((_) {}, onError: (Object _) {});
    await client.api.ownerDevices
        .storeDeviceIndex(includeRevoked: true)
        .then((_) {}, onError: (Object _) {});
    expect(adapter.requests.map((RequestOptions r) => r.uri.query), <String>[
      'include_revoked=0',
      'include_revoked=1',
    ]);
  });

  test('a hand-written call sends bool query values as 1/0', () async {
    await client.dio.get<Object?>(
      '/v1/owner/batches',
      queryParameters: <String, Object?>{'include_closed': true, 'x': 'true'},
    );
    // Text and numbers are left as they were
    expect(adapter.requests.single.uri.queryParameters, <String, String>{
      'include_closed': '1',
      'x': 'true',
    });
  });

  test('a list value with booleans keeps its shape', () async {
    await client.dio.get<Object?>(
      '/v1/x',
      queryParameters: <String, Object?>{
        'a': <Object?>[true, false],
      },
    );
    expect(adapter.requests.single.uri.query, 'a=1&a=0');
  });
}
