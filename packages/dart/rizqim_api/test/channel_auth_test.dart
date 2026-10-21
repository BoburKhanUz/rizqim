import 'package:dio/dio.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/fake_backend.dart';

void main() {
  test('the terminal signs the branch channel with its own token, form-encoded, without a PIN session', () async {
    final FakeAdapter adapter = FakeAdapter((RequestOptions o) {
      if (o.uri.path == '/api/v1/devices/pair') return ok(pairData());
      return json(200, <String, Object?>{'auth': 'key:abc'});
    });
    final RizqimClient client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: MemoryKeyValueStore(),
      dio: Dio()..httpClientAdapter = adapter,
    );
    await client.auth.pairTerminal('ABCD-EFGH');

    final String auth = await client.channelAuth.terminal(
      '1.2',
      'private-pos-branch.3',
    );

    final RequestOptions r = adapter.requests.last;
    expect(auth, 'key:abc');
    expect(r.uri.path, '/api/broadcasting/auth');
    expect(r.headers['Authorization'], 'Bearer terminal-token');
    expect(r.contentType, Headers.formUrlEncodedContentType);
    expect(r.data, <String, String>{
      'socket_id': '1.2',
      'channel_name': 'private-pos-branch.3',
    });
    await client.auth.dispose();
  });

  test(
    'a refusal is an ApiException; a revoked terminal token unpairs',
    () async {
      final FakeAdapter adapter = FakeAdapter((RequestOptions o) {
        if (o.uri.path == '/api/v1/devices/pair') return ok(pairData());
        return json(401, <String, Object?>{'message': 'Unauthenticated.'});
      });
      final RizqimClient client = RizqimClient(
        baseUrl: 'https://api.test/api',
        store: MemoryKeyValueStore(),
        dio: Dio()..httpClientAdapter = adapter,
      );
      await client.auth.pairTerminal('ABCD-EFGH');
      await expectLater(
        client.channelAuth.terminal('1.2', 'private-pos-branch.3'),
        throwsA(
          isA<ApiException>().having(
            (ApiException e) => e.statusCode,
            'status',
            401,
          ),
        ),
      );
      expect(client.auth.isPaired, isFalse);
      await client.auth.dispose();
    },
  );
}
