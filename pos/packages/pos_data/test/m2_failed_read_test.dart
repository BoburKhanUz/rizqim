import 'dart:async';

import 'package:fake_async/fake_async.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// M2 fix round 2, review SR-2: a page whose loader swallows its own errors (the cashier floor's `_load` catches
/// `PosException`, the waiter rooms catch everything) must not leave the screen stale after a failed event read: the
/// scheduler sees the failed GET through the read capture, sets doubt and retries the event once, paced.
void main() {
  setUp(() => RefreshThrottle.defaultRandom = () => 0);

  late Harness h;
  late FakePosRealtimeHub hub;
  late PosRealtime rt;
  late PosRefreshScheduler s;
  late List<PosRefreshReason> reasons;
  late int ok;

  /// A live cashier floor whose loader catches its errors, as `FloorPage._load` does.
  void open(FakeAsync async) {
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
    ok = 0;
    s = PosRefreshScheduler(
      realtime: rt,
      resumed: const Stream<void>.empty(),
      eventFilter: PosRelevance.floor,
      pacing: RefreshPacing.floor,
      onRefresh: (PosRefreshReason r) async {
        reasons.add(r);
        try {
          await h.pos.floor.load();
          ok++;
        } on PosException {
          // as FloorPage._load: the error is shown, not rethrown
        }
      },
    )..start();
    rt.start();
    async.elapse(const Duration(milliseconds: 50));
    reasons.clear();
    ok = 0;
  }

  void close(FakeAsync async) {
    unawaited(s.dispose());
    unawaited(rt.dispose());
    async.flushMicrotasks();
  }

  void orderCreated() => h.state.emit('order.created', <String, Object?>{
    'order': <String, Object?>{'uuid': 'o1', 'status': 'accepted'},
  });

  test('a 503 on the event read: doubt is set and the event is read again within one window', () {
    fakeAsync((FakeAsync async) {
      open(async);
      h.state.failPaths['/pos/floor'] = 503;
      orderCreated();
      async.elapse(const Duration(milliseconds: 100));
      h.state.failPaths.clear();
      expect(reasons, <PosRefreshReason>[PosRefreshReason.event]);
      expect(ok, 0);
      expect(s.revision!.known, isNull);
      expect(
        s.revision!.doubt,
        isTrue,
        reason: 'the swallowed failure is seen',
      );
      // One paced retry at the end of the 5 s window (+ spread, zero here)
      async.elapse(const Duration(seconds: 5));
      expect(ok, 1);
      expect(reasons, <PosRefreshReason>[
        PosRefreshReason.event,
        PosRefreshReason.event,
      ]);
      expect(s.revision!.doubt, isFalse);
      expect(s.revision!.known, BigInt.from(h.state.revision));
      close(async);
    });
  });

  test('the retry fails too: no loop; the next heartbeat (doubt) re-reads', () {
    fakeAsync((FakeAsync async) {
      open(async);
      h.state.failPaths['/pos/floor'] = 503;
      orderCreated();
      async.elapse(const Duration(seconds: 20));
      expect(reasons, hasLength(2), reason: 'the event read and ONE retry');
      expect(ok, 0);
      h.state.failPaths.clear();
      hub.heartbeat(h.state.heartbeatBody());
      async.elapse(const Duration(seconds: 1));
      expect(reasons.last, PosRefreshReason.heartbeat);
      expect(ok, 1);
      expect(s.revision!.doubt, isFalse);
      close(async);
    });
  });
}
