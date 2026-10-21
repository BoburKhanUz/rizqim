import 'dart:async';
import 'dart:io';

import 'package:fake_async/fake_async.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/fake_socket.dart';

const String channel = 'private-pos-branch.3';

void main() {
  late FakeRealtimeServer server;
  late List<String> authorized;
  late Object? authError;

  PusherClient build() => PusherClient(
    uri: realtimeUri(apiBaseUrl: 'https://api.test/api', appKey: 'key'),
    headers: <String, String>{'Origin': originOf('https://api.test/api')},
    connector: server.connect,
    authorize: (String socketId, String name) async {
      if (authError != null) throw authError!;
      authorized.add('$socketId|$name');
      return 'key:signature';
    },
    backoff: const Backoff(jitter: 0),
  );

  setUp(() {
    server = FakeRealtimeServer();
    authorized = <String>[];
    authError = null;
  });

  test('realtimeUri and origin come from the API base URL', () {
    expect(
      realtimeUri(
        apiBaseUrl: 'https://rizqim.uz/api',
        appKey: 'abc',
      ).toString(),
      'wss://rizqim.uz/app/abc?protocol=7&client=rizqim-dart&version=1.0&flash=false',
    );
    expect(
      realtimeUri(
        apiBaseUrl: 'https://rizqim.uz/api',
        appKey: 'abc',
        override: 'http://127.0.0.1:8080',
      ).toString(),
      startsWith('ws://127.0.0.1:8080/app/abc?'),
    );
    expect(originOf('https://rizqim.uz/api'), 'https://rizqim.uz');
  });

  test('connects, signs the private channel and delivers its events', () {
    fakeAsync((FakeAsync async) {
      final PusherClient client = build();
      final List<RealtimeEvent> events = <RealtimeEvent>[];
      final List<RealtimeStatus> statuses = <RealtimeStatus>[];
      client.events.listen(events.add);
      client.statuses.listen(statuses.add);

      client.subscribe(channel);
      client.connect();
      async.flushMicrotasks();
      expect(server.headers.single['Origin'], 'https://api.test');
      expect(client.status, RealtimeStatus.connecting);

      server.last.establish();
      async.flushMicrotasks();
      expect(client.status, RealtimeStatus.connected);
      expect(authorized, <String>['123.456|$channel']);
      expect(
        server.last.sentEvents('pusher:subscribe').single['data'],
        <String, Object?>{'channel': channel, 'auth': 'key:signature'},
      );

      server.last.push(
        'pusher_internal:subscription_succeeded',
        channel: channel,
      );
      server.last.push(
        'order.created',
        channel: channel,
        data: <String, Object?>{
          'order': <String, Object?>{'uuid': 'u1', 'status': 'accepted'},
        },
      );
      server.last.push(
        'order.created',
        channel: 'private-other',
        data: <String, Object?>{},
      );
      async.flushMicrotasks();

      expect(client.subscribedChannels, <String>{channel});
      expect(events, hasLength(1));
      expect(events.single.event, 'order.created');
      expect(
        (events.single.data['order']! as Map<String, Object?>)['uuid'],
        'u1',
      );
      expect(statuses, <RealtimeStatus>[
        RealtimeStatus.connecting,
        RealtimeStatus.connected,
      ]);
      unawaited(client.dispose());
      async.flushMicrotasks();
    });
  });

  test('answers the server ping', () {
    fakeAsync((FakeAsync async) {
      final PusherClient client = build()..connect();
      async.flushMicrotasks();
      server.last
        ..establish()
        ..push('pusher:ping');
      async.flushMicrotasks();
      expect(server.last.sentEvents('pusher:pong'), hasLength(1));
      unawaited(client.dispose());
      async.flushMicrotasks();
    });
  });

  test('reconnects with growing backoff after drops and refused connections, then resubscribes', () {
    fakeAsync((FakeAsync async) {
      final PusherClient client = build();
      final List<RealtimeStatus> statuses = <RealtimeStatus>[];
      client.statuses.listen(statuses.add);
      client
        ..subscribe(channel)
        ..connect();
      async.flushMicrotasks();
      server.last.establish();
      async.flushMicrotasks();
      expect(client.connectAttempts, 1);

      // the server goes away
      server.down = true;
      server.last.drop();
      async.flushMicrotasks();
      expect(client.status, RealtimeStatus.reconnecting);

      async.elapse(const Duration(milliseconds: 999));
      expect(client.connectAttempts, 1, reason: 'first retry after 1 s');
      async.elapse(const Duration(milliseconds: 1));
      expect(client.connectAttempts, 2);

      async.elapse(const Duration(seconds: 2)); // 2nd retry after 2 s
      expect(client.connectAttempts, 3);
      async.elapse(const Duration(seconds: 3));
      expect(client.connectAttempts, 3, reason: '3rd retry waits 4 s');
      async.elapse(const Duration(seconds: 1));
      expect(client.connectAttempts, 4);

      // back up: the next attempt connects and subscribes again
      server.down = false;
      async.elapse(const Duration(seconds: 8));
      expect(client.connectAttempts, 5);
      server.last.establish(socketId: '999.1');
      async.flushMicrotasks();
      expect(client.status, RealtimeStatus.connected);
      expect(authorized.last, '999.1|$channel');
      expect(statuses.last, RealtimeStatus.connected);
      expect(
        statuses.where((RealtimeStatus s) => s == RealtimeStatus.connected),
        hasLength(2),
      );

      // the backoff starts from 1 s again after a good connection
      server.last.drop();
      async.flushMicrotasks();
      async.elapse(const Duration(seconds: 1));
      expect(client.connectAttempts, 6);
      unawaited(client.dispose());
      async.flushMicrotasks();
    });
  });

  test('the backoff is capped at 30 s', () {
    const Backoff b = Backoff(jitter: 0);
    expect(b.delay(0), const Duration(seconds: 1));
    expect(b.delay(4), const Duration(seconds: 16));
    expect(b.delay(5), const Duration(seconds: 30));
    expect(b.delay(50), const Duration(seconds: 30));
    final Duration jittered = const Backoff().delay(3);
    expect(jittered.inMilliseconds, inInclusiveRange(6400, 9600));
  });

  test('a silent connection is pinged and dropped when no pong comes', () {
    fakeAsync((FakeAsync async) {
      final PusherClient client = build()..connect();
      async.flushMicrotasks();
      server.last.establish(activityTimeout: 30);
      async.flushMicrotasks();

      async.elapse(const Duration(seconds: 30));
      expect(server.last.sentEvents('pusher:ping'), hasLength(1));
      // a pong keeps it alive
      server.last.push('pusher:pong');
      async.flushMicrotasks();
      async.elapse(const Duration(seconds: 29));
      expect(server.sockets, hasLength(1));

      // the next ping goes unanswered
      async.elapse(const Duration(seconds: 1));
      expect(server.last.sentEvents('pusher:ping'), hasLength(2));
      async.elapse(const Duration(seconds: 30));
      expect(server.sockets.first.closed, isTrue);
      expect(client.status, RealtimeStatus.reconnecting);
      async.elapse(const Duration(seconds: 1));
      expect(server.sockets, hasLength(2));
      unawaited(client.dispose());
      async.flushMicrotasks();
    });
  });

  test('a failed signature is retried with backoff without dropping the connection', () {
    fakeAsync((FakeAsync async) {
      authError = const ApiException(
        message: 'Ruxsat etilmagan',
        statusCode: 403,
      );
      final List<Object> errors = <Object>[];
      final PusherClient client = build();
      client.errors.listen(errors.add);
      client
        ..subscribe(channel)
        ..connect();
      async.flushMicrotasks();
      server.last.establish();
      async.flushMicrotasks();
      expect(errors.single, isA<RealtimeSubscriptionError>());
      expect(server.last.sentEvents('pusher:subscribe'), isEmpty);

      authError = null;
      async.elapse(const Duration(seconds: 1));
      expect(server.last.sentEvents('pusher:subscribe'), hasLength(1));
      expect(server.sockets, hasLength(1));
      unawaited(client.dispose());
      async.flushMicrotasks();
    });
  });

  test('pusher:error closes and reconnects; disconnect stops for good', () {
    fakeAsync((FakeAsync async) {
      final PusherClient client = build()..connect();
      async.flushMicrotasks();
      server.last
        ..establish()
        ..push(
          'pusher:error',
          data: <String, Object?>{
            'code': 4201,
            'message': 'Pong reply not received',
          },
        );
      async.flushMicrotasks();
      expect(client.status, RealtimeStatus.reconnecting);
      async.elapse(const Duration(seconds: 1));
      expect(server.sockets, hasLength(2));

      unawaited(client.disconnect());
      async.flushMicrotasks();
      expect(client.status, RealtimeStatus.idle);
      async.elapse(const Duration(minutes: 5));
      expect(server.sockets, hasLength(2));
      unawaited(client.dispose());
      async.flushMicrotasks();
    });
  });

  test('unsubscribe tells the server', () {
    fakeAsync((FakeAsync async) {
      final PusherClient client = build()
        ..subscribe(channel)
        ..connect();
      async.flushMicrotasks();
      server.last.establish();
      async.flushMicrotasks();
      server.last.push(
        'pusher_internal:subscription_succeeded',
        channel: channel,
      );
      async.flushMicrotasks();
      client.unsubscribe(channel);
      expect(
        server.last.sentEvents('pusher:unsubscribe').single['data'],
        <String, Object?>{'channel': channel},
      );
      unawaited(client.dispose());
      async.flushMicrotasks();
    });
  });

  group('subscription state', () {
    test('connected is not live: the channel is pending until the server confirms it', () {
      fakeAsync((FakeAsync async) {
        final PusherClient client = build();
        final List<RealtimeChannelState> states = <RealtimeChannelState>[];
        client.channelStates.listen(states.add);
        client
          ..subscribe(channel)
          ..connect();
        async.flushMicrotasks();
        server.last.establish();
        async.flushMicrotasks();

        expect(client.status, RealtimeStatus.connected);
        expect(client.channelState(channel), RealtimeSubscriptionState.pending);
        expect(client.isSubscribed(channel), isFalse);
        expect(states, isEmpty);

        // the confirmation comes late
        async.elapse(const Duration(seconds: 5));
        server.last.push(
          'pusher_internal:subscription_succeeded',
          channel: channel,
        );
        async.flushMicrotasks();
        expect(client.isSubscribed(channel), isTrue);
        expect(states.single.state, RealtimeSubscriptionState.subscribed);

        // a dropped connection makes it pending again
        server.last.drop();
        async.flushMicrotasks();
        expect(client.isSubscribed(channel), isFalse);
        expect(states.last.state, RealtimeSubscriptionState.pending);
        expect(client.channelState('private-unknown'), isNull);
        unawaited(client.dispose());
        async.flushMicrotasks();
      });
    });

    for (final int code in <int>[403, 429]) {
      test(
        'a $code from /broadcasting/auth marks the channel failed and keeps retrying; success makes it live',
        () {
          fakeAsync((FakeAsync async) {
            authError = ApiException(message: 'rad etildi', statusCode: code);
            final PusherClient client = build();
            final List<RealtimeChannelState> states = <RealtimeChannelState>[];
            client.channelStates.listen(states.add);
            client
              ..subscribe(channel)
              ..connect();
            async.flushMicrotasks();
            server.last.establish();
            async.flushMicrotasks();

            expect(client.status, RealtimeStatus.connected);
            expect(
              client.channelState(channel),
              RealtimeSubscriptionState.failed,
            );
            expect(client.isSubscribed(channel), isFalse);
            expect((states.single.error! as ApiException).statusCode, code);

            // still refused after the first retry: stays failed, no subscribe was sent
            async.elapse(const Duration(seconds: 1));
            expect(
              client.channelState(channel),
              RealtimeSubscriptionState.failed,
            );
            expect(server.last.sentEvents('pusher:subscribe'), isEmpty);

            authError = null;
            async.elapse(const Duration(seconds: 2));
            expect(server.last.sentEvents('pusher:subscribe'), hasLength(1));
            expect(
              client.channelState(channel),
              RealtimeSubscriptionState.failed,
              reason: 'not live until the server confirms',
            );
            server.last.push(
              'pusher_internal:subscription_succeeded',
              channel: channel,
            );
            async.flushMicrotasks();
            expect(client.isSubscribed(channel), isTrue);
            expect(states.last.state, RealtimeSubscriptionState.subscribed);
            unawaited(client.dispose());
            async.flushMicrotasks();
          });
        },
      );
    }

    test('pusher:subscription_error marks the channel failed and retries', () {
      fakeAsync((FakeAsync async) {
        final PusherClient client = build()
          ..subscribe(channel)
          ..connect();
        async.flushMicrotasks();
        server.last.establish();
        async.flushMicrotasks();
        server.last.push(
          'pusher:subscription_error',
          channel: channel,
          data: <String, Object?>{'status': 403},
        );
        async.flushMicrotasks();
        expect(client.channelState(channel), RealtimeSubscriptionState.failed);
        async.elapse(const Duration(seconds: 1));
        expect(server.last.sentEvents('pusher:subscribe'), hasLength(2));
        unawaited(client.dispose());
        async.flushMicrotasks();
      });
    });
  });

  group('connect timeout', () {
    test('a connection that opens after the timeout is closed, not leaked', () {
      fakeAsync((FakeAsync async) {
        final List<Completer<RealtimeSocket>> stalled =
            <Completer<RealtimeSocket>>[];
        final PusherClient client = PusherClient(
          uri: Uri.parse('ws://stall.test/app/key'),
          connector: (Uri uri, Map<String, String> headers) {
            final Completer<RealtimeSocket> c = Completer<RealtimeSocket>();
            stalled.add(c);
            return c.future;
          },
          authorize: (String socketId, String name) async => 'key:sig',
          backoff: const Backoff(jitter: 0),
          connectTimeout: const Duration(seconds: 5),
        )..connect();
        async.flushMicrotasks();
        expect(stalled, hasLength(1));

        async.elapse(const Duration(seconds: 5));
        expect(client.status, RealtimeStatus.reconnecting);
        // the first attempt finally opens: it is closed at once
        final FakeSocket late = FakeSocket();
        stalled.first.complete(late);
        async.flushMicrotasks();
        expect(late.closed, isTrue);

        // the retry connects normally
        async.elapse(const Duration(seconds: 1));
        expect(stalled, hasLength(2));
        final FakeSocket good = FakeSocket();
        stalled.last.complete(good);
        async.flushMicrotasks();
        good.establish();
        async.flushMicrotasks();
        expect(client.status, RealtimeStatus.connected);
        expect(good.closed, isFalse);

        unawaited(client.dispose());
        async.flushMicrotasks();
        expect(good.closed, isTrue);
      });
    });

    test('dispose during a pending connect closes the late connection and leaves no timers', () {
      fakeAsync((FakeAsync async) {
        final Completer<RealtimeSocket> stalled = Completer<RealtimeSocket>();
        final PusherClient client = PusherClient(
          uri: Uri.parse('ws://stall.test/app/key'),
          connector: (Uri uri, Map<String, String> headers) => stalled.future,
          authorize: (String socketId, String name) async => 'key:sig',
          backoff: const Backoff(jitter: 0),
        )..connect();
        async.flushMicrotasks();
        unawaited(client.dispose());
        async.flushMicrotasks();
        expect(client.status, RealtimeStatus.idle);
        expect(async.pendingTimers, isEmpty);

        final FakeSocket late = FakeSocket();
        stalled.complete(late);
        async.flushMicrotasks();
        expect(late.closed, isTrue);
        async.elapse(const Duration(minutes: 5));
        expect(client.connectAttempts, 1);
      });
    });

    test('against a real server that accepts TCP but never answers the handshake, no socket stays open', () async {
      final ServerSocket stall = await ServerSocket.bind(
        InternetAddress.loopbackIPv4,
        0,
      );
      final List<Socket> accepted = <Socket>[];
      final List<Future<void>> closedByClient = <Future<void>>[];
      stall.listen((Socket s) {
        accepted.add(s);
        final Completer<void> closed = Completer<void>();
        closedByClient.add(closed.future);
        // read and ignore the upgrade request; never answer
        s.listen(
          (_) {},
          onDone: () => closed.isCompleted ? null : closed.complete(),
          onError: (Object _) => closed.isCompleted ? null : closed.complete(),
        );
      });
      final Uri uri = Uri.parse(
        'ws://127.0.0.1:${stall.port}/app/key?protocol=7',
      );

      // the connector gives up after its timeout and aborts the request
      await expectLater(
        ioRealtimeConnectorWith(timeout: const Duration(milliseconds: 300))(
          uri,
          const <String, String>{},
        ),
        throwsA(isA<TimeoutException>()),
      );

      // the client retries with the same deadline; after dispose nothing is left open
      final PusherClient client = PusherClient(
        uri: uri,
        connector: ioRealtimeConnectorWith(
          timeout: const Duration(milliseconds: 300),
        ),
        authorize: (String socketId, String name) async => 'key:sig',
        backoff: const Backoff(initial: Duration(milliseconds: 50), jitter: 0),
        connectTimeout: const Duration(seconds: 2),
      )..connect();
      await Future<void>.delayed(const Duration(milliseconds: 900));
      await client.dispose();
      expect(accepted.length, greaterThanOrEqualTo(2));

      await Future.wait(closedByClient).timeout(const Duration(seconds: 5));
      await stall.close();
    });
  });
}
