import 'dart:async';
import 'dart:math';

import 'package:fake_async/fake_async.dart';
import 'package:pos_sync/pos_sync.dart';
import 'package:test/test.dart';

/// A server that applies each operation once (by op_id) and answers in order; it can be down, or lose its answer.
class FakeServer implements SyncTransport {
  final Map<String, SyncResult> applied = <String, SyncResult>{};
  final List<List<int>> batches = <List<int>>[];
  final List<int> appliedSeqs = <int>[];
  bool down = false;

  /// The server applies the batch, but the answer never reaches the till.
  bool loseNextAnswer = false;

  /// Stop at this seq (a database hiccup on the server).
  int? stopAt;

  @override
  Future<SyncBatchResponse> send(SyncBatchRequest request) async {
    if (down) throw const SyncUnreachable('offline');
    batches.add(<int>[for (final SyncOperation o in request.operations) o.seq]);
    final List<SyncResult> results = <SyncResult>[];
    int? stopped;
    for (final SyncOperation o in request.operations) {
      if (stopAt == o.seq) {
        stopAt = null;
        stopped = o.seq;
        break;
      }
      final SyncResult? before = applied[o.opId];
      if (before != null) {
        results.add(
          SyncResult(
            opId: o.opId,
            seq: o.seq,
            status: before.status,
            duplicate: true,
          ),
        );
        continue;
      }
      final SyncResult r = SyncResult(
        opId: o.opId,
        seq: o.seq,
        status: SyncOpStatus.applied,
      );
      applied[o.opId] = r;
      appliedSeqs.add(o.seq);
      results.add(r);
    }
    if (loseNextAnswer) {
      loseNextAnswer = false;
      throw const SyncUnreachable('answer lost');
    }
    return SyncBatchResponse(results: results, stoppedAt: stopped);
  }
}

void main() {
  late MemorySyncStorage disk;
  late SyncStore store;
  late FakeServer server;
  final Random random = Random(3);

  setUp(() async {
    disk = MemorySyncStorage();
    store = await SyncStore.open(disk, random: random);
    server = FakeServer();
  });

  Future<void> queue(int n) async {
    for (var i = 0; i < n; i++) {
      await store.enqueue(SyncOpType.orderRound, <String, Object?>{
        'order': 'o',
        'i': i,
      }, staffId: 3);
    }
  }

  test('drains the queue in seq order, in batches', () async {
    await queue(7);
    final SyncSender sender = SyncSender(
      store: store,
      transport: server,
      limits: const OfflineLimits(batchSize: 3),
    );

    final SyncDrainResult r = await sender.drain();

    expect(r.drained, isTrue);
    expect(r.sent, 7);
    expect(server.batches, <List<int>>[
      <int>[1, 2, 3],
      <int>[4, 5, 6],
      <int>[7],
    ]);
    expect(server.appliedSeqs, <int>[1, 2, 3, 4, 5, 6, 7]);
    expect(store.pendingCount, 0);
  });

  test(
    'an answer lost on the way back is sent again and applied once',
    () async {
      await queue(4);
      final SyncSender sender = SyncSender(
        store: store,
        transport: server,
        limits: const OfflineLimits(batchSize: 2),
      );
      server.loseNextAnswer = true;

      final SyncDrainResult first = await sender.drain();
      expect(first.drained, isFalse);
      expect(
        store.pendingCount,
        4,
        reason: 'nothing answered: everything stays queued',
      );
      expect(server.applied, hasLength(2));

      final SyncDrainResult second = await sender.drain();
      expect(second.drained, isTrue);
      expect(server.applied, hasLength(4));
      expect(server.appliedSeqs, <int>[1, 2, 3, 4]);
      expect(store.pendingCount, 0);
    },
  );

  test('an answer that cannot be written down leaves the operation queued and the sender retrying', () async {
    await queue(1);
    disk.failNextAppend = true;
    final SyncDrainResult first = await SyncSender(
      store: store,
      transport: server,
    ).drain();
    expect(first.error, isA<SyncStorageCrash>());
    expect(store.pendingCount, 1);

    final SyncDrainResult again = await SyncSender(
      store: store,
      transport: server,
    ).drain();
    expect(again.drained, isTrue);
    expect(server.appliedSeqs, <int>[1], reason: 'applied once, by op_id');
  });

  test('the queue survives a restart in the middle of draining', () async {
    await queue(5);
    final SyncSender sender = SyncSender(
      store: store,
      transport: server,
      limits: const OfflineLimits(batchSize: 2),
    );
    server.stopAt = 3;
    await sender.drain();
    expect(store.pendingCount, 3);

    // The app is restarted: the same queue, from the disk
    final SyncStore reopened = await SyncStore.open(disk, random: random);
    expect(reopened.pending.map((SyncOperation o) => o.seq), <int>[3, 4, 5]);
    await SyncSender(store: reopened, transport: server).drain();
    expect(server.appliedSeqs, <int>[1, 2, 3, 4, 5]);
    expect(reopened.pendingCount, 0);
  });

  test('while the server is down it waits longer each time, and tries at once when told the network is back', () {
    fakeAsync((FakeAsync async) {
      late SyncSender sender;
      var drained = 0;
      unawaited(() async {
        await queue(2);
        server.down = true;
        sender = SyncSender(
          store: store,
          transport: server,
          backoff: const SyncBackoff(
            base: Duration(seconds: 2),
            max: Duration(seconds: 30),
            jitter: 0,
          ),
          onDrained: () async => drained++,
        );
        sender.start();
      }());
      async.flushMicrotasks();
      expect(sender.failures, 1);

      async.elapse(const Duration(seconds: 2));
      expect(sender.failures, 2);
      async.elapse(const Duration(seconds: 3));
      expect(sender.failures, 2, reason: 'the second wait is 4 s');
      async.elapse(const Duration(seconds: 1));
      expect(sender.failures, 3);

      server.down = false;
      sender.nudge();
      async.flushMicrotasks();
      expect(store.pendingCount, 0);
      expect(sender.failures, 0);
      expect(drained, 1);

      // A new operation is sent as soon as it is queued (when the network is up)
      unawaited(
        store.enqueue(SyncOpType.orderSend, <String, Object?>{
          'order': 'o',
        }, staffId: 3),
      );
      async.flushMicrotasks();
      expect(store.pendingCount, 0);
      expect(server.appliedSeqs, <int>[1, 2, 3]);
      unawaited(sender.dispose());
    });
  });

  test('"send now" while an attempt that left offline is still on its way back: the queue goes in a fresh attempt, '
      'the button gets that answer, and no failure or backoff is left behind', () async {
    final _Held net = _Held(server);
    final SyncSender sender = SyncSender(
      store: store,
      transport: net,
      backoff: const SyncBackoff(jitter: 0),
    );
    addTearDown(sender.dispose);
    sender.start();
    await queue(1);
    await pumpEventQueue();
    expect(
      net.held,
      hasLength(1),
      reason: 'the automatic attempt left offline and waits',
    );
    expect(sender.syncing, isTrue);

    // The internet is back; the cashier presses "send now" (PosOffline.syncNow)
    net.offline = false;
    sender.nudge();
    final Future<SyncDrainResult> now = sender.drain();
    net.held.single.complete();
    final SyncDrainResult r = await now;
    await pumpEventQueue();

    expect(r.drained, isTrue);
    expect(server.appliedSeqs, <int>[1]);
    expect(store.pendingCount, 0);
    expect(net.calls, 2);
    expect(net.maxInFlight, 1, reason: 'never two sends at once');
    expect(sender.failures, 0);
    expect(sender.lastError, isNull);
    expect(sender.nextRetryAt, isNull);
  });

  test('really offline: "send now" over a running attempt reruns once, then waits the normal backoff', () async {
    final _Held net = _Held(server);
    final SyncSender sender = SyncSender(
      store: store,
      transport: net,
      backoff: const SyncBackoff(jitter: 0),
    );
    addTearDown(sender.dispose);
    sender.start();
    await queue(1);
    await pumpEventQueue();
    expect(net.held, hasLength(1));

    sender.nudge();
    final Future<SyncDrainResult> now = sender.drain();
    net.held.single.complete();
    final SyncDrainResult r = await now;
    await pumpEventQueue();

    expect(r.drained, isFalse);
    expect(r.error, isA<SyncUnreachable>());
    expect(
      net.calls,
      2,
      reason: 'the attempt on the wire and one rerun, no loop',
    );
    expect(net.maxInFlight, 1);
    expect(sender.failures, 2);
    expect(
      sender.nextRetryAt,
      isNotNull,
      reason: 'the normal backoff takes over',
    );

    // Nothing running: "send now" is exactly one attempt
    sender.nudge();
    await sender.drain();
    await pumpEventQueue();
    expect(net.calls, 3);
    expect(sender.nextRetryAt, isNotNull);
  });

  test('stop() (logout, re-pair): a fresh attempt asked for before it is not sent, and "send now" after it sends '
      'nothing', () async {
    final _Held net = _Held(server);
    final SyncSender sender = SyncSender(
      store: store,
      transport: net,
      backoff: const SyncBackoff(jitter: 0),
    );
    addTearDown(sender.dispose);
    sender.start();
    await queue(1);
    await pumpEventQueue();
    expect(
      net.held,
      hasLength(1),
      reason: 'the automatic attempt is on the wire',
    );

    sender.nudge();
    await sender.stop();
    net.offline = false;
    net.held.single.complete();
    await pumpEventQueue();

    expect(net.calls, 1, reason: 'nothing goes out after stop()');
    expect(
      sender.failures,
      1,
      reason: 'only the attempt that was on the wire failed',
    );
    expect(sender.nextRetryAt, isNull);

    final SyncDrainResult r = await sender.drain();
    await pumpEventQueue();
    expect(net.calls, 1);
    expect(r.drained, isFalse);
    expect(r.left, 1);
    expect(r.error, isNot(isA<SyncUnreachable>()));
    expect(sender.failures, 1);
    expect(store.pendingCount, 1, reason: 'the queue is left as it is');
  });

  test('an attempt that reached the server is not run again because its own answer reported contact: a refusal '
      'waits the backoff', () async {
    final _Held net = _Held(server)..offline = false;
    final SyncSender sender = SyncSender(
      store: store,
      transport: net,
      backoff: const SyncBackoff(jitter: 0),
    );
    addTearDown(sender.dispose);
    server.stopAt = 1;
    sender.start();
    await queue(1);
    await pumpEventQueue();
    expect(net.held, hasLength(1));

    // The answer arrives: PosHttp reports contact (offline -> online) and the till nudges, before the sender reads it
    sender.nudge();
    net.held.single.complete();
    await pumpEventQueue();

    expect(
      net.calls,
      1,
      reason: 'the server answered this attempt: no second send at once',
    );
    expect(sender.failures, 1);
    expect(sender.lastError, isA<SyncRefused>());
    expect(sender.nextRetryAt, isNotNull, reason: 'the backoff stands');
  });

  test('the backoff is capped', () {
    const SyncBackoff b = SyncBackoff(
      base: Duration(seconds: 2),
      max: Duration(minutes: 1),
      jitter: 0,
    );
    expect(b.delay(1), const Duration(seconds: 2));
    expect(b.delay(4), const Duration(seconds: 16));
    expect(b.delay(30), const Duration(minutes: 1));
  });

  test(
    'the batch says how much is queued and when the oldest happened',
    () async {
      await queue(3);
      final List<SyncBatchRequest> seen = <SyncBatchRequest>[];
      final SyncSender sender = SyncSender(
        store: store,
        transport: _Recording(seen, server),
        limits: const OfflineLimits(batchSize: 2),
      );
      await sender.drain();
      expect(seen.first.queueSize, 3);
      expect(seen.last.queueSize, 1);
      final Map<String, Object?> body = seen.first.toJson();
      expect((body['operations']! as List<Object?>).length, 2);
      expect((body['queue']! as Map<String, Object?>)['size'], 3);
    },
  );
}

/// The network as the till sees it: a request decides "offline" when it leaves, and the failure can be held on its
/// way back ([holdNext]), as dio's interceptor hops hold it for a few event-loop turns.
class _Held implements SyncTransport {
  _Held(this.inner);

  final FakeServer inner;
  bool offline = true;
  bool holdNext = true;
  final List<Completer<void>> held = <Completer<void>>[];
  int calls = 0;
  int inFlight = 0;
  int maxInFlight = 0;

  @override
  Future<SyncBatchResponse> send(SyncBatchRequest request) async {
    calls++;
    inFlight++;
    maxInFlight = max(maxInFlight, inFlight);
    try {
      final bool leftOffline = offline;
      if (holdNext) {
        holdNext = false;
        final Completer<void> c = Completer<void>();
        held.add(c);
        await c.future;
      }
      if (leftOffline) throw const SyncUnreachable('offline');
      return await inner.send(request);
    } finally {
      inFlight--;
    }
  }
}

class _Recording implements SyncTransport {
  _Recording(this.seen, this.inner);

  final List<SyncBatchRequest> seen;
  final SyncTransport inner;

  @override
  Future<SyncBatchResponse> send(SyncBatchRequest request) {
    seen.add(request);
    return inner.send(request);
  }
}
