import 'dart:async';

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
}
