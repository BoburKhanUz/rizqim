import 'dart:async';

import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'offline_test.dart' show OfflineHarness;

/// A disk that refuses the orphan file (full, I/O error).
class _FailingDisk extends MemorySyncStorage {
  bool failOrphanWrites = false;

  @override
  Future<void> writeDoc(String key, String content) {
    if (failOrphanWrites && key.startsWith(PosOffline.orphanPrefix)) {
      throw const SyncStorageCrash();
    }
    return super.writeDoc(key, content);
  }
}

/// Wave M5 stage S1 (docs/scale/M5_DESIGN.md sections 1.6 and 2): the till never blocks a payment, and never deletes
/// operations the server has not received.
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

  String shift() => h.state.shift!['uuid']! as String;

  Future<void> settle() => Future<void>.delayed(Duration.zero);

  group('bug 1: the 24 h limit stops only NEW orders', () {
    test('after 25 h without contact a new order is refused; a round, the bill, a cash payment and the close of an '
        'order that is already open work', () async {
      final String room = h.state.seedOrder(tableId: 1, guests: 4);
      await h.warmUp();
      await h.goOffline();
      h.clock.now = h.clock.now.add(const Duration(hours: 25));

      expect(h.offline.status.canOpenOrders, isFalse);
      await expectLater(
        h.pos.orders.open(
          channel: PosChannel.takeaway,
          items: <PosLineInput>[line(1, '1')],
        ),
        throwsA(
          isA<PosOfflineUnavailable>().having(
            (PosOfflineUnavailable e) => e.message,
            'message',
            contains('yangi buyurtma ochilmaydi'),
          ),
        ),
      );

      final PosOrder before = await h.pos.orders.get(room);
      final PosOrder after = await h.pos.orders.addRound(
        room,
        version: before.version,
        items: <PosLineInput>[line(4, '1')],
        send: true,
      );
      expect(after.items.length, before.items.length + 1);

      final PosBilling billing = await h.pos.billing.createBills(
        room,
        version: after.version,
      );
      final PosBill bill = billing.bills.single;
      final PosBillResult paid = await h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: shift(),
      );
      expect(paid.order.isClosed, isTrue);
      expect(h.offline.store.pending.map((SyncOperation o) => o.type), <String>[
        SyncOpType.orderRound,
        SyncOpType.billCreate,
        SyncOpType.billPay,
        SyncOpType.orderClose,
      ]);
    });

    test(
      'after 30 h without contact a card payment of an open order is taken',
      () async {
        final String room = h.state.seedOrder(tableId: 1, guests: 2);
        await h.warmUp();
        await h.goOffline();
        h.clock.now = h.clock.now.add(const Duration(hours: 30));

        final PosOrder order = await h.pos.orders.get(room);
        final PosBilling billing = await h.pos.billing.createBills(
          room,
          version: order.version,
        );
        final PosBill bill = billing.bills.single;
        final PosBillResult paid = await h.pos.billing.pay(
          bill.uuid,
          tenders: <PosTenderInput>[
            PosTenderInput.card(
              bill.total,
              cardType: 'uzcard',
              transactionId: '412300987654',
            ),
          ],
          shiftUuid: shift(),
        );
        expect(paid.order.isClosed, isTrue);
      },
    );

    test('a round and a bill whose online request is lost go on through the queue with a menu copy older than a '
        'day', () async {
      final String room = h.state.seedOrder(tableId: 1, guests: 2);
      await h.warmUp();
      final PosOrder before = await h.pos.orders.get(room);
      h.clock.now = h.clock.now.add(const Duration(hours: 25));
      // The cashier signs in again (the server answers: contact is fresh), the menu copy is from yesterday
      await h.client.auth.signInStaff(userId: 3, pin: h.state.pins[3]!);
      h.adapter.offline = true;

      final PosOrder after = await h.pos.orders.addRound(
        room,
        version: before.version,
        items: <PosLineInput>[line(4, '1')],
      );
      expect(after.items.length, before.items.length + 1);
      expect(h.offline.store.pending.single.type, SyncOpType.orderRound);
    });

    test('the stored bootstrap copy stays usable after 24 h (halls and fees of the orders that are open)', () async {
      await h.warmUp();
      await h.goOffline();
      h.clock.now = h.clock.now.add(const Duration(hours: 25));
      expect(h.offline.storedBootstrap(), isNotNull);
    });
  });

  group('bug 4 on the till', () {
    test('a payment taken 37 h after the PIN login is recorded by the server (staff_unverified), not rejected; the '
        'till shows it to the manager', () async {
      final String room = h.state.seedOrder(tableId: 1, guests: 2);
      sync.sessionStartedAt[3] = h.clock.now;
      await h.warmUp();
      await h.goOffline();
      h.clock.now = h.clock.now.add(const Duration(hours: 37));

      final PosOrder order = await h.pos.orders.get(room);
      final PosBilling billing = await h.pos.billing.createBills(
        room,
        version: order.version,
      );
      final PosBill bill = billing.bills.single;
      await h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: shift(),
      );

      h.adapter.offline = false;
      await h.offline.syncNow();
      expect(h.offline.store.pendingCount, 0);
      final Map<String, Object?> pay = sync.results.values.firstWhere(
        (Map<String, Object?> r) => r['type'] == SyncOpType.billPay,
      );
      expect(pay['status'], 'conflict');
      expect(pay['reason'], 'staff_unverified');
      final Map<String, Object?> flag =
          ((pay['refs']! as Map<String, Object?>)['flags']! as List<Object?>)
                  .first!
              as Map<String, Object?>;
      expect(flag['flag'], 'staff_unverified');
      expect(flag['reason'], 'staff_not_signed_in');
      expect(flag['amount'], bill.total.toApiString());
      // The money is in the server's books (unallocated), the bill is not paid
      expect(sync.unallocated.single['amount'], bill.total.toApiString());
      expect(sync.unallocated.single['order_uuid'], room);
      // The bill.create of the same staff member is not money: rejected as before
      final Map<String, Object?> create = sync.results.values.firstWhere(
        (Map<String, Object?> r) => r['type'] == SyncOpType.billCreate,
      );
      expect(create['status'], 'rejected');
      expect(create['reason'], 'staff_not_signed_in');

      final SyncOperation shown = h.offline.store.errors.firstWhere(
        (SyncOperation o) => o.type == SyncOpType.billPay,
      );
      expect(shown.reason, 'staff_unverified');
      expect(PosSyncReasons.describe('staff_unverified'), contains('xodim'));
    });
  });

  group('bug 2: a 401 on the terminal token', () {
    /// Two offline sales in the queue, then the owner disconnects the till on the server.
    Future<List<String>> queueThenRevoke() async {
      final String room = h.state.seedOrder(tableId: 1, guests: 2);
      await h.warmUp();
      await h.goOffline();
      final PosOrder order = await h.pos.orders.get(room);
      final PosBilling billing = await h.pos.billing.createBills(
        room,
        version: order.version,
      );
      final PosBill bill = billing.bills.single;
      await h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: shift(),
      );
      final List<String> ids = <String>[
        for (final SyncOperation o in h.offline.store.pending) o.opId,
      ];
      expect(ids, hasLength(3));

      h.state.paired = false;
      h.adapter.offline = false;
      await h.offline.syncNow();
      await settle();
      await settle();
      expect(h.client.auth.terminal, isNull);
      return ids;
    }

    test('does not delete the unsent operations: every one of them is still on the disk', () async {
      final List<String> ids = await queueThenRevoke();
      final String disk = <String>[
        ...h.disk.lines,
        ...h.disk.docs.values,
      ].join('\n');
      for (final String id in ids) {
        expect(disk, contains(id), reason: 'operation $id was deleted');
      }
      expect(sync.received, isEmpty);
    });

    test('sets them aside as an orphan queue (who, when, how many payments) and stops sending', () async {
      final List<String> ids = await queueThenRevoke();
      final PosOrphanQueue orphan = h.offline.orphans.single;
      expect(orphan.operations.map((SyncOperation o) => o.opId), ids);
      expect(orphan.payments, 1);
      expect(orphan.reason, 'terminal_revoked');
      expect(orphan.deviceUuid, FakePosState.deviceUuid);
      expect(orphan.key, startsWith(PosOffline.orphanPrefix));
      // The journal itself is empty now: nothing of the old pairing can be sent with another token
      expect(h.offline.store.pendingCount, 0);
      expect(h.offline.held, isFalse);
      expect(h.offline.warnings.orphanOperations, 3);
      expect(h.offline.warnings.orphanPayments, 1);
      await h.offline.syncNow();
      expect(sync.received, isEmpty);
    });

    test('the orphan queue survives a restart, a new pairing with its own sales and a second unpairing', () async {
      final List<String> ids = await queueThenRevoke();
      await h.pos.dispose();
      h = await OfflineHarness.create(
        disk: h.disk,
        adapter: h.adapter,
        clock: h.clock,
        keys: h.keys,
        seed: 12,
      );
      await h.client.auth.restore();
      expect(h.client.auth.terminal, isNull);
      expect(
        h.offline.orphans.single.operations.map((SyncOperation o) => o.opId),
        ids,
      );

      // Paired again: the till works, its new sale reaches the server, the old operations are not sent
      await h.signIn();
      await h.warmUp();
      await h.goOffline();
      final PosOrder walkIn = await h.pos.orders.open(
        channel: PosChannel.takeaway,
        items: <PosLineInput>[line(1, '1')],
        send: true,
      );
      h.adapter.offline = false;
      expect((await h.offline.syncNow()).drained, isTrue);
      expect(sync.received.single['op_id'], isNot(isIn(ids)));
      expect(h.state.orders[h.offline.store.resolve(walkIn.uuid)], isNotNull);
      expect(h.offline.active, isFalse);
      expect(h.offline.orphans, hasLength(1));

      // The till is unpaired on purpose with an empty queue: the stored copies go, the orphan queue stays
      await h.client.auth.forgetTerminal();
      await settle();
      await settle();
      expect(h.offline.store.docKeys(prefix: 'catalog:'), isEmpty);
      await h.pos.dispose();
      h = await OfflineHarness.create(
        disk: h.disk,
        adapter: h.adapter,
        clock: h.clock,
        keys: h.keys,
        seed: 13,
      );
      expect(
        h.offline.orphans.single.operations.map((SyncOperation o) => o.opId),
        ids,
      );
    });

    test(
      'unpairing on the till with unsent operations keeps them too',
      () async {
        final String room = h.state.seedOrder(tableId: 1, guests: 2);
        await h.warmUp();
        await h.goOffline();
        final PosOrder order = await h.pos.orders.get(room);
        await h.pos.orders.addRound(
          room,
          version: order.version,
          items: <PosLineInput>[line(4, '1')],
        );
        final String id = h.offline.store.pending.single.opId;
        await h.client.auth.forgetTerminal();
        await settle();
        await settle();
        expect(h.offline.orphans.single.operations.single.opId, id);
        expect(h.offline.orphans.single.reason, 'terminal_forgotten');
        expect(h.offline.store.pendingCount, 0);
      },
    );

    test('when the orphan file cannot be written nothing is deleted and nothing is sent', () async {
      await h.pos.dispose();
      final _FailingDisk disk = _FailingDisk();
      h = await OfflineHarness.create(disk: disk);
      FakeCashierBackend(h.state);
      sync = FakeSyncBackend(h.state);
      h.state.openShift();
      await h.signIn();
      final String room = h.state.seedOrder(tableId: 1, guests: 2);
      await h.warmUp();
      await h.goOffline();
      final PosOrder order = await h.pos.orders.get(room);
      await h.pos.orders.addRound(
        room,
        version: order.version,
        items: <PosLineInput>[line(4, '1')],
      );
      disk.failOrphanWrites = true;
      h.state.paired = false;
      h.adapter.offline = false;
      await h.offline.syncNow();
      await settle();
      await settle();
      expect(h.offline.held, isTrue);
      expect(h.offline.store.pendingCount, 1);
      expect(h.offline.warnings.held, isTrue);
      expect((await h.offline.syncNow()).error, isA<SyncStopped>());
      expect(sync.received, isEmpty);
    });
  });

  test('the manager acknowledges the warnings; what was set aside stays on the disk', () async {
    final String room = h.state.seedOrder(tableId: 1, guests: 2);
    await h.warmUp();
    await h.goOffline();
    final PosOrder order = await h.pos.orders.get(room);
    await h.pos.orders.addRound(
      room,
      version: order.version,
      items: <PosLineInput>[line(4, '1')],
    );
    await h.client.auth.forgetTerminal();
    await settle();
    await settle();
    expect(h.offline.warnings.isEmpty, isFalse);
    h.clock.now = h.clock.now.add(const Duration(minutes: 1));
    await h.offline.acknowledgeWarnings();
    expect(h.offline.warnings.isEmpty, isTrue);
    expect(h.offline.orphans, hasLength(1));
  });
}
