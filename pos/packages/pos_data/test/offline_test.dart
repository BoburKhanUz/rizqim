import 'dart:math';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

/// P3-31: the limited mode in pos_data over the fake backend: online answers are kept, offline the till sells from its
/// copies, everything goes to the durable queue, and on reconnect it reaches the server once, in order.
class OfflineHarness {
  OfflineHarness._(
    this.disk,
    this.adapter,
    this.client,
    this.pos,
    this.clock,
    this.keys,
  );

  final MemorySyncStorage disk;
  final FakePosAdapter adapter;
  final RizqimClient client;
  final PosData pos;
  final TestClock clock;
  final MemoryKeyValueStore keys;

  FakePosState get state => adapter.state;
  PosOffline get offline => pos.offline!;

  static Future<OfflineHarness> create({
    MemorySyncStorage? disk,
    FakePosAdapter? adapter,
    TestClock? clock,
    MemoryKeyValueStore? keys,
  }) async {
    final MemoryKeyValueStore k = keys ?? MemoryKeyValueStore();
    final TestClock c = clock ?? TestClock(DateTime.utc(2026, 10, 22, 13));
    final MemorySyncStorage d = disk ?? MemorySyncStorage();
    final FakePosAdapter a = adapter ?? FakePosAdapter(clock: () => c.now);
    final RizqimClient client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: k,
      dio: Dio()..httpClientAdapter = a,
      clock: () => c.now,
    );
    final SyncStore store = await SyncStore.open(
      d,
      clock: () => c.now,
      random: Random(11),
    );
    final PosData pos = PosData(
      client,
      clock: () => c.now,
      random: Random(7),
      offlineStore: store,
    );
    return OfflineHarness._(d, a, client, pos, c, k);
  }

  Future<void> signIn({int userId = 3}) async {
    await client.auth.pairTerminal('ABCD-EFGH');
    await client.auth.signInStaff(userId: userId, pin: state.pins[userId]!);
  }

  /// Online: bootstrap, both menus, the floor and the open orders are loaded (and kept).
  Future<void> warmUp() async {
    final PosBootstrap boot = await pos.session.loadBootstrap();
    await pos.catalog.load(PosChannel.hall, menuVersion: boot.menuVersion);
    await pos.catalog.load(PosChannel.takeaway, menuVersion: boot.menuVersion);
    await pos.floor.load();
    await pos.orders.openOrders();
  }

  /// The network goes; the next request finds out.
  Future<void> goOffline() async {
    adapter.offline = true;
    await pos.floor.load();
  }
}

class TestClock {
  TestClock(this.now);

  DateTime now;
}

void main() {
  late OfflineHarness h;
  late FakeSyncBackend sync;

  setUp(() async {
    h = await OfflineHarness.create();
    FakeCashierBackend(h.state);
    sync = FakeSyncBackend(h.state);
    h.state.openShift();
    await h.signIn();
  });

  tearDown(() => h.pos.dispose());

  PosLineInput line(int variantId, String qty) => PosLineInput(
    variantUuid: FakePosState.variantUuid(variantId),
    quantity: PosQuantity.parse(qty),
  );

  test('offline: a walk-in sale and a room round are sold, paid in cash, and synced once on reconnect', () async {
    final String room = h.state.seedOrder(tableId: 1, guests: 4);
    await h.warmUp();
    final PosOrder roomBefore = await h.pos.orders.get(room);
    await h.goOffline();
    expect(h.offline.active, isTrue);
    expect(h.offline.status.canSell, isTrue);

    // Walk-in takeaway: 2 tea and sent
    final PosOrder walkIn = await h.pos.orders.open(
      channel: PosChannel.takeaway,
      items: <PosLineInput>[line(1, '2')],
      send: true,
    );
    expect(walkIn.shortCode, 'K1-001');
    expect(walkIn.raw['offline'], isA<Map<String, Object?>>());
    expect(walkIn.items.single.kitchenStatus, PosKitchenStatus.sent);
    expect(walkIn.totalPrice, isNot(PosMoney.zero));

    final PosBilling billing = await h.pos.billing.createBills(
      walkIn.uuid,
      version: walkIn.version,
    );
    final PosBill bill = billing.bills.single;
    expect(bill.total, walkIn.totalPrice);
    final PosBillResult paid = await h.pos.billing.pay(
      bill.uuid,
      tenders: <PosTenderInput>[
        PosTenderInput.cash(
          bill.total,
          tendered: bill.total + const PosMoney.som(5000),
        ),
      ],
      shiftUuid: h.state.shift!['uuid']! as String,
    );
    expect(paid.bill.fiscal.status, 'offline');
    expect(paid.order.isClosed, isTrue);

    // A round on the room order opened before the outage: the room fee and the hall's 10% stay right
    final PosOrder roomAfter = await h.pos.orders.addRound(
      room,
      version: roomBefore.version,
      items: <PosLineInput>[line(4, '3')],
      send: true,
    );
    expect(roomAfter.items.length, roomBefore.items.length + 1);
    final PosMoney added = roomAfter.items.last.totalPrice;
    expect(roomAfter.subtotal, roomBefore.subtotal + added);
    expect(roomAfter.roomFeeAmount, roomBefore.roomFeeAmount);

    // The floor: the walk-in is gone (paid), the room shows the new total
    final PosFloor floor = await h.pos.floor.load();
    expect(
      floor.untabledOrders.where((FloorOrder o) => o.uuid == walkIn.uuid),
      isEmpty,
    );
    expect(floor.table(1)!.orders.single.totalPrice, roomAfter.totalPrice);

    // Z is closed while the queue holds operations
    expect(h.offline.store.pendingCount, 5);
    await expectLater(
      h.pos.shifts.close(
        h.state.shift!['uuid']! as String,
        countedBreakdown: const <int, int>{},
      ),
      throwsA(isA<PosOfflineUnavailable>()),
    );

    // The network comes back: the queue goes in seq order, once
    h.adapter.offline = false;
    final SyncDrainResult result = await h.offline.syncNow();
    expect(result.drained, isTrue);
    expect(sync.received.map((Map<String, Object?> o) => o['type']), <String>[
      SyncOpType.orderOpen,
      SyncOpType.billCreate,
      SyncOpType.billPay,
      SyncOpType.orderClose,
      SyncOpType.orderRound,
    ]);
    expect(
      sync.results.values.every(
        (Map<String, Object?> r) => r['status'] == 'applied',
      ),
      isTrue,
    );
    expect(h.offline.store.pendingCount, 0);
    expect(h.offline.active, isFalse);
    expect(sync.pendingReported, 0);

    // On the server: the walk-in with the device's id, paid; the room with the round
    final String serverWalkIn = h.offline.store.resolve(walkIn.uuid);
    expect(serverWalkIn, isNot(walkIn.uuid));
    expect(h.state.orders[serverWalkIn]!['external_id'], walkIn.uuid);
    expect(h.state.orders[serverWalkIn]!['status'], 'delivered');
    expect(
      (h.state.orders[room]!['items']! as List<Object?>).length,
      roomAfter.items.length,
    );

    // The screen that still holds the device's id reads the server's order now
    final PosOrder reloaded = await h.pos.orders.get(walkIn.uuid);
    expect(reloaded.uuid, serverWalkIn);

    // Sending again changes nothing
    await h.offline.syncNow();
    expect(sync.received, hasLength(5));
  });

  test('a power cut while offline: the queue and the local orders come back on restart and sync by themselves', () async {
    await h.warmUp();
    await h.goOffline();
    final PosOrder walkIn = await h.pos.orders.open(
      channel: PosChannel.takeaway,
      items: <PosLineInput>[line(1, '1')],
    );
    await h.pos.dispose();

    // The app starts again, still offline, from the same disk; nobody can sign in without the server
    final OfflineHarness again = await OfflineHarness.create(
      disk: h.disk,
      adapter: h.adapter,
      clock: h.clock,
      keys: h.keys,
    );
    await again.client.auth.restore();
    expect(again.offline.store.pendingCount, 1);
    expect(again.offline.active, isTrue);
    final PosOrder restored = await again.pos.orders.get(walkIn.uuid);
    expect(restored.shortCode, 'K1-001');
    await expectLater(
      again.pos.orders.open(
        channel: PosChannel.takeaway,
        items: <PosLineInput>[line(1, '1')],
      ),
      throwsA(isA<PosOfflineUnavailable>()),
    );

    // The queue goes with the terminal's own token, no staff session needed
    h.adapter.offline = false;
    expect((await again.offline.syncNow()).drained, isTrue);
    expect(sync.received, hasLength(1));
    expect(again.offline.store.nextLocalNumber('K1'), 'K1-002');
    h = again;
  });

  test(
    'a rejected operation shows in the sync errors and a manager resolves it',
    () async {
      final String room = h.state.seedOrder(tableId: 1, guests: 2);
      await h.warmUp();
      final PosOrder before = await h.pos.orders.get(room);
      await h.goOffline();
      await h.pos.orders.addRound(
        room,
        version: before.version,
        items: <PosLineInput>[line(1, '1')],
      );
      sync.rejectNext[SyncOpType.orderRound] = 'order_closed';

      h.adapter.offline = false;
      await h.offline.syncNow();
      expect(h.offline.store.errors.single.reason, 'order_closed');
      expect(h.offline.status.errorsCount, 1);

      final PosSyncErrorsPage page = await h.offline.serverErrors();
      expect(page.errors.single.type, SyncOpType.orderRound);
      expect(page.errors.single.isRejected, isTrue);
      expect(page.errors.single.typeLabel, "Bosqich qo'shildi");

      // The cashier cannot settle it, the manager can
      await expectLater(
        h.offline.resolveError(
          page.errors.single.opId,
          resolution: 'dismissed',
        ),
        throwsA(isA<PosForbidden>()),
      );
      await h.client.auth.signInStaff(userId: 2, pin: h.state.pins[2]!);
      await h.offline.resolveError(
        page.errors.single.opId,
        resolution: 'resolved',
      );
      expect((await h.offline.serverErrors()).errors, isEmpty);
    },
  );

  test('what the limited mode does not do is refused with a reason', () async {
    final String room = h.state.seedOrder(tableId: 1, guests: 2);
    await h.warmUp();
    final PosOrder order = await h.pos.orders.get(room);
    await h.goOffline();

    await expectLater(
      h.pos.orders.move(room, version: order.version, tableId: 2),
      throwsA(isA<PosOfflineUnavailable>()),
    );
    await expectLater(
      h.pos.billing.createBills(
        room,
        version: order.version,
        split: const PosSplit.equal(2),
      ),
      throwsA(isA<PosOfflineUnavailable>()),
    );
    await expectLater(
      h.pos.orders.voidItem(
        room,
        order.items.firstWhere((PosOrderItem i) => i.wasSent).id,
        version: order.version,
        reason: 'x',
      ),
      throwsA(isA<PosOfflineUnavailable>()),
    );
    final PosBilling b = await h.pos.billing.createBills(
      room,
      version: order.version,
    );
    await expectLater(
      h.pos.billing.pay(
        b.bills.single.uuid,
        tenders: <PosTenderInput>[PosTenderInput.qr(b.bills.single.total)],
      ),
      throwsA(isA<PosOfflineUnavailable>()),
    );
  });

  test('no selling offline with a menu older than a day', () async {
    await h.warmUp();
    h.clock.now = h.clock.now.add(const Duration(hours: 25));
    await h.goOffline();
    expect(h.offline.status.canSell, isFalse);
    await expectLater(
      h.pos.orders.open(
        channel: PosChannel.takeaway,
        items: <PosLineInput>[line(1, '1')],
      ),
      throwsA(
        isA<PosOfflineUnavailable>().having(
          (PosOfflineUnavailable e) => e.message,
          'message',
          contains('Internet kerak'),
        ),
      ),
    );
  });

  test('a mutation whose answer is lost goes on through the queue with the same key', () async {
    final String room = h.state.seedOrder(tableId: 1, guests: 2);
    await h.warmUp();
    final PosOrder before = await h.pos.orders.get(room);
    h.adapter.offline = true;

    final PosOrder after = await h.pos.orders.addRound(
      room,
      version: before.version,
      items: <PosLineInput>[line(1, '1')],
      idempotencyKey: 'key-1',
    );

    expect(after.items.length, before.items.length + 1);
    final SyncOperation op = h.offline.store.pending.single;
    expect(op.type, SyncOpType.orderRound);
    expect(op.payload['idempotency_key'], 'key-1');
  });
}
