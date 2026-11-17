/// Wave M2 against the real backend: conditional GETs (a 304 on the wire for an unchanged floor, a 200 after a change),
/// `rev` on every event and `X-Revision` on the reads, a missed event caught by its `rev` gap and by the heartbeat, and a
/// till whose build has no Reverb key taking it from `GET /v1/realtime`.
///
/// The 304 needs the server's change tokens (`REALTIME_REVISION_STORE`, Redis in production). On a stack without a
/// working revision store every token read is a fresh random value (docs/OPERATIONS.md "Real-time"): then the test
/// checks that side instead (every read a full 200 with a new token, never a stale 304) and says so in the log.
@Timeout(Duration(minutes: 3))
library;

import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/backend.dart';
import 'support/menu.dart';
import 'support/stack.dart';

/// What came over the wire for each GET, before the ETag store turns a 304 back into a body.
class WireLog extends Interceptor {
  final List<({String path, int status, Headers headers})> seen =
      <({String path, int status, Headers headers})>[];

  static WireLog attach(Dio dio) {
    final WireLog log = WireLog();
    // First in the chain: it sees the raw 304 before ConditionalGetInterceptor replaces it
    dio.interceptors.insert(0, log);
    return log;
  }

  void detach(Dio dio) => dio.interceptors.remove(this);

  @override
  void onResponse(
    Response<dynamic> response,
    ResponseInterceptorHandler handler,
  ) {
    if (response.requestOptions.method == 'GET') {
      seen.add((
        path: response.requestOptions.uri.path,
        status: response.statusCode ?? 0,
        headers: response.headers,
      ));
    }
    handler.next(response);
  }

  List<({String path, int status, Headers headers})> of(String suffix) =>
      <({String path, int status, Headers headers})>[
        for (final s in seen)
          if (s.path.endsWith(suffix)) s,
      ];
}

/// The screen's socket, with the test deciding what of it reaches the screen ([dropHeartbeats]).
class SocketGate {
  bool dropHeartbeats = false;
  int droppedHeartbeats = 0;

  Future<RealtimeSocket> connect(Uri uri, Map<String, String> headers) async =>
      _GatedSocket(await ioRealtimeConnector(uri, headers), this);
}

class _GatedSocket implements RealtimeSocket {
  _GatedSocket(this._inner, this._gate);

  final RealtimeSocket _inner;
  final SocketGate _gate;

  @override
  Stream<String> get messages => _inner.messages.where((String raw) {
    if (!_gate.dropHeartbeats || !raw.contains(heartbeatEvent)) return true;
    final Object? m = jsonDecode(raw);
    if (m is Map && m['event'] == heartbeatEvent) {
      _gate.droppedHeartbeats++;
      return false;
    }
    return true;
  });

  @override
  void send(String message) => _inner.send(message);

  @override
  Future<void> close() => _inner.close();
}

void main() {
  late StackConfig config;
  late Terminal till;
  late Terminal waiter;
  late Menu menu;
  late int branchId;
  late String channelKey;
  late bool tokensWork;
  final List<PosOrder> opened = <PosOrder>[];

  /// Two full reads of the floor in a row, nothing changing in between: the same X-Revision when the server's
  /// revision store works, two random ones when it does not.
  Future<bool> stableTokens() async {
    final WireLog wire = WireLog.attach(till.client.dio);
    try {
      for (var i = 0; i < 2; i++) {
        await ReadTracking.capture(
          () => till.pos.floor.load(),
          unconditional: true,
        );
      }
      final List<String?> revs = <String?>[
        for (final s in wire.of('/v1/pos/floor')) s.headers.value('x-revision'),
      ];
      return revs.length == 2 && revs.first != null && revs.first == revs.last;
    } finally {
      wire.detach(till.client.dio);
    }
  }

  Future<PosFloor> floorOf(Terminal t) => t.pos.floor.load();

  /// A free table the other files do not use by name (realtime_test: Stol 2, Stol 3, Tapchan 3, Tapchan 4).
  int freeTable(PosFloor floor) => floor.tables
      .lastWhere(
        (FloorTable t) =>
            t.orders.isEmpty &&
            !<String>{
              'Stol 2',
              'Stol 3',
              'Tapchan 3',
              'Tapchan 4',
            }.contains(t.name),
      )
      .id;

  setUpAll(() async {
    config = StackConfig.fromEnvironment();
    till = await Terminal.open(config, 'cashier');
    waiter = await Terminal.open(config, 'waiter1');
    await till.signIn('cashier');
    await waiter.signIn('waiter1');
    menu = Menu(
      await waiter.pos.catalog.load(
        PosChannel.hall,
        menuVersion: waiter.bootstrap.menuVersion,
      ),
    );
    await till.listen();
    branchId = till.realtime!.branchId;
    channelKey = 'pos-branch.$branchId';
    tokensWork = await stableTokens();
    if (!tokensWork) {
      stdout.writeln(
        '\n[pos-e2e] M2: the stack has no working revision store (REALTIME_REVISION_STORE): every token is random, so '
        'the checks below prove the "never a stale 304" side; set REALTIME_REVISION_STORE=database to see the 304s',
      );
    }
  });

  tearDownAll(() async {
    final Staff manager = config.seed.staff('manager');
    for (final PosOrder o in opened) {
      try {
        final PosOrder current = await waiter.pos.orders.get(o.uuid);
        await runWithApproval<PosOrder>(
          (String? token) => waiter.pos.orders.cancel(
            current.uuid,
            version: current.version,
            reason: 'Sinov tugadi',
            approvalToken: token,
          ),
          approve: (String permission, PosException cause) =>
              waiter.pos.approvals.request(
                permission: permission,
                userId: manager.id,
                pin: manager.pin!,
              ),
        );
      } on Object catch (e) {
        stdout.writeln('[pos-e2e] M2: could not cancel ${o.uuid}: $e');
      }
    }
    await till.close();
    await waiter.close();
  });

  test('an unchanged floor: the second poll is a 304 on the wire and the till keeps its data; a change is a 200', () async {
    final WireLog wire = WireLog.attach(till.client.dio);
    addTearDown(() => wire.detach(till.client.dio));
    // A fresh start for the till's screens (as after a sign-in): the first read is a full 200
    till.client.etags.clear();
    final PosFloor first = await floorOf(till);
    final PosFloor second = await floorOf(till);
    final List<({String path, int status, Headers headers})> reads = wire.of(
      '/v1/pos/floor',
    );
    expect(reads, hasLength(2));
    final Headers h = reads.first.headers;
    expect(reads.first.status, 200);
    expect(h.value('etag'), isNotNull);
    expect(h.value('cache-control'), contains('no-cache'));
    expect(h.value('x-revision'), matches(RegExp(r'^\d+$')));
    expect(h.value('x-revision-scope'), 'branch:$branchId');
    if (tokensWork) {
      expect(reads.last.status, 304, reason: 'nothing changed');
      expect(till.client.etags.notModified, greaterThan(0));
      expect(second.tables.length, first.tables.length);
      expect(
        second.tables.map((FloorTable t) => t.orders.length),
        first.tables.map((FloorTable t) => t.orders.length),
      );
    } else {
      expect(reads.last.status, 200, reason: 'no working tokens: never a 304');
      expect(
        reads.last.headers.value('x-revision'),
        isNot(h.value('x-revision')),
      );
    }

    // A change on another phone: the next conditional poll is a 200 with it
    final int table = freeTable(second);
    final PosOrder order = await waiter.pos.orders.open(
      channel: PosChannel.hall,
      tableId: table,
      guestsCount: 2,
      waiterId: config.seed.staff('waiter1').id,
    );
    opened.add(order);
    final PosFloor third = await floorOf(till);
    expect(wire.of('/v1/pos/floor').last.status, 200);
    expect(
      third.tables
          .firstWhere((FloorTable t) => t.id == table)
          .orders
          .map((FloorOrder o) => o.uuid),
      contains(order.uuid),
    );
  });

  test('every event carries rev for its channel; it is the token the next read names', () async {
    final PosFloor floor = await floorOf(till);
    final int table = freeTable(floor);
    final PosOrder order = await waiter.pos.orders.open(
      channel: PosChannel.hall,
      tableId: table,
      guestsCount: 3,
      waiterId: config.seed.staff('waiter1').id,
    );
    opened.add(order);
    final HeardEvent heard = await till.log!.waitFor(
      (PosEvent e) =>
          e is PosOrderEvent && e.isCreated && e.orderUuid == order.uuid,
      what: 'order.created with rev',
    );
    final BigInt? rev = ChannelRevision.revisionIn(
      heard.event.data,
      channelKey,
    );
    expect(rev, isNotNull, reason: 'rev.$channelKey in ${heard.event.data}');
    if (tokensWork) {
      final CapturedRead<PosFloor> read = await ReadTracking.capture(
        () => floorOf(till),
      );
      // At least the event's token (the stack's own jobs may write in between: the read then names a later one)
      expect(
        read.reads.revisionFor('branch:$branchId'),
        greaterThanOrEqualTo(rev!),
      );
    }
  });

  test('a missed event: the next event is not N+1, so the screen re-reads in full; the heartbeat catches the rest', () async {
    // The screen gets its own socket through a gate the test controls, and no poll: nothing but what the test lets
    // through can move its token N. The stack's heartbeat loop (every 15 s) and the safety poll would otherwise catch
    // a miss up at a random moment and turn the gap below into an ordinary N+1.
    final SocketGate gate = SocketGate();
    final PosRealtime rt = PosRealtime.create(
      client: till.client,
      terminal: till.client.auth.terminal!,
      apiBaseUrl: config.apiUrl,
      appKey: config.reverbKey,
      reverbUrl: config.reverbUrl,
      clientName: 'pos-e2e-gated',
      connector: gate.connect,
    )!;
    addTearDown(rt.dispose);
    final List<PosRefreshReason> reasons = <PosRefreshReason>[];
    late PosFloor shown;
    final PosRefreshScheduler screen = PosRefreshScheduler(
      realtime: rt,
      // Only items_changed: the order.created of the same order is an event this screen never gets
      eventFilter: (PosEvent e) => e is PosOrderItemsChanged,
      pollInterval: Duration.zero,
      livePollInterval: Duration.zero,
      onRefresh: (PosRefreshReason r) async {
        shown = await floorOf(till);
        // After the read: a test waiting for the reason then sees the floor it brought
        reasons.add(r);
      },
      debounce: const Duration(milliseconds: 100),
    )..start();
    addTearDown(screen.dispose);
    final EventLog heard = EventLog(rt);
    addTearDown(heard.dispose);
    gate.dropHeartbeats = true;
    rt.start();
    await rt.states
        .firstWhere((PosLiveState s) => s == PosLiveState.live)
        .timeout(const Duration(seconds: 15));
    await screen.refreshNow();
    if (tokensWork) expect(screen.revision!.known, isNotNull);

    Future<void> waitForReason(PosRefreshReason r) async {
      final DateTime until = DateTime.now().add(const Duration(seconds: 15));
      while (!reasons.contains(r) && DateTime.now().isBefore(until)) {
        await Future<void>.delayed(const Duration(milliseconds: 50));
      }
      expect(reasons, contains(r));
    }

    // 1. The gap: order.created is missed (filtered), so the round's items_changed is N+2 for this screen
    final int table = freeTable(await floorOf(till));
    final PosOrder order = await waiter.pos.orders.open(
      channel: PosChannel.hall,
      tableId: table,
      guestsCount: 2,
      waiterId: config.seed.staff('waiter1').id,
    );
    opened.add(order);
    await heard.waitFor(
      (PosEvent e) => e is PosOrderEvent && e.orderUuid == order.uuid,
      what: 'order.created on the gated socket',
    );
    reasons.clear();
    final int rereadsBefore = screen.revision!.rereadCount;
    final int heartbeatsBefore = screen.revision!.heartbeatRereads;
    await waiter.pos.orders.addRound(
      order.uuid,
      version: order.version,
      items: menu.inputs(<Dish>[Dish.of('Osh', 1)]),
      send: true,
    );
    await waitForReason(PosRefreshReason.event);
    expect(
      screen.revision!.rereadCount,
      greaterThan(rereadsBefore),
      reason:
          'the first relevant event after a missed one is a re-read, not N+1',
    );
    expect(screen.revision!.heartbeatRereads, heartbeatsBefore);
    expect(reasons.toSet(), <PosRefreshReason>{PosRefreshReason.event});
    expect(
      shown.tables
          .firstWhere((FloorTable t) => t.id == table)
          .orders
          .map((FloorOrder o) => o.uuid),
      contains(order.uuid),
    );

    // 2. Lost entirely: a change whose only event this screen ignores; heartbeats are still held at the gate
    await screen.refreshNow();
    final BigInt? n = screen.revision!.known;
    reasons.clear();
    final int table2 = freeTable(await floorOf(till));
    final PosOrder lost = await waiter.pos.orders.open(
      channel: PosChannel.hall,
      tableId: table2,
      guestsCount: 2,
      waiterId: config.seed.staff('waiter1').id,
    );
    opened.add(lost);
    await heard.waitFor(
      (PosEvent e) => e is PosOrderEvent && e.orderUuid == lost.uuid,
      what: 'order.created (ignored by the screen)',
    );
    expect(reasons, isEmpty, reason: 'the screen did not get it');
    expect(screen.revision!.known, n, reason: 'N did not move');
    // Heartbeats through again, and one sent now: whichever arrives first (this one or the stack's own) is stamped
    // after the change, so its token differs from N
    final int beatsBefore = screen.revision!.heartbeatRereads;
    gate.dropHeartbeats = false;
    await artisan(<String>['schedule:test', '--name=realtime-heartbeat']);
    await waitForReason(PosRefreshReason.heartbeat);
    expect(screen.revision!.heartbeatRereads, greaterThan(beatsBefore));
    expect(
      shown.tables
          .firstWhere((FloorTable t) => t.id == table2)
          .orders
          .map((FloorOrder o) => o.uuid),
      contains(lost.uuid),
    );
  });

  test('GET /v1/realtime: a build without a Reverb key connects with the server\'s', () async {
    final RealtimeConfig? cfg = await RealtimeConfig.fetch(till.client.dio);
    expect(cfg, isNotNull);
    expect(cfg!.reverb?.key, config.reverbKey);
    expect(cfg.silenceSeconds, greaterThan(0));
    final PosRealtime rt = PosRealtime.create(
      client: till.client,
      terminal: till.client.auth.terminal!,
      apiBaseUrl: config.apiUrl,
      appKey: '',
      // The stack's Reverb runs on its own port (REVERB_PUBLIC_HOST is not set): the address from the build, the key
      // from the server
      reverbUrl: config.reverbUrl,
      clientName: 'pos-e2e-nokey',
    )!;
    addTearDown(rt.dispose);
    rt.start();
    await rt.states
        .firstWhere((PosLiveState s) => s == PosLiveState.live)
        .timeout(const Duration(seconds: 15));
    expect(rt.subscription, RealtimeSubscriptionState.subscribed);
  });
}
