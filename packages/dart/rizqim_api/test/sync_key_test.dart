import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/fake_backend.dart';

/// Wave M5 S3-c (docs/scale/M5_DESIGN.md 4): the till keeps the key of its signed offline queue in the device's secure
/// storage, in the same record as the terminal token. It arrives in the pairing answer (`sync_key`) or from
/// `POST /terminal/sync-key`, and leaves the device with the terminal.
void main() {
  late MemoryKeyValueStore store;
  late FakeAdapter adapter;
  late RizqimClient client;

  /// A key as the server gives it: base64 of 32 bytes, generated here (never a literal).
  String newKey(int seed) {
    final Random r = Random(seed);
    return base64Encode(<int>[for (var i = 0; i < 32; i++) r.nextInt(256)]);
  }

  RizqimClient build() => RizqimClient(
    baseUrl: 'https://api.test/api',
    store: store,
    dio: Dio()..httpClientAdapter = adapter,
  );

  Map<String, Object?> paired({Object? syncKey, bool withKeyField = true}) =>
      <String, Object?>{...pairData(), if (withKeyField) 'sync_key': syncKey};

  void serve({
    Object? pairKey,
    bool withKeyField = true,
    FutureOr<ResponseBody> Function(RequestOptions o)? syncKey,
  }) {
    adapter.handler = (RequestOptions o) {
      if (o.uri.path == '/api/v1/devices/pair') {
        return ok(paired(syncKey: pairKey, withKeyField: withKeyField));
      }
      if (o.uri.path == '/api/v1/terminal/sync-key' && syncKey != null) {
        return syncKey(o);
      }
      return refuse(404, 'unhandled ${o.method} ${o.uri.path}');
    };
  }

  setUp(() {
    store = MemoryKeyValueStore();
    adapter = FakeAdapter((RequestOptions o) => refuse(404, 'unhandled'));
    client = build();
  });

  tearDown(() => client.auth.dispose());

  group('the pairing answer', () {
    test('its sync key is kept beside the terminal token and comes back after a restart', () async {
      final String key = newKey(1);
      serve(pairKey: <String, Object?>{'key': key, 'kid': 1});

      final PairedTerminal t = await client.auth.pairTerminal('ABCD-EFGH');

      expect(t.syncKey?.kid, 1);
      expect(client.auth.syncKey?.key, key);
      expect(client.auth.storageError, isNull);
      // One record in the secure storage: the token and the key together, nothing else written
      expect(store.values.keys, <String>[CredentialStorage.key]);
      expect(store.values[CredentialStorage.key], contains(key));

      final RizqimClient again = build();
      addTearDown(again.auth.dispose);
      await again.auth.restore();
      expect(again.auth.terminalToken, 'terminal-token');
      expect(again.auth.syncKey?.key, key);
      expect(again.auth.syncKey?.kid, 1);
    });

    test('the key is not shown by toString', () async {
      final String key = newKey(2);
      serve(pairKey: <String, Object?>{'key': key, 'kid': 3});
      await client.auth.pairTerminal('ABCD-EFGH');

      expect('${client.auth.syncKey}', isNot(contains(key)));
      expect('${client.auth.syncKey}', contains('3'));
    });

    test('no key (KDS, customer display, an older server): pairing works as before', () async {
      serve(pairKey: null);
      await client.auth.pairTerminal('ABCD-EFGH');
      expect(client.auth.isPaired, isTrue);
      expect(client.auth.syncKey, isNull);

      serve(withKeyField: false);
      await client.auth.pairTerminal('ABCD-EFGH');
      expect(client.auth.isPaired, isTrue);
      expect(client.auth.syncKey, isNull);
    });

    test('a key of an unknown shape never breaks the pairing', () async {
      for (final Object? odd in <Object?>[
        'text',
        7,
        <Object?>[],
        <String, Object?>{'key': 5, 'kid': 1},
        <String, Object?>{'key': newKey(3)},
        <String, Object?>{'key': newKey(3), 'kid': 'one'},
        <String, Object?>{'key': '', 'kid': 1},
        <String, Object?>{'key': newKey(3), 'kid': 0},
      ]) {
        serve(pairKey: odd);
        await client.auth.pairTerminal('ABCD-EFGH');
        expect(client.auth.isPaired, isTrue, reason: '$odd');
        expect(client.auth.syncKey, isNull, reason: '$odd');
      }
    });

    test('a record saved before signing existed is read as before', () async {
      store.values[CredentialStorage.key] = jsonEncode(<String, Object?>{
        'terminal': pairData(),
        'user': null,
      });
      await client.auth.restore();
      expect(client.auth.terminalToken, 'terminal-token');
      expect(client.auth.syncKey, isNull);
    });
  });

  group('POST /terminal/sync-key', () {
    test('asked with the terminal token and no Idempotency-Key; the new key replaces the old one on the device', () async {
      final String first = newKey(4);
      final String second = newKey(5);
      serve(
        pairKey: <String, Object?>{'key': first, 'kid': 1},
        syncKey: (RequestOptions o) =>
            ok(<String, Object?>{'key': second, 'kid': 2}),
      );
      await client.auth.pairTerminal('ABCD-EFGH');

      final TerminalSyncKey? got = await client.auth.renewSyncKey();

      expect(got?.kid, 2);
      expect(client.auth.syncKey?.key, second);
      final RequestOptions sent = adapter.requests.last;
      expect(sent.method, 'POST');
      expect(sent.uri.path, '/api/v1/terminal/sync-key');
      expect(sent.headers['Authorization'], 'Bearer terminal-token');
      expect(sent.headers.containsKey('Idempotency-Key'), isFalse);
      expect(store.values[CredentialStorage.key], contains(second));
      expect(store.values[CredentialStorage.key], isNot(contains(first)));
      // The terminal itself is the same one
      expect(client.auth.terminalToken, 'terminal-token');
      expect(client.auth.terminal?.branch?.id, 3);
    });

    test(
      'two callers at once make one request (every call rotates on the server)',
      () async {
        final Completer<void> gate = Completer<void>();
        final String key = newKey(6);
        serve(
          syncKey: (RequestOptions o) async {
            await gate.future;
            return ok(<String, Object?>{'key': key, 'kid': 1});
          },
        );
        await client.auth.pairTerminal('ABCD-EFGH');

        final Future<TerminalSyncKey?> a = client.auth.renewSyncKey();
        final Future<TerminalSyncKey?> b = client.auth.renewSyncKey();
        await Future<void>.delayed(Duration.zero);
        gate.complete();

        expect((await a)?.kid, 1);
        expect((await b)?.kid, 1);
        expect(adapter.count('/api/v1/terminal/sync-key'), 1);
      },
    );

    test('a failed call keeps the key the till has', () async {
      final String key = newKey(7);
      serve(
        pairKey: <String, Object?>{'key': key, 'kid': 1},
        syncKey: (RequestOptions o) => refuse(500, 'Server xatosi'),
      );
      await client.auth.pairTerminal('ABCD-EFGH');

      await expectLater(
        client.auth.renewSyncKey(),
        throwsA(isA<ApiException>()),
      );
      expect(client.auth.syncKey?.key, key);
      expect(client.auth.syncKey?.kid, 1);
    });

    test(
      'an answer that is not a key is an error, the old key stays',
      () async {
        final String key = newKey(8);
        serve(
          pairKey: <String, Object?>{'key': key, 'kid': 1},
          syncKey: (RequestOptions o) => ok(<String, Object?>{'kid': 2}),
        );
        await client.auth.pairTerminal('ABCD-EFGH');

        await expectLater(
          client.auth.renewSyncKey(),
          throwsA(isA<ApiException>()),
        );
        expect(client.auth.syncKey?.key, key);
      },
    );

    test(
      'an answer that arrives after the terminal was unpaired is thrown away',
      () async {
        final Completer<void> gate = Completer<void>();
        final String key = newKey(9);
        serve(
          syncKey: (RequestOptions o) async {
            await gate.future;
            return ok(<String, Object?>{'key': key, 'kid': 1});
          },
        );
        await client.auth.pairTerminal('ABCD-EFGH');

        final Future<TerminalSyncKey?> call = client.auth.renewSyncKey();
        await Future<void>.delayed(Duration.zero);
        await client.auth.forgetTerminal();
        gate.complete();

        expect(await call, isNull);
        expect(client.auth.syncKey, isNull);
        expect(store.values, isEmpty);
      },
    );

    test('not paired: nothing is asked', () async {
      expect(await client.auth.renewSyncKey(), isNull);
      expect(adapter.requests, isEmpty);
    });
  });

  group('the key leaves with the terminal', () {
    test('unpairing removes it from the device', () async {
      final String key = newKey(10);
      serve(pairKey: <String, Object?>{'key': key, 'kid': 1});
      await client.auth.pairTerminal('ABCD-EFGH');

      await client.auth.forgetTerminal();

      expect(client.auth.syncKey, isNull);
      expect(store.values, isEmpty);
    });

    test('a terminal the server revoked (401) loses it too', () async {
      final String key = newKey(11);
      serve(pairKey: <String, Object?>{'key': key, 'kid': 1});
      await client.auth.pairTerminal('ABCD-EFGH');

      await client.auth.handleTerminalRejected('terminal-token');

      expect(client.auth.syncKey, isNull);
      expect(store.values, isEmpty);
    });

    test('pairing again takes the new pairing\'s key', () async {
      final String first = newKey(12);
      final String second = newKey(13);
      serve(pairKey: <String, Object?>{'key': first, 'kid': 4});
      await client.auth.pairTerminal('ABCD-EFGH');
      serve(pairKey: <String, Object?>{'key': second, 'kid': 1});
      await client.auth.pairTerminal('ABCD-EFGH');

      expect(client.auth.syncKey?.key, second);
      expect(client.auth.syncKey?.kid, 1);
      expect(store.values[CredentialStorage.key], isNot(contains(first)));
    });
  });
}
