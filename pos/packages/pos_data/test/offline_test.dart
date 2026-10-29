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
    int seed = 11,
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
      random: Random(seed),
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

  test('P6-16: a card paid offline on the bank terminal syncs with its RRN and last four digits', () async {
    final String room = h.state.seedOrder(tableId: 1, guests: 4);
    await h.warmUp();
    final PosOrder order = await h.pos.orders.get(room);
    final PosBill bill = (await h.pos.billing.createBills(
      room,
      version: order.version,
    )).bills.single;

    await h.goOffline();
    final PosBillResult paid = await h.pos.billing.pay(
      bill.uuid,
      tenders: <PosTenderInput>[
        PosTenderInput.card(
          bill.dueAmount,
          cardType: 'uzcard',
          transactionId: '123456789012',
          cardLast4: '4321',
        ),
      ],
      shiftUuid: h.state.shift!['uuid']! as String,
    );
    expect(paid.bill.payments.single.transactionId, '123456789012');
    expect(paid.bill.payments.single.cardLast4, '4321');

    h.adapter.offline = false;
    expect((await h.offline.syncNow()).drained, isTrue);
    final Map<String, Object?> sent =
        ((sync.received.first['payload']! as Map<String, Object?>)['tenders']!
                    as List<Object?>)
                .single!
            as Map<String, Object?>;
    expect(sent['transaction_id'], '123456789012');
    expect(sent['card_last4'], '4321');
    expect(h.state.orders[room]!['status'], 'delivered');
  });

  test('a bill made and printed online is reprinted and paid once the till is offline, and synced', () async {
    final String room = h.state.seedOrder(tableId: 1, guests: 4);
    await h.warmUp();
    final PosOrder order = await h.pos.orders.get(room);
    // Online: the guest asks for the bill, it is made and printed (the screen does not reload it)
    final PosBill bill = (await h.pos.billing.createBills(
      room,
      version: order.version,
    )).bills.single;
    await h.pos.billing.printBill(bill.uuid);

    await h.goOffline();
    final PosBillResult again = await h.pos.billing.printBill(bill.uuid);
    expect(again.bill.uuid, bill.uuid);
    final PosBillResult paid = await h.pos.billing.pay(
      bill.uuid,
      tenders: <PosTenderInput>[PosTenderInput.cash(bill.dueAmount)],
      shiftUuid: h.state.shift!['uuid']! as String,
    );
    expect(paid.bill.dueAmount, PosMoney.zero);
    expect(paid.bill.fiscal.status, 'offline');
    expect(paid.order.isClosed, isTrue);
    final PosFloor floor = await h.pos.floor.load();
    expect(floor.table(1)!.orders, isEmpty);

    h.adapter.offline = false;
    expect((await h.offline.syncNow()).drained, isTrue);
    expect(sync.received.map((Map<String, Object?> o) => o['type']), <String>[
      SyncOpType.billPay,
      SyncOpType.orderClose,
    ]);
    expect(
      (sync.received.first['payload']! as Map<String, Object?>)['bill'],
      bill.uuid,
    );
    expect(
      sync.results.values.every(
        (Map<String, Object?> r) => r['status'] == 'applied',
      ),
      isTrue,
    );
    expect(h.state.orders[room]!['status'], 'delivered');
  });

  test('the sale receipt of an offline payment is due once, across drains, sign-ins and restarts', () async {
    await h.warmUp();
    await h.goOffline();
    Future<String> sell() async {
      final PosOrder o = await h.pos.orders.open(
        channel: PosChannel.takeaway,
        items: <PosLineInput>[line(1, '1')],
      );
      final PosBill bill = (await h.pos.billing.createBills(
        o.uuid,
        version: o.version,
      )).bills.single;
      await h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: h.state.shift!['uuid']! as String,
      );
      return bill.uuid;
    }

    final List<List<SyncOperation>> emitted = <List<SyncOperation>>[];
    h.offline.synced.listen(emitted.add);
    await sell();
    h.adapter.offline = false;
    await h.offline.syncNow();
    expect(h.offline.salesToPrint, hasLength(1));
    final PosOfflineSale first = h.offline.salesToPrint.single;
    expect(h.state.orders[first.orderUuid], isNotNull);

    // Not printed yet (paper out, receipt still pending): it stays due after a restart
    await h.pos.dispose();
    h = await OfflineHarness.create(
      disk: h.disk,
      adapter: h.adapter,
      clock: h.clock,
      keys: h.keys,
      seed: 12, // a device's random source does not start over
    );
    await h.client.auth.restore();
    expect(h.offline.salesToPrint.single.opId, first.opId);
    await h.offline.salePrinted(first.opId);
    expect(h.offline.salesToPrint, isEmpty);
    h.offline.synced.listen(emitted.add);

    // Another cashier signs in; a second offline sale and drain: only that one is due, only that drain's answers
    // are emitted
    await h.client.auth.signInStaff(userId: 3, pin: h.state.pins[3]!);
    await h.warmUp();
    await h.goOffline();
    await sell();
    h.adapter.offline = false;
    await h.offline.syncNow();
    expect(h.offline.salesToPrint.single.opId, isNot(first.opId));
    expect(
      emitted.last.map((SyncOperation o) => o.opId),
      isNot(contains(first.opId)),
    );
    expect(emitted.last.map((SyncOperation o) => o.type), <String>[
      SyncOpType.orderOpen,
      SyncOpType.billCreate,
      SyncOpType.billPay,
      SyncOpType.orderClose,
    ]);
  });

  test('forgetting the terminal clears the journal and the copies; the seq goes on', () async {
    await h.warmUp();
    await h.goOffline();
    await h.pos.orders.open(
      channel: PosChannel.takeaway,
      items: <PosLineInput>[line(1, '1')],
    );
    final int seq = h.offline.store.lastSeq;
    expect(h.offline.store.hasPending, isTrue);

    await h.client.auth.forgetTerminal();
    await h.pos.catalog.clear(); // as the cashier app does
    await pumpEventQueue();
    expect(h.offline.store.hasPending, isFalse);
    expect(h.offline.store.docKeys(), isEmpty);
    expect(h.offline.salesToPrint, isEmpty);

    // Re-paired: nothing old is sent, copies are kept again, the seq does not start over
    h.adapter.offline = false;
    await h.signIn();
    await h.warmUp();
    expect(h.offline.active, isFalse);
    expect(h.offline.store.doc('bootstrap'), isNotNull);
    expect(sync.received, isEmpty);
    await h.goOffline();
    final SyncOperation next = (await () async {
      await h.pos.orders.open(
        channel: PosChannel.takeaway,
        items: <PosLineInput>[line(1, '1')],
      );
      return h.offline.store.pending.single;
    }());
    expect(next.seq, seq + 1);
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

  test('P3-35: a line kept at the server price is a conflict with a notice for the cashier; a clamped clock is quiet', () async {
    final String room = h.state.seedOrder(tableId: 1, guests: 2);
    await h.warmUp();
    final PosOrder before = await h.pos.orders.get(room);
    await h.goOffline();
    await h.pos.orders.addRound(
      room,
      version: before.version,
      items: <PosLineInput>[line(1, '1')],
      send: true,
    );
    sync.flagNext[SyncOpType.orderRound] = <Map<String, Object?>>[
      <String, Object?>{
        'flag': 'device_time_clamped',
        'device_time': '2026-10-22T09:00:00Z',
        'used_time': '2026-10-22T11:00:00Z',
      },
      <String, Object?>{
        'flag': 'offline_price_mismatch',
        'line_id': 'l1',
        'name': 'Choy',
        'device_price': '4000.00',
        'server_price': '5000.00',
      },
    ];
    final List<List<SyncOperation>> synced = <List<SyncOperation>>[];
    h.offline.synced.listen(synced.add);

    h.adapter.offline = false;
    await h.offline.syncNow();
    await Future<void>.delayed(Duration.zero);
    final SyncOperation op = synced
        .expand((List<SyncOperation> l) => l)
        .singleWhere((SyncOperation o) => o.type == SyncOpType.orderRound);
    expect(op.status, SyncOpStatus.conflict);
    expect(op.reason, PosSyncReasons.offlinePriceMismatch);
    expect(
      syncTouchesOrder(<SyncOperation>[op], room, (String id) => id),
      isTrue,
    );
    expect(
      syncTouchesOrder(<SyncOperation>[op], 'other', (String id) => id),
      isFalse,
    );
    final List<PosSyncPriceNotice> notices = PosSyncPriceNotice.of(
      <SyncOperation>[op],
    );
    expect(notices.single.orderUuid, room);
    expect(notices.single.message, contains("'Choy' offlayn 4000 so'mdan"));
    expect(notices.single.message, contains("5000 so'm qo'yildi"));
    expect(notices.single.message, contains('serverdan yangilandi'));
    final PosSyncError listed = (await h.offline.serverErrors()).errors.single;
    expect(listed.isRejected, isFalse);
    expect(listed.message, contains('server narxi'));

    // Only the clock moved: applied, nothing for the manager
    final PosOrder now = await h.pos.orders.get(room);
    await h.goOffline();
    await h.pos.orders.addRound(
      room,
      version: now.version,
      items: <PosLineInput>[line(1, '1')],
    );
    sync.flagNext[SyncOpType.orderRound] = <Map<String, Object?>>[
      <String, Object?>{
        'flag': 'device_time_clamped',
        'device_time': '2026-10-22T09:00:00Z',
        'used_time': '2026-10-22T11:00:00Z',
      },
    ];
    h.adapter.offline = false;
    await h.offline.syncNow();
    expect(h.offline.store.errors, hasLength(1));
    expect(
      PosSyncPriceNotice.of(h.offline.store.answered),
      hasLength(1),
      reason: 'the earlier one only',
    );
  });

  test('P3-35: invalid_payload and modifier_not_found are rejected with Uzbek text', () async {
    final String room = h.state.seedOrder(tableId: 1, guests: 2);
    await h.warmUp();
    final PosOrder before = await h.pos.orders.get(room);
    await h.goOffline();
    await h.pos.orders.addRound(
      room,
      version: before.version,
      items: <PosLineInput>[line(1, '1')],
    );
    sync.rejectNext[SyncOpType.orderRound] = PosSyncReasons.modifierNotFound;
    h.adapter.offline = false;
    await h.offline.syncNow();
    expect(
      h.offline.store.errors.single.message,
      contains("Qo'shimcha menyuda topilmadi"),
    );
    final PosSyncError e = (await h.offline.serverErrors()).errors.single;
    expect(e.isRejected, isTrue);
    expect(e.reason, 'modifier_not_found');

    // A server answer without a message reads the reason's text
    final PosSyncError bare = PosSyncError.fromJson(
      PosJson.of(<String, Object?>{
        'op_id': 'x',
        'seq': 3,
        'type': SyncOpType.billPay,
        'status': 'rejected',
        'reason': PosSyncReasons.invalidPayload,
      }, 'data'),
    );
    expect(bare.message, contains("serverda saqlanmadi"));
    expect(
      PosSyncReasons.describe(PosSyncReasons.deviceTimeClamped),
      contains('soati'),
    );
  });

  test('what the limited mode does not do is refused with a reason', () async {
    final String room = h.state.seedOrder(tableId: 1, guests: 2);
    await h.warmUp();
    final PosOrder order = await h.pos.orders.get(room);
    await h.goOffline();

    await expectLater(
      h.pos.orders.move(room, version: order.version, tableId: 2),
      throwsA(isA<PosOfflineUnavailable>()),
    );
    // P3-37: no discount offline (no sync op), said in Uzbek; nothing is queued
    final int queued = h.offline.store.pendingCount;
    await expectLater(
      h.pos.orders.discount(
        room,
        version: order.version,
        kind: PosDiscountKind.percent,
        value: '10',
        reason: 'Doimiy mijoz',
      ),
      throwsA(
        isA<PosOfflineUnavailable>().having(
          (PosOfflineUnavailable e) => e.message,
          'message',
          contains('Chegirma uchun internet kerak'),
        ),
      ),
    );
    expect(h.offline.store.pendingCount, queued);
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
