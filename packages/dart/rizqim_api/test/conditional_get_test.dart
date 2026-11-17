import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:fake_async/fake_async.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/fake_backend.dart';
import 'support/fake_socket.dart';
import 'support/kitchen_fixtures.dart';

/// Wave M2: the ETag store, the read tracking, the channel revision state machine, `GET /v1/realtime`, silence
/// detection and the reconnect spread.
void main() {
  group('conditional GETs (EtagStore + ConditionalGetInterceptor)', () {
    late DateTime now;
    late FakeAdapter adapter;
    late RizqimClient client;
    late Map<String, Object?> body;
    late String etag;
    late int revision;

    /// A server that answers the kitchen board as the real one: an ETag (here: the body's revision), `X-Revision`,
    /// and `304` to a matching `If-None-Match`.
    ResponseBody board(RequestOptions o) {
      final String tag = '"c1.$etag"';
      if (o.headers['If-None-Match'] == tag) {
        return ResponseBody.fromString(
          '',
          304,
          headers: <String, List<String>>{
            'etag': <String>[tag],
          },
        );
      }
      return ResponseBody.fromString(
        jsonEncode(body),
        200,
        headers: <String, List<String>>{
          Headers.contentTypeHeader: <String>[Headers.jsonContentType],
          'etag': <String>[tag],
          'x-revision': <String>['$revision'],
          'x-revision-scope': <String>['branch:3'],
          'date': <String>['Tue, 20 Oct 2026 07:00:00 GMT'],
        },
      );
    }

    setUp(() async {
      now = DateTime.utc(2026, 10, 20, 7);
      etag = 'a';
      revision = 9007199254740993; // above 2^53: must not lose precision
      body = <String, Object?>{
        'success': true,
        'message': '',
        'data': <Object?>[ticketJson()],
        'meta': <String, Object?>{'truncated': false},
      };
      adapter = FakeAdapter((RequestOptions o) {
        if (o.uri.path == '/api/v1/devices/pair') return ok(pairData());
        if (o.uri.path.endsWith('/staff-session')) {
          return ok(staffData(expiresAt: now.add(const Duration(hours: 12))));
        }
        if (o.uri.path == '/api/v1/staff/kitchen/tickets') return board(o);
        return refuse(404, 'unhandled ${o.uri.path}');
      });
      client = RizqimClient(
        baseUrl: 'https://api.test/api',
        store: MemoryKeyValueStore(),
        dio: Dio()..httpClientAdapter = adapter,
        clock: () => now,
      );
      await client.auth.pairTerminal('ABCD-EFGH');
      await client.auth.signInStaff(userId: 5, pin: '4821');
      adapter.requests.clear();
    });

    tearDown(() => client.auth.dispose());

    test('a 304 returns the kept body and keeps the ORIGINAL received-at (KDS timers do not restart)', () async {
      final KitchenTicketList first = await client.kitchen.tickets(branchId: 3);
      expect(adapter.requests.single.headers['If-None-Match'], isNull);
      expect(client.etags.length, 1);

      now = now.add(const Duration(minutes: 2));
      final KitchenTicketList second = await client.kitchen.tickets(
        branchId: 3,
      );
      expect(adapter.requests.last.headers['If-None-Match'], '"c1.a"');
      expect(client.etags.notModified, 1);
      expect(second.tickets.single.uuid, first.tickets.single.uuid);
      expect(second.receivedAt, first.receivedAt, reason: 'not "now"');
      expect(second.tickets.single.receivedAt, first.receivedAt);
      // The original Date header comes with it: the server-clock offset stays consistent with receivedAt
      expect(second.serverTime, first.serverTime);
    });

    test(
      'a changed body is a 200 with the new data (never the kept one)',
      () async {
        await client.kitchen.tickets(branchId: 3);
        etag = 'b';
        body = <String, Object?>{
          ...body,
          'data': <Object?>[
            ticketJson(uuid: 'bbbbbbbb-0000-4000-8000-000000000009'),
          ],
        };
        now = now.add(const Duration(seconds: 15));
        final KitchenTicketList next = await client.kitchen.tickets(
          branchId: 3,
        );
        expect(
          next.tickets.single.uuid,
          'bbbbbbbb-0000-4000-8000-000000000009',
        );
        expect(next.receivedAt, now);
        expect(client.etags.notModified, 0);
      },
    );

    test('another query or another actor never shares an entry', () async {
      await client.kitchen.tickets(branchId: 3);
      await client.kitchen.tickets(branchId: 3, includeReady: true);
      expect(adapter.requests.last.headers['If-None-Match'], isNull);
      await client.auth.signOutStaff();
      expect(client.etags.length, 0, reason: 'cleared on sign-out');
      await client.auth.signInStaff(userId: 5, pin: '4821');
      await client.kitchen.tickets(branchId: 3);
      expect(adapter.requests.last.headers['If-None-Match'], isNull);
    });

    test(
      'an unconditional capture sends no If-None-Match and stores the new 200',
      () async {
        await client.kitchen.tickets(branchId: 3);
        final CapturedRead<KitchenTicketList> fresh =
            await ReadTracking.capture(
              () => client.kitchen.tickets(branchId: 3),
              unconditional: true,
            );
        expect(adapter.requests.last.headers['If-None-Match'], isNull);
        expect(fresh.reads.reads.single.replayed, isFalse);
        expect(
          fresh.reads.revisionFor('branch:3'),
          BigInt.parse('9007199254740993'),
        );
        await client.kitchen.tickets(branchId: 3);
        expect(adapter.requests.last.headers['If-None-Match'], '"c1.a"');
      },
    );

    test('a refused read forgets the entry; a network error is still a network error', () async {
      await client.kitchen.tickets(branchId: 3);
      adapter.handler = (RequestOptions o) => refuse(503, 'Server Error');
      await expectLater(
        client.kitchen.tickets(branchId: 3),
        throwsA(isA<ApiException>()),
      );
      expect(client.etags.length, 0);
      adapter.handler = (RequestOptions o) =>
          throw DioException.connectionError(
            requestOptions: o,
            reason: 'offline',
          );
      await expectLater(
        client.kitchen.tickets(branchId: 3),
        throwsA(
          isA<ApiException>().having(
            (ApiException e) => e.isNetworkError,
            'network',
            isTrue,
          ),
        ),
      );
    });

    test('endpoints outside the allow-list are never conditional', () async {
      adapter.handler = (RequestOptions o) => ResponseBody.fromString(
        jsonEncode(<String, Object?>{
          'success': true,
          'data': <String, Object?>{},
        }),
        200,
        headers: <String, List<String>>{
          Headers.contentTypeHeader: <String>[Headers.jsonContentType],
          'etag': <String>['"x"'],
        },
      );
      await client.dio.get<Object?>('/v1/pos/sync/status');
      await client.dio.get<Object?>('/v1/pos/sync/status');
      expect(adapter.requests.last.headers['If-None-Match'], isNull);
      expect(client.etags.length, 0);
      expect(isConditionalGetPath('/api/v1/pos/floor'), isTrue);
      expect(
        isConditionalGetPath(
          '/api/v1/pos/orders/aaaaaaaa-0000-4000-8000-000000000001/bills',
        ),
        isTrue,
      );
      expect(isConditionalGetPath('/api/v1/pos/bills/x/qr'), isFalse);
      expect(isConditionalGetPath('/api/v1/pos/bill-print-requests'), isFalse);
      expect(isConditionalGetPath('/api/v1/pos/bootstrap'), isFalse);
    });

    test('a different base URL clears the store', () async {
      await client.kitchen.tickets(branchId: 3);
      expect(client.etags.length, 1);
      client.dio.options.baseUrl = 'https://other.test/api';
      await client.kitchen.tickets(branchId: 3);
      expect(adapter.requests.last.headers['If-None-Match'], isNull);
      expect(client.etags.length, 1);
    });
  });

  group('EtagStore', () {
    ConditionalGetEntry entry(String tag) => ConditionalGetEntry(
      etag: tag,
      data: null,
      headers: const <String, List<String>>{},
      statusCode: 200,
      receivedAt: DateTime.utc(2026),
    );

    test('bounded: the least recently used entry goes first', () {
      final EtagStore store = EtagStore(maxEntries: 3);
      store
        ..put('a', entry('1'))
        ..put('b', entry('2'))
        ..put('c', entry('3'));
      expect(store.lookup('a')?.etag, '1'); // a is now the most recent
      store.put('d', entry('4'));
      expect(store.keys, <String>['c', 'a', 'd']);
      expect(store.evicted, 1);
      store.clear();
      expect(store.length, 0);
    });

    test('the key sorts the query and includes the actor', () {
      RequestOptions o(String q, String token) => RequestOptions(
        path: '/v1/pos/orders?$q',
        baseUrl: 'https://api.test/api',
        headers: <String, Object?>{'Authorization': 'Bearer $token'},
      );
      expect(
        EtagStore.keyFor(o('a=1&b=2', 't')),
        EtagStore.keyFor(o('b=2&a=1', 't')),
      );
      expect(
        EtagStore.keyFor(o('a=1', 't')),
        isNot(EtagStore.keyFor(o('a=1', 'u'))),
      );
    });
  });

  group('ReadCapture.revisionFor', () {
    late FakeAdapter adapter;
    late Dio dio;

    ResponseBody answer(String? revision, {String scope = 'branch:3'}) =>
        ResponseBody.fromString(
          jsonEncode(<String, Object?>{'data': <String, Object?>{}}),
          200,
          headers: <String, List<String>>{
            Headers.contentTypeHeader: <String>[Headers.jsonContentType],
            'x-revision': ?(revision == null ? null : <String>[revision]),
            'x-revision-scope': ?(revision == null ? null : <String>[scope]),
          },
        );

    setUp(() {
      adapter = FakeAdapter((RequestOptions o) => answer('10'));
      dio = Dio(BaseOptions(baseUrl: 'https://api.test/api'))
        ..httpClientAdapter = adapter
        ..interceptors.add(ConditionalGetInterceptor(EtagStore()));
    });

    test('one single-scope read proves its token', () async {
      final CapturedRead<void> c = await ReadTracking.capture(
        () => dio.get<Object?>('/v1/pos/floor'),
      );
      expect(c.reads.revisionFor('branch:3'), BigInt.from(10));
      expect(c.reads.revisionFor('branch:4'), isNull);
    });

    test('several reads of the scope: the smallest token', () async {
      var n = 10;
      adapter.handler = (RequestOptions o) => answer('${n++}');
      final CapturedRead<void> c = await ReadTracking.capture(() async {
        await dio.get<Object?>('/v1/pos/floor');
        await dio.get<Object?>('/v1/pos/orders');
      });
      expect(c.reads.revisionFor('branch:3'), BigInt.from(10));
    });

    test('a read without a revision that started before proves nothing (mixed screen)', () async {
      adapter.handler = (RequestOptions o) =>
          o.uri.path.endsWith('status') ? answer(null) : answer('10');
      final CapturedRead<void> c = await ReadTracking.capture(() async {
        await Future.wait(<Future<Object?>>[
          dio.get<Object?>('/v1/pos/sync/status'),
          dio.get<Object?>('/v1/pos/floor'),
        ]);
      });
      expect(c.reads.revisionFor('branch:3'), isNull);
      // ... but one that started after the revision read completed is covered by it
      final CapturedRead<void> after = await ReadTracking.capture(() async {
        await dio.get<Object?>('/v1/pos/floor');
        await dio.get<Object?>('/v1/pos/sync/status');
      });
      expect(after.reads.revisionFor('branch:3'), BigInt.from(10));
    });

    test('a failed read proves nothing; no read proves nothing', () async {
      adapter.handler = (RequestOptions o) =>
          o.uri.path.endsWith('orders') ? refuse(500, 'x') : answer('10');
      final CapturedRead<void> c = await ReadTracking.capture(() async {
        await dio.get<Object?>('/v1/pos/floor');
        try {
          await dio.get<Object?>('/v1/pos/orders');
        } on DioException {
          // the screen shows its error
        }
      });
      expect(c.reads.failed, isTrue);
      expect(c.reads.revisionFor('branch:3'), isNull);
      final CapturedRead<void> none = await ReadTracking.capture(() async {});
      expect(none.reads.revisionFor('branch:3'), isNull);
    });

    test('a store-wide body (no X-Revision) gives no token', () async {
      adapter.handler = (RequestOptions o) => answer(null);
      final CapturedRead<void> c = await ReadTracking.capture(
        () => dio.get<Object?>('/v1/staff/orders'),
      );
      expect(c.reads.revisionFor('branch:3'), isNull);
    });
  });

  group('ChannelRevision', () {
    Map<String, Object?> rev(Object? token) => <String, Object?>{
      'rev': <String, Object?>{'pos-branch.5': token},
    };

    test('relevant events: skip only what a read proved; everything else reads and settles S', () {
      final ChannelRevision r = ChannelRevision('private-pos-branch.5');
      expect(r.channelKey, 'pos-branch.5');
      expect(r.scope, 'branch:5');
      // Nothing proven: read
      expect(r.event(rev('100')), RevisionVerdict.read);
      r.read(BigInt.from(100));
      expect(r.known, BigInt.from(100));
      expect(r.settled, BigInt.from(100));
      expect(r.event(rev('100')), RevisionVerdict.skip);
      expect(r.event(rev('101')), RevisionVerdict.read);
      expect(r.nextCount, 1);
      expect(r.settled, BigInt.from(101));
      expect(r.known, BigInt.from(100), reason: 'only a read moves R');
      // A gap: still a read (a full one), counted as such
      expect(r.event(rev('103')), RevisionVerdict.read);
      expect(r.rereadCount, 2);
      // No rev (an old server, a LAN event), another channel's rev only: read
      expect(r.event(const <String, Object?>{}), RevisionVerdict.read);
      expect(
        r.event(<String, Object?>{
          'rev': <String, Object?>{'store.3': '100'},
        }),
        RevisionVerdict.read,
      );
    });

    test('tokens beyond 2^53 compare exactly', () {
      final ChannelRevision r = ChannelRevision('private-pos-branch.5')
        ..read(BigInt.parse('9007199254740993'));
      expect(r.event(rev('9007199254740993')), RevisionVerdict.skip);
      expect(r.event(rev('9007199254740994')), RevisionVerdict.read);
      expect(r.nextCount, 1);
      expect(parseRevision('12a'), isNull);
      expect(parseRevision('-1'), isNull);
    });

    test('heartbeat: missed when its rev is not S; never adopts it', () {
      final ChannelRevision r = ChannelRevision('private-pos-branch.5');
      expect(
        r.heartbeat(rev('100')),
        HeartbeatVerdict.fine,
        reason: 'nothing known',
      );
      r.read(BigInt.from(100));
      expect(r.heartbeat(rev('100')), HeartbeatVerdict.fine);
      expect(r.heartbeat(rev('101')), HeartbeatVerdict.missed);
      expect(r.settled, BigInt.from(100));
      expect(r.heartbeatRereads, 1);
    });

    test('scopes of the channels', () {
      expect(ChannelRevision.scopeOfChannel('branch.2'), 'branch:2');
      // Untracked: touchBranch does not move the store token, a courier list is store-wide
      expect(ChannelRevision.scopeOfChannel('store.3'), isNull);
      expect(ChannelRevision.scopeOfChannel('courier.9'), isNull);
      final ChannelRevision store = ChannelRevision('private-store.3')
        ..read(BigInt.from(7));
      expect(store.known, isNull);
      expect(
        store.event(<String, Object?>{
          'rev': <String, Object?>{'store.3': '7'},
        }),
        RevisionVerdict.read,
        reason: 'a same store token never skips a re-read',
      );
      expect(ChannelRevision.scopeOfChannel('user.9'), 'user:9');
      expect(ChannelRevision.scopeOfChannel('menu.3'), isNull);
    });
  });

  group('RealtimeConfig', () {
    test('reads GET /v1/realtime and builds the socket address from it when no key is compiled in', () async {
      final FakeAdapter adapter = FakeAdapter(
        (RequestOptions o) => ok(<String, Object?>{
          'status': 'degraded',
          'live': false,
          'heartbeat_seconds': 30,
          'silence_seconds': 90,
          'poll_seconds': <String, Object?>{'live': 60, 'fallback': 15},
          'reverb': <String, Object?>{
            'key': 'server-key',
            'host': 'ws.example.uz',
            'port': 443,
            'scheme': 'https',
          },
        }),
      );
      final Dio dio = Dio(BaseOptions(baseUrl: 'https://api.example.uz/api'))
        ..httpClientAdapter = adapter;
      final RealtimeConfig cfg = (await RealtimeConfig.fetch(dio))!;
      expect(adapter.requests.single.uri.path, '/api/v1/realtime');
      expect(
        (cfg.live, cfg.silenceSeconds, cfg.pollFallbackSeconds),
        (false, 90, 15),
      );
      final Uri uri = RealtimeConfig.socketUri(
        apiBaseUrl: 'https://api.example.uz/api',
        appKey: '',
        config: cfg,
      )!;
      expect(
        uri.toString(),
        startsWith('wss://ws.example.uz:443/app/server-key?'),
      );
      // A compiled key wins
      expect(
        RealtimeConfig.socketUri(
          apiBaseUrl: 'https://api.example.uz/api',
          appKey: 'built-in',
          config: cfg,
        )!.path,
        '/app/built-in',
      );
      // A null host: the API's own host
      final Uri own = RealtimeConfig.socketUri(
        apiBaseUrl: 'https://api.example.uz/api',
        appKey: '',
        config: RealtimeConfig.fromJson(<String, Object?>{
          'reverb': <String, Object?>{'key': 'k'},
        }),
      )!;
      expect(
        (own.scheme, own.host, own.path),
        ('wss', 'api.example.uz', '/app/k'),
      );
      // Real-time off on the server, no compiled key: nothing to connect to
      expect(
        RealtimeConfig.socketUri(
          apiBaseUrl: 'https://api.example.uz/api',
          appKey: '',
          config: RealtimeConfig.fromJson(const <String, Object?>{}),
        ),
        isNull,
      );
    });

    test('an unreachable or old server gives null', () async {
      final Dio dio = Dio(BaseOptions(baseUrl: 'https://api.test/api'))
        ..httpClientAdapter = FakeAdapter(
          (RequestOptions o) => refuse(404, 'x'),
        );
      expect(await RealtimeConfig.fetch(dio), isNull);
    });
  });

  group('Backoff.reconnect', () {
    test(
      'the first reconnect is spread over 0-10 s, later ones keep the 30 s cap',
      () {
        final Random random = Random(42);
        final Backoff b = Backoff(
          firstSpread: const Duration(seconds: 10),
          random: random,
        );
        final List<int> firsts = <int>[
          for (var i = 0; i < 500; i++) b.delay(0).inMilliseconds,
        ];
        expect(firsts.every((int ms) => ms >= 0 && ms <= 10000), isTrue);
        // Spread, not bunched: every 2-second slice of 0-10 s gets some devices
        for (var slice = 0; slice < 5; slice++) {
          expect(
            firsts.where((int ms) => ms ~/ 2000 == slice).length,
            greaterThan(50),
          );
        }
        expect(b.delay(1).inMilliseconds, inInclusiveRange(1600, 2400));
        expect(b.delay(10), lessThanOrEqualTo(const Duration(seconds: 30)));
        expect(Backoff.reconnect.firstSpread, const Duration(seconds: 10));
        // Without a spread: as before (1 s +-20 %)
        expect(
          const Backoff().delay(0).inMilliseconds,
          inInclusiveRange(800, 1200),
        );
      },
    );
  });

  group('LiveChannel', () {
    late FakeRealtimeServer server;
    late RealtimeConfig? config;
    late int configReads;

    LiveChannel build({bool waitForConfig = false}) {
      server = FakeRealtimeServer();
      configReads = 0;
      final PusherClient client = PusherClient(
        uri: Uri.parse('ws://127.0.0.1/app/compiled'),
        connector: server.connect,
        authorize: (String socketId, String channel) async => 'sig',
        backoff: const Backoff(jitter: 0),
      );
      return LiveChannel(
        client: client,
        channel: 'private-pos-branch.5',
        loadConfig: () async {
          configReads++;
          return config;
        },
        uriFor: (RealtimeConfig? c) => RealtimeConfig.socketUri(
          apiBaseUrl: 'http://127.0.0.1/api',
          appKey: waitForConfig ? '' : 'compiled',
          config: c,
        ),
        waitForConfig: waitForConfig,
      );
    }

    void subscribe(FakeAsync async) {
      // A long activity timeout: these tests do not answer pings
      server.last.establish(activityTimeout: 3600);
      async.flushMicrotasks();
      server.last.push(
        'pusher_internal:subscription_succeeded',
        channel: 'private-pos-branch.5',
      );
      async.flushMicrotasks();
    }

    Map<String, Object?> cfg({bool live = true, int silence = 90}) =>
        <String, Object?>{
          'live': live,
          'heartbeat_seconds': 30,
          'silence_seconds': silence,
          'reverb': <String, Object?>{'key': 'server-key'},
        };

    test('silence for silence_seconds marks it degraded; a heartbeat brings it back', () {
      fakeAsync((FakeAsync async) {
        config = RealtimeConfig.fromJson(cfg());
        final LiveChannel ch = build();
        final List<RealtimeEvent> heartbeats = <RealtimeEvent>[];
        final List<RealtimeEvent> events = <RealtimeEvent>[];
        ch.heartbeats.listen(heartbeats.add);
        ch.events.listen(events.add);
        ch.start();
        async.flushMicrotasks();
        subscribe(async);
        expect(ch.isLive, isTrue);
        async.elapse(const Duration(seconds: 89));
        expect(ch.isLive, isTrue);
        async.elapse(const Duration(seconds: 2));
        expect(ch.degraded, isTrue);
        expect(ch.isLive, isFalse);
        server.last.push(
          'realtime.heartbeat',
          channel: 'private-pos-branch.5',
          data: <String, Object?>{
            'rev': <String, Object?>{'pos-branch.5': '1'},
          },
        );
        async.flushMicrotasks();
        expect(ch.isLive, isTrue);
        expect(heartbeats, hasLength(1));
        expect(events, isEmpty, reason: 'a heartbeat is not a screen event');
        // An event also counts as a sign of life
        async.elapse(const Duration(seconds: 80));
        server.last.push(
          'order.created',
          channel: 'private-pos-branch.5',
          data: const <String, Object?>{},
        );
        async.flushMicrotasks();
        async.elapse(const Duration(seconds: 80));
        expect(ch.isLive, isTrue);
        expect(events, hasLength(1));
        unawaited(ch.dispose());
        async.flushMicrotasks();
      });
    });

    test('a heartbeat whose data is JSON encoded twice (the backend\'s triggerBatch) still gives its rev', () {
      fakeAsync((FakeAsync async) {
        config = RealtimeConfig.fromJson(cfg());
        final LiveChannel ch = build();
        final List<RealtimeEvent> heartbeats = <RealtimeEvent>[];
        ch.heartbeats.listen(heartbeats.add);
        ch.start();
        async.flushMicrotasks();
        subscribe(async);
        server.last.push(
          'realtime.heartbeat',
          channel: 'private-pos-branch.5',
          data: jsonEncode(
            jsonEncode(<String, Object?>{
              'at': '2026-11-16T18:00:00Z',
              'interval': 30,
              'rev': <String, Object?>{'pos-branch.5': '4711'},
            }),
          ),
        );
        async.flushMicrotasks();
        expect(
          ChannelRevision.revisionIn(heartbeats.single.data, 'pos-branch.5'),
          BigInt.from(4711),
        );
        unawaited(ch.dispose());
        async.flushMicrotasks();
      });
    });

    test('live:false from the server: degraded (fast polling) until a heartbeat arrives', () {
      fakeAsync((FakeAsync async) {
        config = RealtimeConfig.fromJson(cfg(live: false));
        final LiveChannel ch = build();
        ch.start();
        async.flushMicrotasks();
        subscribe(async);
        expect(ch.subscribed, isTrue);
        expect(ch.degraded, isTrue);
        server.last.push(
          'realtime.heartbeat',
          channel: 'private-pos-branch.5',
          data: const <String, Object?>{},
        );
        async.flushMicrotasks();
        expect(ch.isLive, isTrue);
        unawaited(ch.dispose());
        async.flushMicrotasks();
      });
    });

    test('an unknown config (old server, offline at start): no silence detection, live when subscribed', () {
      fakeAsync((FakeAsync async) {
        config = null;
        final LiveChannel ch = build();
        ch.start();
        async.flushMicrotasks();
        subscribe(async);
        async.elapse(const Duration(minutes: 10));
        expect(ch.isLive, isTrue);
        unawaited(ch.dispose());
        async.flushMicrotasks();
      });
    });

    test('no compiled key: connects with the server key; re-reads the config on reconnect', () {
      fakeAsync((FakeAsync async) {
        config = null;
        final LiveChannel ch = build(waitForConfig: true);
        ch.start();
        async.elapse(const Duration(seconds: 1));
        expect(server.sockets, isEmpty, reason: 'no key yet');
        expect(ch.waitingForKey, isTrue);
        config = RealtimeConfig.fromJson(cfg());
        async.elapse(const Duration(seconds: 60));
        expect(server.sockets, hasLength(1));
        expect(server.uris.single.path, '/app/server-key');
        subscribe(async);
        expect(ch.isLive, isTrue);
        async.elapse(const Duration(seconds: 10));
        final int before = configReads;
        server.last.drop();
        async.elapse(const Duration(seconds: 2));
        expect(configReads, before + 1, reason: 'read again on reconnect');
        unawaited(ch.dispose());
        async.flushMicrotasks();
      });
    });
  });
}
