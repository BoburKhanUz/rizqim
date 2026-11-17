import 'dart:async';

import 'package:fake_async/fake_async.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// Wave M2 in the POS data layer: conditional GETs through the repositories, the revision-aware scheduler, heartbeat
/// and silence, the unconditional safety poll, app resume.
void main() {
  group('conditional GETs through PosHttp', () {
    late Harness h;

    setUp(() async {
      h = Harness();
      await h.signIn();
      h.adapter.requests.clear();
    });

    test(
      'the second floor read is a 304 on the wire and returns the same floor',
      () async {
        final PosFloor first = await h.pos.floor.load();
        final PosFloor second = await h.pos.floor.load();
        expect(h.state.notModified, 1);
        final List<String?> sent = <String?>[
          for (final r in h.adapter.to('GET', '/v1/pos/floor'))
            r.headers['If-None-Match'] as String?,
        ];
        expect(sent.first, isNull);
        expect(sent.last, isNotNull);
        expect(second.halls.length, first.halls.length);
        expect(h.pos.connectivity.state.online, isTrue);
      },
    );

    test('a change is a 200 with the new data, never the kept body', () async {
      final PosFloor before = await h.pos.floor.load();
      await h.pos.orders.open(
        channel: PosChannel.hall,
        tableId: 1,
        guestsCount: 2,
      );
      final PosFloor after = await h.pos.floor.load();
      expect(h.state.notModified, 0);
      expect(after.orders.length, before.orders.length + 1);
    });

    test(
      'a 304 keeps the moment of the original 200 (the waiter\'s loadedAt)',
      () async {
        final DateTime t0 = h.now;
        final CapturedRead<PosFloor> a = await ReadTracking.capture(
          () => h.pos.floor.load(),
        );
        expect(a.reads.receivedAt(h.now), t0);
        final CapturedRead<PosFloor> b = await ReadTracking.capture(
          () => h.pos.floor.load(),
        );
        expect(b.reads.anyReplayed, isTrue);
        // The screen asks 40 s later by its own clock: the body still counts from its original 200
        expect(
          b.reads.receivedAt(t0.add(const Duration(seconds: 40))),
          t0,
          reason: 'not reset to now',
        );
      },
    );

    test('offline: a network error, the offline state shows; nothing is answered from the store', () async {
      await h.pos.floor.load();
      h.adapter.offline = true;
      await expectLater(
        h.pos.floor.load(),
        throwsA(isA<PosNetworkException>()),
      );
      expect(h.pos.connectivity.state.online, isFalse);
      h.adapter.offline = false;
      await h.pos.floor.load();
      expect(h.pos.connectivity.state.online, isTrue);
      expect(h.state.notModified, 1);
    });

    test('signing the staff out clears the store', () async {
      await h.pos.floor.load();
      expect(h.client.etags.length, greaterThan(0));
      await h.client.auth.signOutStaff();
      expect(h.client.etags.length, 0);
    });
  });

  group('the revision-aware scheduler', () {
    late Harness h;
    late FakePosRealtimeHub hub;
    late PosRealtime rt;
    late PosRefreshScheduler s;
    late List<PosRefreshReason> reasons;
    late StreamController<void> resumed;

    /// A live till floor screen: the scheduler re-reads `/pos/floor` on order events.
    void open(FakeAsync async, {bool Function(PosEvent e)? filter}) {
      h = Harness();
      hub = FakePosRealtimeHub();
      h.state.onEvent = hub.broadcast;
      unawaited(h.signIn());
      async.elapse(const Duration(seconds: 1));
      rt = PosRealtime.create(
        client: h.client,
        terminal: h.client.auth.terminal!,
        apiBaseUrl: 'https://api.test/api',
        appKey: FakePosState.realtimeKey,
        connector: hub.connect,
      )!;
      reasons = <PosRefreshReason>[];
      resumed = StreamController<void>.broadcast(sync: true);
      s = PosRefreshScheduler(
        realtime: rt,
        resumed: resumed.stream,
        eventFilter: filter ?? (PosEvent e) => e is PosOrderEvent,
        onRefresh: (PosRefreshReason r) async {
          reasons.add(r);
          await h.pos.floor.load();
        },
      )..start();
      rt.start();
      async.elapse(const Duration(milliseconds: 50));
      expect(reasons, <PosRefreshReason>[PosRefreshReason.resync]);
      expect(s.revision!.known, BigInt.from(h.state.revision));
      reasons.clear();
    }

    void close(FakeAsync async) {
      unawaited(s.dispose());
      unawaited(rt.dispose());
      unawaited(resumed.close());
      async.flushMicrotasks();
    }

    Map<String, Object?> order(String uuid) => <String, Object?>{
      'order': <String, Object?>{'uuid': uuid, 'status': 'accepted'},
    };

    test('next (N+1): re-read and N+1; same (N): nothing; the read proves the new N', () {
      fakeAsync((FakeAsync async) {
        open(async);
        h.state.emit('order.created', order('o1')); // N+1
        async.elapse(const Duration(seconds: 1));
        expect(reasons, <PosRefreshReason>[PosRefreshReason.event]);
        final BigInt n = s.revision!.known!;
        expect(n, BigInt.from(h.state.revision));
        // The same token again (a second event of the same commit): already read
        hub.broadcast('order.status_changed', <String, Object?>{
          ...order('o1'),
          'rev': h.state.revMap,
        });
        async.elapse(const Duration(seconds: 1));
        expect(reasons, hasLength(1));
        expect(s.skippedEvents, 1);
        close(async);
      });
    });

    test('a gap (an event lost in between) is a full re-read', () {
      fakeAsync((FakeAsync async) {
        open(async);
        h.state.revision += 1; // a change whose event never arrived
        h.state.emit('order.created', order('o2')); // N+2
        async.elapse(const Duration(seconds: 1));
        expect(reasons, <PosRefreshReason>[PosRefreshReason.event]);
        expect(s.revision!.rereadCount, 1);
        expect(s.revision!.known, BigInt.from(h.state.revision));
        close(async);
      });
    });

    test('an event this screen ignores does not move N: the relevant event of the same commit still re-reads', () {
      fakeAsync((FakeAsync async) {
        open(async);
        // One commit, two events with the same rev: the first is filtered out here
        h.state.revision++;
        hub.broadcast('booking.changed', <String, Object?>{
          'booking': <String, Object?>{'uuid': 'b', 'status': 'confirmed'},
          'rev': h.state.revMap,
        });
        hub.broadcast('order.created', <String, Object?>{
          ...order('o3'),
          'rev': h.state.revMap,
        });
        async.elapse(const Duration(seconds: 1));
        expect(reasons, <PosRefreshReason>[PosRefreshReason.event]);
        expect(s.skippedEvents, 0);
        close(async);
      });
    });

    test('a failed refresh forgets N: the next event with the same token still re-reads', () {
      fakeAsync((FakeAsync async) {
        open(async);
        h.adapter.offline = true;
        unawaited(s.refreshNow());
        async.elapse(const Duration(seconds: 1));
        expect(s.revision!.known, isNull);
        h.adapter.offline = false;
        hub.broadcast('order.created', <String, Object?>{
          ...order('o4'),
          'rev': h.state.revMap,
        });
        async.elapse(const Duration(seconds: 1));
        expect(reasons.last, PosRefreshReason.event);
        expect(s.skippedEvents, 0);
        close(async);
      });
    });

    test(
      'a 304 refresh keeps N (the kept body\'s X-Revision is still current)',
      () {
        fakeAsync((FakeAsync async) {
          open(async);
          final BigInt? n = s.revision!.known;
          unawaited(s.refreshNow());
          async.elapse(const Duration(seconds: 1));
          expect(h.state.notModified, greaterThan(0));
          expect(s.revision!.known, n);
          close(async);
        });
      },
    );

    test(
      'heartbeat: same token nothing, another token (a missed event) re-reads',
      () {
        fakeAsync((FakeAsync async) {
          open(async);
          hub.heartbeat(h.state.heartbeatBody());
          async.elapse(const Duration(seconds: 1));
          expect(reasons, isEmpty);
          // A change whose event was lost: only the heartbeat tells
          h.state.revision++;
          hub.heartbeat(h.state.heartbeatBody());
          async.elapse(const Duration(seconds: 1));
          expect(reasons, <PosRefreshReason>[PosRefreshReason.heartbeat]);
          expect(s.revision!.known, BigInt.from(h.state.revision));
          close(async);
        });
      },
    );

    test('the live safety poll goes without If-None-Match at least every 2 minutes; the others are conditional', () {
      fakeAsync((FakeAsync async) {
        open(async);
        h.adapter.requests.clear();
        async.elapse(const Duration(minutes: 6, seconds: 1));
        final List<bool> conditional = <bool>[
          for (final r in h.adapter.to('GET', '/v1/pos/floor'))
            r.headers['If-None-Match'] != null,
        ];
        expect(conditional, hasLength(6));
        expect(conditional, <bool>[true, false, true, false, true, false]);
        expect(s.unconditionalRefreshes, 3);
        close(async);
      });
    });

    test('silence (no heartbeat for silence_seconds): fast polling until a heartbeat returns', () {
      fakeAsync((FakeAsync async) {
        h = Harness();
        h.state.realtimeConfig = <String, Object?>{
          ...h.state.realtimeConfig!,
          'heartbeat_seconds': 30,
          'silence_seconds': 90,
        };
        hub = FakePosRealtimeHub();
        h.state.onEvent = hub.broadcast;
        unawaited(h.signIn());
        async.elapse(const Duration(seconds: 1));
        rt = PosRealtime.create(
          client: h.client,
          terminal: h.client.auth.terminal!,
          apiBaseUrl: 'https://api.test/api',
          appKey: FakePosState.realtimeKey,
          connector: hub.connect,
        )!;
        reasons = <PosRefreshReason>[];
        resumed = StreamController<void>.broadcast(sync: true);
        s = PosRefreshScheduler(
          realtime: rt,
          resumed: resumed.stream,
          onRefresh: (PosRefreshReason r) async => reasons.add(r),
        )..start();
        rt.start();
        async.elapse(const Duration(seconds: 30));
        hub.heartbeat(h.state.heartbeatBody());
        async.elapse(const Duration(seconds: 30));
        expect(rt.isLive, isTrue);
        reasons.clear();
        // The heartbeat stops (the scheduler or the queue is stuck)
        async.elapse(const Duration(seconds: 91));
        expect(rt.degraded, isTrue);
        expect(rt.state, PosLiveState.live, reason: 'still subscribed');
        reasons.clear();
        async.elapse(const Duration(seconds: 45));
        expect(
          reasons.where((PosRefreshReason r) => r == PosRefreshReason.poll),
          hasLength(3),
          reason: 'every 15 s while down',
        );
        hub.heartbeat(h.state.heartbeatBody());
        async.flushMicrotasks();
        expect(rt.isLive, isTrue);
        reasons.clear();
        async.elapse(const Duration(seconds: 59));
        expect(reasons, isEmpty, reason: 'back to the 60 s safety poll');
        close(async);
      });
    });

    test('live:false on the server: polls at the fallback interval while subscribed', () {
      fakeAsync((FakeAsync async) {
        h = Harness();
        h.state.realtimeConfig = <String, Object?>{
          ...h.state.realtimeConfig!,
          'live': false,
          'status': 'degraded',
        };
        hub = FakePosRealtimeHub();
        h.state.onEvent = hub.broadcast;
        unawaited(h.signIn());
        async.elapse(const Duration(seconds: 1));
        rt = PosRealtime.create(
          client: h.client,
          terminal: h.client.auth.terminal!,
          apiBaseUrl: 'https://api.test/api',
          appKey: FakePosState.realtimeKey,
          connector: hub.connect,
        )!;
        reasons = <PosRefreshReason>[];
        resumed = StreamController<void>.broadcast(sync: true);
        s = PosRefreshScheduler(
          realtime: rt,
          resumed: resumed.stream,
          onRefresh: (PosRefreshReason r) async => reasons.add(r),
        )..start();
        rt.start();
        async.elapse(const Duration(seconds: 1));
        expect(rt.state, PosLiveState.live);
        expect(rt.isLive, isFalse);
        reasons.clear();
        async.elapse(const Duration(seconds: 31));
        expect(reasons, <PosRefreshReason>[
          PosRefreshReason.poll,
          PosRefreshReason.poll,
        ]);
        close(async);
      });
    });

    test('no compiled key: the server\'s key from GET /v1/realtime', () {
      fakeAsync((FakeAsync async) {
        h = Harness();
        h.state.realtimeConfig = <String, Object?>{
          ...h.state.realtimeConfig!,
          'reverb': <String, Object?>{'key': 'server-made-key'},
        };
        hub = FakePosRealtimeHub();
        unawaited(h.signIn());
        async.elapse(const Duration(seconds: 1));
        final List<Uri> uris = <Uri>[];
        rt = PosRealtime.create(
          client: h.client,
          terminal: h.client.auth.terminal!,
          apiBaseUrl: 'https://api.test/api',
          appKey: '',
          connector: (Uri u, Map<String, String> headers) {
            uris.add(u);
            return hub.connect(u, headers);
          },
        )!;
        rt.start();
        async.elapse(const Duration(seconds: 1));
        expect(uris.single.path, '/app/server-made-key');
        expect(uris.single.host, 'api.test');
        expect(rt.isLive, isTrue);
        unawaited(rt.dispose());
        async.flushMicrotasks();
      });
    });

    test('the app coming back to the foreground re-reads at once', () {
      fakeAsync((FakeAsync async) {
        open(async);
        resumed.add(null);
        async.flushMicrotasks();
        expect(reasons, <PosRefreshReason>[PosRefreshReason.resume]);
        close(async);
      });
    });

    test('the first reconnect after a drop is not at a fixed second (spread over 0-10 s)', () {
      fakeAsync((FakeAsync async) {
        open(async);
        final int before = hub.connects;
        hub.dropAll();
        async.elapse(const Duration(milliseconds: 10));
        expect(rt.isLive, isFalse);
        async.elapse(const Duration(seconds: 10));
        expect(hub.connects, before + 1);
        expect(rt.state, PosLiveState.live);
        expect(reasons, contains(PosRefreshReason.resync));
        close(async);
      });
    });
  });
}
