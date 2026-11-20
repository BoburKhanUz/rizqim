import 'dart:async';
import 'dart:convert';

import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'offline_test.dart' show OfflineHarness;

/// Wave M5 stage S1, fixes after the two independent reviews (till side).
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

  String shift() => h.state.shift!['uuid']! as String;

  Future<void> settle() => Future<void>.delayed(Duration.zero);

  /// Signed in at 13:00, the network goes, 13 h pass (the token lived 12 h) and the till asks for something.
  Future<String> outageOutlivesTheSession() async {
    final String room = h.state.seedOrder(tableId: 1, guests: 2);
    sync.sessionStartedAt[3] = h.clock.now;
    await h.warmUp();
    await h.goOffline();
    h.clock.now = h.clock.now.add(const Duration(hours: 13));
    await h.pos.session.refreshEntitlements();
    expect(h.pos.session.staffContinued, isTrue);
    return room;
  }

  Future<PosBillResult> billAndPay(String room) async {
    final PosOrder order = await h.pos.orders.get(room);
    final PosBilling billing = await h.pos.billing.createBills(
      room,
      version: order.version,
    );
    final PosBill bill = billing.bills.single;
    return h.pos.billing.pay(
      bill.uuid,
      tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
      shiftUuid: shift(),
    );
  }

  group('rev1 M3: a flapping link', () {
    test('the link comes back for a moment during the outage, then goes again: the open table can still be '
        'paid', () async {
      final String room = await outageOutlivesTheSession();

      // One sync round gets through (terminal token; no cashier action), a staff request is refused (401: the token
      // is dead on the server), then the link is gone again
      h.adapter.offline = false;
      await h.offline.syncNow();
      try {
        await h.pos.session.refreshEntitlements();
      } on Object {
        // 401
      }
      await settle();
      // The PIN keypad is shown; nobody gets to enter a PIN before the link is gone again
      expect(h.pos.session.phase, PosSessionPhase.locked);
      await h.goOffline();
      await settle();
      expect(h.pos.session.isSignedIn, isTrue);

      final PosBillResult paid = await billAndPay(room);
      expect(paid.order.isClosed, isTrue);
      expect(h.pos.session.staffContinued, isTrue);
      for (final SyncOperation o in h.offline.store.pending) {
        expect(o.payload['staff_continued'], isTrue, reason: o.type);
      }
    });
  });

  group('rev2 L1: more than 12 h after the PIN login a continued staff member only bills, takes payment and '
      'closes', () {
    PosLineInput line(int variantId, String qty) => PosLineInput(
      variantUuid: FakePosState.variantUuid(variantId),
      quantity: PosQuantity.parse(qty),
    );

    Matcher refusedAsContinued() => throwsA(
      isA<PosOfflineUnavailable>()
          .having(
            (PosOfflineUnavailable e) => e.reason,
            'reason',
            PosOffline.continuedReason,
          )
          .having(
            (PosOfflineUnavailable e) => e.message,
            'message',
            contains('12 soat'),
          ),
    );

    test('no new round and no void; the bill, the cash payment and the close go through', () async {
      final String room = h.state.seedOrder(tableId: 1, guests: 2);
      sync.sessionStartedAt[3] = h.clock.now;
      await h.warmUp();
      await h.goOffline();
      // Two hours into the outage (the PIN session is alive): a round that is not sent to the kitchen yet
      h.clock.now = h.clock.now.add(const Duration(hours: 2));
      final PosOrder before = await h.pos.orders.get(room);
      final PosOrder withRound = await h.pos.orders.addRound(
        room,
        version: before.version,
        items: <PosLineInput>[line(4, '1')],
      );
      final PosOrderItem unsent = withRound.unsentItems.last;

      h.clock.now = h.clock.now.add(const Duration(hours: 11));
      await h.pos.session.refreshEntitlements();
      expect(h.pos.session.staffContinued, isTrue);
      expect(h.pos.session.staffFinishOnly, isTrue);
      expect(
        h.offline.continuedNotice,
        allOf(contains("PIN'siz davom etilmoqda"), contains('12 soat')),
      );

      await expectLater(
        h.pos.orders.addRound(
          room,
          version: withRound.version,
          items: <PosLineInput>[line(4, '1')],
        ),
        refusedAsContinued(),
      );
      await expectLater(
        h.pos.orders.voidItem(room, unsent.id, version: withRound.version),
        refusedAsContinued(),
      );
      expect(h.offline.store.pendingCount, 1);

      final PosBillResult paid = await billAndPay(room);
      expect(paid.order.isClosed, isTrue);
      expect(
        h.offline.store.pending.map((SyncOperation o) => o.type).skip(1),
        <String>[
          SyncOpType.billCreate,
          SyncOpType.billPay,
          SyncOpType.orderClose,
        ],
      );
    });
  });

  group('rev2 L3: the journal and the terminal at the start of the app', () {
    Future<List<String>> queueASale() async {
      final String room = h.state.seedOrder(tableId: 1, guests: 2);
      await h.warmUp();
      await h.goOffline();
      await billAndPay(room);
      final List<String> ids = <String>[
        for (final SyncOperation o in h.offline.store.pending) o.opId,
      ];
      expect(ids, hasLength(3));
      // The attempt this run's sender made is over (a request of the "previous run" must not arrive after the restart)
      expect((await h.offline.syncNow()).error, isNotNull);
      return ids;
    }

    Future<void> restart() async {
      await h.pos.dispose();
      h = await OfflineHarness.create(
        disk: h.disk,
        adapter: h.adapter,
        clock: h.clock,
        keys: h.keys,
        seed: 12,
        restore: true,
      );
      for (var i = 0; i < 5; i++) {
        await settle();
      }
    }

    test('a journal that belongs to another pairing is set aside before anything is sent', () async {
      final List<String> ids = await queueASale();
      // The till was paired again (another device on the server) and stopped before its queue was set aside
      h.disk.docs['_device'] = jsonEncode(<String, Object?>{
        'taken_at': h.clock.now.toUtc().toIso8601String(),
        'seq': 0,
        'value': <String, Object?>{
          'uuid': '99999999-9999-4999-8999-999999999999',
        },
      });
      h.adapter.offline = false;
      await restart();

      expect(h.client.auth.terminal?.device.uuid, FakePosState.deviceUuid);
      expect(sync.received, isEmpty, reason: 'sent with another token');
      expect(h.offline.store.pendingCount, 0);
      final PosOrphanQueue orphan = h.offline.orphans.single;
      expect(orphan.operations.map((SyncOperation o) => o.opId), ids);
      expect(orphan.reason, 'device_mismatch');
      expect(orphan.deviceUuid, '99999999-9999-4999-8999-999999999999');
      expect(h.offline.held, isFalse);
      // The till goes on with its own pairing
      await h.client.auth.signInStaff(userId: 3, pin: h.state.pins[3]!);
      await h.warmUp();
      await h.goOffline();
      await h.pos.orders.open(channel: PosChannel.takeaway);
      h.adapter.offline = false;
      expect((await h.offline.syncNow()).drained, isTrue);
      expect(sync.received, isNotEmpty);
      for (final Map<String, Object?> o in sync.received) {
        expect(o['op_id'], isNot(isIn(ids)), reason: '${o['type']}');
      }
    });

    test(
      'the same pairing after a restart: the queue is sent as before',
      () async {
        final List<String> ids = await queueASale();
        h.adapter.offline = false;
        await restart();
        expect(h.offline.held, isFalse);
        expect((await h.offline.syncNow()).left, 0);
        expect(sync.received.map((Map<String, Object?> o) => o['op_id']), ids);
        expect(h.offline.orphans, isEmpty);
      },
    );

    test('held survives a restart: a queue that could not be set aside is still not sent', () async {
      await h.pos.dispose();
      final _OrphanlessDisk disk = _OrphanlessDisk();
      h = await OfflineHarness.create(disk: disk);
      FakeCashierBackend(h.state);
      sync = FakeSyncBackend(h.state);
      h.state.openShift();
      await h.signIn();
      final List<String> ids = await queueASale();
      disk.failOrphanWrites = true;
      // Unpaired on the till: the terminal is gone from the device, the queue could not be set aside
      await h.client.auth.forgetTerminal();
      for (var i = 0; i < 5; i++) {
        await settle();
      }
      expect(h.offline.held, isTrue);

      // The same device's token is back in the secure storage (restored from a backup): held is not forgotten
      h.adapter.offline = false;
      await h.pos.dispose();
      final OfflineHarness paired = await OfflineHarness.create(
        adapter: h.adapter,
        clock: h.clock,
      );
      await paired.client.auth.pairTerminal('ABCD-EFGH');
      await paired.pos.dispose();
      h = await OfflineHarness.create(
        disk: disk,
        adapter: h.adapter,
        clock: h.clock,
        keys: paired.keys,
        seed: 12,
        restore: true,
      );
      for (var i = 0; i < 5; i++) {
        await settle();
      }
      expect(h.client.auth.terminal, isNotNull);
      expect(h.offline.held, isTrue);
      expect(h.offline.warnings.held, isTrue);
      expect(h.offline.store.pending.map((SyncOperation o) => o.opId), ids);
      expect((await h.offline.syncNow()).error, isA<SyncStopped>());
      expect(sync.received, isEmpty);
    });

    test('no terminal on the device and operations in the journal: nothing is sent, nothing is deleted', () async {
      final List<String> ids = await queueASale();
      h.adapter.offline = false;
      await h.pos.dispose();
      h = await OfflineHarness.create(
        disk: h.disk,
        adapter: h.adapter,
        clock: h.clock,
        seed: 12,
        restore: true,
      );
      for (var i = 0; i < 5; i++) {
        await settle();
      }
      expect(h.client.auth.terminal, isNull);
      expect(h.offline.held, isTrue);
      expect(h.offline.store.pending.map((SyncOperation o) => o.opId), ids);
      expect(sync.batches, 0);
    });
  });

  group('rev1 L2 (design bug 5): the journal is lost while the pairing survives', () {
    test('409 seq_reused: the till adopts the server\'s last_seq, renumbers what was never sent and the queue goes '
        'through; op ids and idempotency keys stay', () async {
      // A sale goes to the server: this device has used the numbers 1..3 there
      final String first = h.state.seedOrder(tableId: 1, guests: 2);
      await h.warmUp();
      await h.goOffline();
      await billAndPay(first);
      h.adapter.offline = false;
      expect((await h.offline.syncNow()).drained, isTrue);
      final int serverLast = sync.received.length;
      expect(serverLast, 3);

      // The journal file is gone (the secure storage, and with it the pairing, is not): numbers start at 1 again
      await h.pos.dispose();
      h = await OfflineHarness.create(
        adapter: h.adapter,
        clock: h.clock,
        keys: h.keys,
        seed: 12,
        restore: true,
      );
      expect(h.offline.store.lastSeq, 0);
      await h.client.auth.signInStaff(userId: 3, pin: h.state.pins[3]!);
      final String second = h.state.seedOrder(tableId: 2, guests: 2);
      await h.warmUp();
      await h.goOffline();
      final PosOrder order = await h.pos.orders.get(second);
      final PosBilling billing = await h.pos.billing.createBills(
        second,
        version: order.version,
        idempotencyKey: 'bill-key',
      );
      final PosBill bill = billing.bills.single;
      await h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: shift(),
        idempotencyKey: 'pay-key',
      );
      final List<SyncOperation> queued = h.offline.store.pending;
      expect(queued.map((SyncOperation o) => o.seq), <int>[1, 2, 3]);

      h.adapter.offline = false;
      final SyncDrainResult r = await h.offline.syncNow();

      expect(r.drained, isTrue, reason: '${r.error}');
      final List<Map<String, Object?>> taken = sync.received
          .skip(serverLast)
          .toList();
      expect(taken.map((Map<String, Object?> o) => o['seq']), <int>[4, 5, 6]);
      expect(
        taken.map((Map<String, Object?> o) => o['op_id']),
        queued.map((SyncOperation o) => o.opId),
      );
      expect(
        taken.map(
          (Map<String, Object?> o) => (o['payload']! as Map)['idempotency_key'],
        ),
        <String?>['bill-key', 'pay-key', null],
      );
      expect(h.state.orders[second]!['status'], isNot('open'));
      expect(h.offline.store.lastSeq, 6);
    });

    test('the status probe reports a higher last_seq than the till knows: new operations are numbered after '
        'it', () async {
      final String first = h.state.seedOrder(tableId: 1, guests: 2);
      await h.warmUp();
      await h.goOffline();
      await billAndPay(first);
      h.adapter.offline = false;
      expect((await h.offline.syncNow()).drained, isTrue);

      await h.pos.dispose();
      h = await OfflineHarness.create(
        adapter: h.adapter,
        clock: h.clock,
        keys: h.keys,
        seed: 12,
        restore: true,
      );
      expect(await h.offline.adoptServerSeq(), isTrue);
      expect(h.offline.store.lastSeq, 3);
    });
  });

  group('rev1 L3: a payment whose close would fall into the unpairing', () {
    test('the till is cut off between the payment and its order.close: both are set aside together, nothing is left '
        'in the journal for the next pairing', () async {
      // A real disk: a journal line takes a while to land (fsync)
      await h.pos.dispose();
      final _SlowDisk disk = _SlowDisk();
      h = await OfflineHarness.create(disk: disk);
      FakeCashierBackend(h.state);
      sync = FakeSyncBackend(h.state);
      h.state.openShift();
      await h.signIn();
      final String room = h.state.seedOrder(tableId: 1, guests: 2);
      await h.warmUp();
      await h.goOffline();
      final PosOrder order = await h.pos.orders.get(room);
      final PosBilling billing = await h.pos.billing.createBills(
        room,
        version: order.version,
      );
      final PosBill bill = billing.bills.single;

      // The payment is being written to the disk when the 401 arrives
      disk.slow = true;
      final Future<PosBillResult> paying = h.offline.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: shift(),
      );
      final Future<void> forgetting = h.offline.forget(
        reason: 'terminal_revoked',
      );
      final PosBillResult paid = await paying;
      await forgetting;
      for (var i = 0; i < 5; i++) {
        await settle();
      }

      expect(paid.order.isClosed, isTrue);
      expect(h.offline.held, isFalse);
      expect(
        h.offline.orphans.single.operations.map((SyncOperation o) => o.type),
        <String>[
          SyncOpType.billCreate,
          SyncOpType.billPay,
          SyncOpType.orderClose,
        ],
      );
      expect(h.offline.store.pending, isEmpty);
      // ...and on the disk: a restart finds no operation of the old pairing in the journal
      final SyncStore again = await SyncStore.open(h.disk);
      expect(again.pending, isEmpty);
    });
  });

  group('rev1 M1 on the till (the fake server follows PosSyncApplier)', () {
    test('a payment whose staff check fails on a bill the server knows settles that bill: conflict '
        'staff_unverified, the close that follows is applied, nothing is unallocated and the sale receipt is '
        'printed', () async {
      final String room = h.state.seedOrder(tableId: 1, guests: 2);
      sync.sessionStartedAt[3] = h.clock.now;
      await h.warmUp();
      final PosOrder order = await h.pos.orders.get(room);
      final PosBilling billing = await h.pos.billing.createBills(
        room,
        version: order.version,
      );
      final PosBill bill = billing.bills.single;
      await h.goOffline();
      h.clock.now = h.clock.now.add(const Duration(hours: 37));
      final PosBillResult paid = await h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: shift(),
      );
      expect(paid.order.isClosed, isTrue);
      expect(h.offline.store.pending.map((SyncOperation o) => o.type), <String>[
        SyncOpType.billPay,
        SyncOpType.orderClose,
      ]);

      h.adapter.offline = false;
      await h.offline.syncNow();
      await settle();

      expect(h.offline.store.pendingCount, 0);
      final Map<String, Object?> pay = sync.results.values.firstWhere(
        (Map<String, Object?> r) => r['type'] == SyncOpType.billPay,
      );
      expect(pay['status'], 'conflict');
      expect(pay['reason'], 'staff_unverified');
      final Map<String, Object?> refs = pay['refs']! as Map<String, Object?>;
      expect(refs['bill_uuid'], bill.uuid);
      expect(
        ((refs['flags']! as List<Object?>).first! as Map)['reason'],
        'staff_not_signed_in',
      );
      final Map<String, Object?> close = sync.results.values.firstWhere(
        (Map<String, Object?> r) => r['type'] == SyncOpType.orderClose,
      );
      expect(close['status'], 'applied');
      expect(
        (((close['refs']! as Map)['flags']! as List<Object?>).first!
            as Map)['flag'],
        'staff_unverified',
      );
      expect(sync.unallocated, isEmpty);
      expect(sync.unverified.single['bill_uuid'], bill.uuid);
      expect(h.state.orders[room]!['closed_at'], isNotNull);

      // The manager sees the payment (and only it); the sale receipt of the settled bill is printed
      expect(h.offline.store.errors.map((SyncOperation o) => o.type), <String>[
        SyncOpType.billPay,
      ]);
      expect(h.offline.salesToPrint.single.billUuid, bill.uuid);
    });

    test('the sync errors list does not name a staff member the server never saw on this till', () async {
      final String room = h.state.seedOrder(tableId: 1, guests: 2);
      await h.warmUp();
      final PosOrder order = await h.pos.orders.get(room);
      final PosBilling billing = await h.pos.billing.createBills(
        room,
        version: order.version,
      );
      final PosBill bill = billing.bills.single;
      await h.goOffline();
      await h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: shift(),
      );
      // The cashier lost the right to take payments meanwhile, and the server has no PIN login of theirs on record
      h.state.rolePermissions['cashier'] = <String>[
        for (final String p in h.state.rolePermissions['cashier']!)
          if (p != 'pos.take_payment') p,
      ];
      h.adapter.offline = false;
      await h.offline.syncNow();
      final Map<String, Object?> pay = sync.results.values.firstWhere(
        (Map<String, Object?> r) => r['type'] == SyncOpType.billPay,
      );
      expect(pay['reason'], 'staff_unverified');
      expect(pay['staff_id'], isNull);
      expect(pay['claimed_staff_id'], 3);
      expect(sync.unverified.single['staff_id'], isNull);
    });
  });
}

/// A disk on which the orphan documents cannot be written.
class _OrphanlessDisk extends MemorySyncStorage {
  bool failOrphanWrites = false;

  @override
  Future<void> writeDoc(String key, String content) async {
    if (failOrphanWrites && key.startsWith(PosOffline.orphanPrefix)) {
      throw StateError('disk full');
    }
    await super.writeDoc(key, content);
  }
}

/// A disk like a real one: once [slow] is set, a journal line takes a few turns of the event loop to land (fsync).
class _SlowDisk extends MemorySyncStorage {
  bool slow = false;

  @override
  Future<void> appendLine(String line) async {
    if (slow) {
      for (var i = 0; i < 20; i++) {
        await Future<void>.delayed(Duration.zero);
      }
    }
    await super.appendLine(line);
  }
}
