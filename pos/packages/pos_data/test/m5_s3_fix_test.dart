import 'dart:async';
import 'dart:convert';

import 'package:fake_async/fake_async.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'offline_test.dart' show OfflineHarness;

/// Wave M5 S3, fixes after the two reviews (signed offline queue): the till's side and the fake server that mirrors
/// the backend (docs/API.md "Imzolangan navbat").
Map<String, Object?> _record(String line) =>
    (jsonDecode(line.substring(9)) as Map).cast<String, Object?>();

String _line(Map<String, Object?> record) {
  final String json = jsonEncode(record);
  // (the journal's checksum: CRC-32 of the JSON text, 8 hex characters)
  var crc = 0xffffffff;
  for (final int b in utf8.encode(json)) {
    crc ^= b;
    for (var k = 0; k < 8; k++) {
      crc = (crc & 1) != 0 ? (crc >> 1) ^ 0xedb88320 : crc >> 1;
    }
  }
  final String sum = ((crc ^ 0xffffffff) & 0xffffffff)
      .toRadixString(16)
      .padLeft(8, '0');
  return '$sum $json';
}

void main() {
  late OfflineHarness h;
  late FakeSyncBackend sync;

  Future<void> start({bool signing = true}) async {
    h = await OfflineHarness.create();
    FakeCashierBackend(h.state);
    sync = FakeSyncBackend(h.state);
    h.state.offlineSigning = signing;
    h.state.advertiseSigningKid = true;
    h.state.openShift();
    await h.signIn();
  }

  tearDown(() => h.pos.dispose());

  PosSyncKeys keys() => h.pos.syncKeys!;

  Future<void> settle() async {
    for (var i = 0; i < 20; i++) {
      await Future<void>.delayed(Duration.zero);
    }
  }

  PosLineInput line(int variant, [String qty = '1']) => PosLineInput(
    variantUuid: FakePosState.variantUuid(variant),
    quantity: PosQuantity.parse(qty),
  );

  Future<PosOrder> sell({bool send = true, int lines = 1}) => h.pos.orders.open(
    channel: PosChannel.takeaway,
    items: <PosLineInput>[for (var i = 1; i <= lines; i++) line(i)],
    send: send,
  );

  Future<void> backOnline() async {
    h.adapter.offline = false;
    expect((await h.offline.syncNow()).drained, isTrue);
    await settle();
  }

  Map<String, Object?> resultOf(String type) => sync.results.values.firstWhere(
    (Map<String, Object?> r) => r['type'] == type,
  );

  // --- the fake server, driven directly (as a till would post) ----------------------------------------------------

  var seq = 0;
  var ids = 0;
  setUp(() {
    seq = 0;
    ids = 0;
  });

  SyncKey serverKey({bool previous = false}) => SyncKey.fromBase64(
    previous ? h.state.syncKeyPrev! : h.state.syncKey!,
    kid: previous ? h.state.syncKid - 1 : h.state.syncKid,
    deviceUuid: FakePosState.deviceUuid,
  );

  /// An operation the fake applies without a backend behind it.
  Map<String, Object?> op({
    int? at,
    String type = 'kitchen.bump',
  }) => <String, Object?>{
    'op_id': '00000000-0000-4000-8000-${(++ids).toString().padLeft(12, '0')}',
    'seq': at ?? ++seq,
    'type': type,
    'occurred_at': h.clock.now.toUtc().toIso8601String(),
    'staff_id': 3,
    'payload': <String, Object?>{'order': 'o-$ids', 'line': 1},
  };

  SyncSeal sign(Map<String, Object?> o, SyncKey key, String prev) =>
      SyncSignature.seal(key, prev, jsonEncode(o));

  Map<String, Object?> post(List<Object?> operations, {int? queue}) {
    final FakeResponse r = h.state.terminalRoutes!(
      'POST',
      '/v1/pos/sync',
      const <String, String>{},
      <String, Object?>{
        'sent_at': h.clock.now.toUtc().toIso8601String(),
        'queue': <String, Object?>{'size': queue ?? operations.length},
        'operations': operations,
      },
    )!;
    expect(r.status, 200, reason: '${r.body}');
    return ((r.body! as Map)['data']! as Map).cast<String, Object?>();
  }

  List<Map<String, Object?>> rows(Map<String, Object?> data, String key) =>
      <Map<String, Object?>>[
        for (final Object? r in data[key]! as List<Object?>)
          (r! as Map).cast<String, Object?>(),
      ];

  String sigOf(Map<String, Object?> data, [int i = 0]) =>
      rows(data, 'results')[i]['sig_status']! as String;

  group('rev2 M4: the server no longer has the key the till signs with', () {
    test('a `bad` answer to the till\'s own operation makes it fetch a new key '
        'once its queue is empty', () async {
      await start();
      await h.warmUp();
      // The server's copy of the key is gone under the same number (a database restored from a backup)
      h.state.syncKey = base64Encode(<int>[
        for (var i = 0; i < 32; i++) (i * 7 + 3) % 256,
      ]);

      await h.goOffline();
      await sell();
      await backOnline();
      expect(sync.sigStatus.values.last, 'bad');
      expect(h.state.syncKeyCalls, 1);
      expect(keys().current()?.kid, 2);

      await h.goOffline();
      await sell();
      await backOnline();
      expect(sync.sigStatus.values.last, 'ok');
      expect(h.state.syncKeyCalls, 1);
    });

    test(
      'a server that answers `bad` again and again is not asked for a key at '
      'every sale: the pause grows',
      () async {
        await start();
        await h.warmUp();
        Future<void> saleUnderALostKey() async {
          h.state.syncKey = base64Encode(<int>[
            for (var i = 0; i < 32; i++) (i * 5 + h.state.syncKid) % 256,
          ]);
          await h.goOffline();
          await sell();
          await backOnline();
          expect(sync.sigStatus.values.last, 'bad');
        }

        await saleUnderALostKey();
        expect(h.state.syncKeyCalls, 1);
        // The fresh key fails too, seconds later
        await saleUnderALostKey();
        expect(h.state.syncKeyCalls, 1);

        h.clock.now = h.clock.now.add(const Duration(minutes: 2));
        await h.pos.session.loadBootstrap();
        await settle();
        expect(h.state.syncKeyCalls, 2);
      },
    );

    test('never while operations are waiting', () async {
      await start();
      await h.warmUp();
      h.state.syncKey = base64Encode(<int>[
        for (var i = 0; i < 32; i++) (i * 7 + 3) % 256,
      ]);
      await h.goOffline();
      await sell();
      final SyncOperation first = h.offline.store.pending.first;
      await h.offline.sender.stop();
      h.adapter.offline = false;
      // Only the first one is answered (as a batch of one would be)
      await h.offline.store.applyResults(<SyncResult>[
        SyncResult.fromJson(<String, Object?>{
          'op_id': first.opId,
          'seq': first.seq,
          'status': 'applied',
          'sig_status': 'bad',
        }),
      ]);
      await sell();
      await settle();
      expect(h.offline.store.badKid, 1);
      expect(h.offline.store.hasPending, isTrue);
      expect(h.state.syncKeyCalls, 0);
    });
  });

  group('rev2 L4: a signed queue that is set aside keeps its signatures', () {
    test('the file holds raw, mac, prev and kid of every operation', () async {
      await start();
      await h.warmUp();
      await h.goOffline();
      await sell();
      final SyncOperation queued = h.offline.store.pending.first;
      expect(queued.seal, isNotNull);

      await h.client.auth.forgetTerminal();
      await settle();

      final PosOrphanQueue orphan = h.offline.orphans.single;
      expect(orphan.operations.first.opId, queued.opId);
      expect(orphan.operations.first.seal?.mac, queued.seal!.mac);
      expect(orphan.operations.first.toWire(), queued.toWire());
      final Map<String, Object?> onDisk = (jsonDecode(
        (await h.offline.store.storage.readDoc(orphan.key))!,
      ) as Map).cast<String, Object?>();
      final Map<String, Object?> first =
          (((onDisk['value']! as Map)['operations']! as List<Object?>).first!
                  as Map)
              .cast<String, Object?>();
      expect(first['raw'], queued.seal!.raw);
      expect(first['mac'], queued.seal!.mac);
      expect(first['prev'], queued.seal!.prev);
      expect(first['kid'], 1);
      // (still the operation itself for a reader that knows nothing of signatures)
      expect(first['op_id'], queued.opId);
    });

    test('a file written before (no signature) is read as before', () {
      final PosOrphanQueue? o = PosOrphanQueue.tryParse(
        'orphan_x',
        <String, Object?>{
          'set_aside_at': '2026-11-20T10:00:00Z',
          'operations': <Object?>[
            <String, Object?>{
              'op_id': 'a',
              'seq': 4,
              'type': 'bill.pay',
              'occurred_at': '2026-11-20T09:00:00Z',
              'staff_id': 3,
              'payload': <String, Object?>{},
            },
            // (a signed entry whose text is no operation is not counted, like any unreadable entry)
            <String, Object?>{
              'op_id': 'b',
              'seq': 5,
              'type': 'bill.pay',
              'occurred_at': '2026-11-20T09:00:00Z',
              'staff_id': 3,
              'payload': <String, Object?>{},
              'raw': 'not json',
              'mac': 'm',
              'prev': 'p',
              'kid': 1,
            },
          ],
        },
      );
      expect(o!.operations.single.seal, isNull);
      expect(o.payments, 1);
    });
  });

  group('F1 outcome: a signed line the server cannot use', () {
    test('leaves the till\'s queue (kept as damage, shown as rejected) and the '
        'rest of the queue goes on', () async {
      await start();
      await h.warmUp();
      await h.goOffline();
      // (this run of the app sends nothing: what it queued is read back from the disk by the next one)
      await h.offline.sender.stop();
      await sell();
      await sell();
      final List<SyncOperation> queued = h.offline.store.pending;
      expect(queued, hasLength(2));
      await h.pos.dispose();

      // Somebody edits the first line on the disk: its id is no id any more
      final int at = h.disk.lines.indexWhere(
        (String l) => _record(l)['raw'] == queued.first.seal!.raw,
      );
      final Map<String, Object?> edited = _record(h.disk.lines[at]);
      edited['raw'] = (edited['raw']! as String).replaceFirst(
        queued.first.opId,
        'not-an-id',
      );
      edited['op'] = <String, Object?>{
        ...(edited['op']! as Map).cast<String, Object?>(),
        'op_id': 'not-an-id',
      };
      h.disk.lines[at] = _line(edited);

      h = await OfflineHarness.create(
        disk: h.disk,
        adapter: h.adapter,
        clock: h.clock,
        keys: h.keys,
        restore: true,
      );
      h.adapter.offline = false;
      await h.client.auth.signInStaff(userId: 3, pin: h.state.pins[3]!);
      await backOnline();

      expect(h.offline.store.hasPending, isFalse);
      expect(sync.unreadable, hasLength(1));
      expect(sync.received, hasLength(1));
      // The innocent operation after it: a line is missing before it
      expect(sync.sigStatus[queued.last.opId], 'chain_gap');
      expect(h.offline.store.damaged.single.text, contains('not-an-id'));
      expect(h.offline.warnings.damagedLines, 1);
      final SyncOperation shown = h.offline.store.errors.single;
      expect(shown.opId, 'not-an-id');
      expect(shown.status, SyncOpStatus.rejected);
      expect(shown.reason, 'invalid_payload');
    });
  });

  group('F1 outcome: POST /terminal/sync-key answers 409 queue_not_empty', () {
    test('the fake refuses a rotation while it thinks the till has a queue, '
        'never the first key', () async {
      await start(signing: false);
      await h.warmUp();
      sync.pendingReported = 4;
      h.state.offlineSigning = true;
      // No key yet: issued whatever the queue
      await h.pos.session.loadBootstrap();
      await settle();
      expect(keys().current()?.kid, 1);

      sync.pendingReported = 4;
      h.state.syncKeyFailure = null;
      await expectLater(
        h.client.auth.remote.issueSyncKey(),
        throwsA(
          isA<ApiException>()
              .having((ApiException e) => e.statusCode, 'status', 409)
              .having(
                (ApiException e) => e.reason,
                'reason',
                'queue_not_empty',
              ),
        ),
      );
      expect(h.state.syncKid, 1);
    });

    test('a till whose queue is empty says so and asks once more', () async {
      await start();
      await h.warmUp();
      // What the server last heard of this till (a queue that was set aside since, for one)
      sync.pendingReported = 4;

      expect(await keys().rotate(), 2);

      expect(h.state.syncKeyCalls, 2);
      expect(
        h.adapter
            .to('GET', '/v1/pos/sync/status')
            .last
            .uri
            .queryParameters['queue_size'],
        '0',
      );
      expect(sync.pendingReported, 0);
      expect(keys().current()?.kid, 2);
    });

    test(
      'still refused after that: the till keeps its key and waits',
      () async {
        await start();
        await h.warmUp();
        h.state.rotateSyncKey(); // the server is ahead: the till wants a key
        h.state.syncKeyFailure = const FakeResponse(409, <String, Object?>{
          'success': false,
          'message': 'Kassa navbatida yuborilmagan amallar bor.',
          'data': <String, Object?>{
            'reason': 'queue_not_empty',
            'pending_ops': 4,
          },
        });
        await h.pos.session.loadBootstrap();
        await settle();
        // Asked, told the server its queue is empty, asked once more: no third time now
        // (three requests in a row: the key, the queue report, the key again)
        await settle();
        expect(h.state.syncKeyCalls, 2);
        expect(keys().current()?.kid, 1);
        await h.pos.session.loadBootstrap();
        await settle();
        expect(h.state.syncKeyCalls, 2);

        await expectLater(
          keys().rotate(),
          throwsA(
            isA<ApiException>().having(
              (ApiException e) => e.reason,
              'reason',
              'queue_not_empty',
            ),
          ),
        );
      },
    );
  });

  group('question 6: a till that sits idle after a failed key request', () {
    test('asks again by itself when the pause is over', () {
      fakeAsync((FakeAsync async) {
        unawaited(start(signing: false).then((_) => h.warmUp()));
        async.elapse(const Duration(milliseconds: 50));
        h.state
          ..offlineSigning = true
          ..syncKeyFailure = const FakeResponse(500, <String, Object?>{
            'success': false,
            'message': 'Server xatosi',
            'data': <String, Object?>{},
          });
        unawaited(h.pos.session.loadBootstrap());
        async.elapse(const Duration(milliseconds: 50));
        expect(h.state.syncKeyCalls, 1);

        // Nothing happens on the till: no sale, no bootstrap
        h.state.syncKeyFailure = null;
        h.clock.now = h.clock.now.add(const Duration(seconds: 59));
        async.elapse(const Duration(seconds: 59));
        expect(h.state.syncKeyCalls, 1);
        h.clock.now = h.clock.now.add(const Duration(seconds: 2));
        async.elapse(const Duration(seconds: 2));
        expect(h.state.syncKeyCalls, 2);
        expect(h.pos.syncKeys!.current()?.kid, 1);

        unawaited(h.pos.dispose());
        async.elapse(const Duration(milliseconds: 50));
      });
    });

    test('no timer is left after dispose', () {
      fakeAsync((FakeAsync async) {
        unawaited(start(signing: false).then((_) => h.warmUp()));
        async.elapse(const Duration(milliseconds: 50));
        h.state
          ..offlineSigning = true
          ..syncKeyFailure = const FakeResponse(500, <String, Object?>{
            'success': false,
            'message': 'Server xatosi',
            'data': <String, Object?>{},
          });
        unawaited(h.pos.session.loadBootstrap());
        async.elapse(const Duration(milliseconds: 50));
        expect(h.state.syncKeyCalls, 1);
        unawaited(h.pos.syncKeys!.dispose());
        async.elapse(const Duration(seconds: 5));
        h.state.syncKeyFailure = null;
        h.clock.now = h.clock.now.add(const Duration(minutes: 5));
        async.elapse(const Duration(minutes: 5));
        expect(h.state.syncKeyCalls, 1);
      });
    });
  });

  group('the till\'s text for signature_invalid', () {
    test('says the operation was applied (a void or a discount was not)', () {
      final String text = PosSyncReasons.describe('signature_invalid')!;
      expect(text, contains("qo'llandi"));
      expect(text, isNot(contains('qabul qilmadi')));
      expect(text, contains('chegirma'));
    });
  });

  group('the fake server, enforce mode (D1: a sale is never dropped)', () {
    test('an unsigned sale: order, bill and payment are all on the server, for '
        'the manager; nothing is rejected', () async {
      await start(signing: false);
      sync.enforceSignatures = true;
      await h.warmUp();
      await h.goOffline();
      final PosOrder order = await sell();
      final PosBilling billing = await h.pos.billing.createBills(
        order.uuid,
        version: order.version,
      );
      final PosBill bill = billing.bills.single;
      await h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: h.state.shift!['uuid']! as String,
      );
      await backOnline();

      expect(sync.sigStatus.values.toSet(), <String>{'missing'});
      expect(
        sync.results.values.where(
          (Map<String, Object?> r) => r['status'] == 'rejected',
        ),
        isEmpty,
      );
      expect(resultOf('order.open')['status'], 'conflict');
      expect(resultOf('order.open')['reason'], 'signature_invalid');
      expect(resultOf('order.open')['sig_status'], 'missing');
      expect(resultOf('bill.create')['reason'], 'signature_invalid');
      expect(resultOf('bill.pay')['status'], 'conflict');
      expect(resultOf('bill.pay')['reason'], 'payment_unverified');
      // The money is on its bill and the order is closed
      final String server = sync.uuids[order.uuid]!;
      expect(h.state.orders[server], isNotNull);
      expect(h.state.orders[server]!['closed_at'], isNotNull);
      expect(sync.unallocated, isEmpty);
    });

    test('a concession is refused: an unsigned void', () async {
      await start(signing: false);
      sync.enforceSignatures = true;
      await h.warmUp();
      await h.goOffline();
      final PosOrder order = await sell(send: false, lines: 2);
      await h.pos.orders.voidItem(
        order.uuid,
        order.items.first.id,
        version: order.version,
      );
      await backOnline();

      expect(resultOf('order.open')['status'], 'conflict');
      expect(resultOf('order.void')['status'], 'rejected');
      expect(resultOf('order.void')['reason'], 'signature_invalid');
      final List<Object?> items =
          h.state.orders[sync.uuids[order.uuid]]!['items']! as List<Object?>;
      expect(
        items.where((Object? i) => (i! as Map)['kitchen_status'] != 'void'),
        hasLength(2),
      );
    });

    test('a concession is refused: an unsigned discount, as any refused '
        'discount', () async {
      await start(signing: false);
      sync.enforceSignatures = true;
      h.state.offlineDiscount = true;
      final String room = h.state.seedOrder(tableId: 1, guests: 4);
      await h.warmUp();
      final PosOrder order = await h.pos.orders.get(room);
      await h.goOffline();
      await h.pos.orders.discount(
        order.uuid,
        version: order.version,
        kind: PosDiscountKind.percent,
        value: '5',
        reason: 'Doimiy mijoz',
      );
      await backOnline();

      final Map<String, Object?> r = resultOf('order.discount');
      expect(r['status'], 'rejected');
      expect(r['reason'], 'discount_not_allowed');
      expect((r['refs']! as Map)['cause'], 'signature_invalid');
    });

    test('signed and in order: applied as ever', () async {
      await start();
      sync.enforceSignatures = true;
      await h.warmUp();
      await h.goOffline();
      await sell();
      await backOnline();
      expect(sync.sigStatus.values.toSet(), <String>{'ok'});
      expect(resultOf('order.open')['status'], 'applied');
    });

    test('a line missing before it: applied, a conflict chain_gap', () async {
      await start();
      sync.enforceSignatures = true;
      final SyncKey key = serverKey();
      final Map<String, Object?> a = op();
      final SyncSeal sa = sign(a, key, SyncSignature.firstPrev(key));
      post(<Object?>[sa.toWire()]);
      final Map<String, Object?> lost = op();
      final SyncSeal sl = sign(lost, key, sa.mac);
      final Map<String, Object?> c = op();
      final Map<String, Object?> answer = post(<Object?>[
        sign(c, key, sl.mac).toWire(),
      ]);
      expect(rows(answer, 'results').single['status'], 'conflict');
      expect(rows(answer, 'results').single['reason'], 'chain_gap');
      expect(sigOf(answer), 'chain_gap');
    });

    test('observe mode (the default) applies all of it', () async {
      await start(signing: false);
      await h.warmUp();
      await h.goOffline();
      await sell();
      await backOnline();
      expect(resultOf('order.open')['status'], 'applied');
      expect(resultOf('order.open')['sig_status'], 'missing');
    });
  });

  group('the fake server\'s chain (as SyncChain)', () {
    test('a badly signed line never moves the head; in its place, the mac it '
        'claims is accepted as the next prev', () async {
      await start();
      final SyncKey key = serverKey();
      final SyncSeal sa = sign(op(), key, SyncSignature.firstPrev(key));
      final Map<String, Object?> b = op();
      final SyncSeal sb = sign(b, key, sa.mac);
      // b's line was edited after it was signed
      final Map<String, Object?> edited = <String, Object?>{
        ...sb.toWire(),
        'signed': jsonEncode(<String, Object?>{...b, 'staff_id': 4}),
      };
      final SyncSeal sc = sign(op(), key, sb.mac);
      final Map<String, Object?> answer = post(<Object?>[
        sa.toWire(),
        edited,
        sc.toWire(),
      ]);
      expect(
        <String>[for (var i = 0; i < 3; i++) sigOf(answer, i)],
        <String>['ok', 'bad', 'ok'],
      );
      // The head is c: an operation that goes on from the edited line's mac again does not follow
      expect(
        sigOf(post(<Object?>[sign(op(), key, sb.mac).toWire()])),
        'chain_gap',
      );
    });

    test('a badly signed line whose number does not follow vouches for '
        'nothing', () async {
      await start();
      final SyncKey key = serverKey();
      final SyncSeal sa = sign(op(), key, SyncSignature.firstPrev(key));
      post(<Object?>[sa.toWire()]);
      final Map<String, Object?> far = op(at: 5);
      final SyncSeal sf = sign(far, key, sa.mac);
      final Map<String, Object?> edited = <String, Object?>{
        ...sf.toWire(),
        'signed': jsonEncode(<String, Object?>{...far, 'staff_id': 4}),
      };
      final Map<String, Object?> answer = post(<Object?>[
        edited,
        sign(op(at: 6), key, sf.mac).toWire(),
      ]);
      expect(sigOf(answer, 0), 'bad');
      expect(sigOf(answer, 1), 'chain_gap');
    });

    test('the key order first: the key before a rotation cannot go on after '
        'the new key\'s first operation', () async {
      await start();
      final SyncKey old = serverKey();
      final SyncSeal sa = sign(op(), old, SyncSignature.firstPrev(old));
      post(<Object?>[sa.toWire()]);
      h.state.rotateSyncKey();
      final SyncKey now = serverKey();
      expect(now.kid, 2);
      expect(
        sigOf(
          post(<Object?>[
            sign(op(), now, SyncSignature.firstPrev(now)).toWire(),
          ]),
        ),
        'ok',
      );
      final SyncKey before = serverKey(previous: true);
      expect(
        sigOf(post(<Object?>[sign(op(), before, sa.mac).toWire()])),
        'chain_gap',
      );
    });

    test('the chain started again from the first prev: ok only straight after '
        'an unsigned operation (a rolled back app)', () async {
      await start();
      final SyncKey key = serverKey();
      final String first = SyncSignature.firstPrev(key);
      post(<Object?>[sign(op(), key, first).toWire()]);
      // Not after a signed one
      expect(
        sigOf(post(<Object?>[sign(op(), key, first).toWire()])),
        'chain_gap',
      );
      // The old build sells unsigned, the updated one signs again from the start
      expect(sigOf(post(<Object?>[op()])), 'missing');
      expect(sigOf(post(<Object?>[sign(op(), key, first).toWire()])), 'ok');
      // ...but not when a number is missing in between
      expect(sigOf(post(<Object?>[op()])), 'missing');
      expect(
        sigOf(post(<Object?>[sign(op(at: seq += 2), key, first).toWire()])),
        'chain_gap',
      );
    });

    test('a kid sent as text is the same number', () async {
      await start();
      final SyncKey key = serverKey();
      final SyncSeal sa = sign(op(), key, SyncSignature.firstPrev(key));
      expect(
        sigOf(
          post(<Object?>[
            <String, Object?>{...sa.toWire(), 'kid': '1'},
          ]),
        ),
        'ok',
      );
    });
  });

  group('the fake server: signed text that is no usable operation', () {
    test('never refuses the batch: answered in `unreadable` by its place, '
        'nothing recorded', () async {
      await start();
      final SyncKey key = serverKey();
      final String first = SyncSignature.firstPrev(key);
      final SyncSeal junk = SyncSignature.seal(key, first, 'not json');
      final SyncSeal nameless = SyncSignature.seal(
        key,
        first,
        jsonEncode(<String, Object?>{'op_id': 'not-an-id', 'seq': 9}),
      );
      final Map<String, Object?> good = op();
      final Map<String, Object?> answer = post(<Object?>[
        junk.toWire(),
        good,
        nameless.toWire(),
      ], queue: 5);
      expect(rows(answer, 'results').single['op_id'], good['op_id']);
      expect(
        rows(answer, 'unreadable').map((Map<String, Object?> u) => u['index']),
        <int>[0, 2],
      );
      expect(rows(answer, 'unreadable').first['reason'], 'invalid_payload');
      expect(rows(answer, 'unreadable').first['op_id'], isNull);
      expect(sync.received, hasLength(1));
      // What the till still holds: 5 - 1 applied - 2 unreadable
      expect(sync.pendingReported, 2);
    });

    test(
      'a line that names itself is recorded rejected (and a duplicate when '
      'it comes again); its id or number taken by another: unreadable',
      () async {
        await start();
        final SyncKey key = serverKey();
        final Map<String, Object?> newer = op(type: 'order.teleport');
        final SyncSeal sn = sign(newer, key, SyncSignature.firstPrev(key));
        final Map<String, Object?> broken = op()..remove('staff_id');
        final SyncSeal sb = sign(broken, key, sn.mac);
        final Map<String, Object?> answer = post(<Object?>[
          sn.toWire(),
          sb.toWire(),
        ]);
        final List<Map<String, Object?>> results = rows(answer, 'results');
        expect(results[0]['status'], 'rejected');
        expect(results[0]['reason'], 'type_unknown');
        // (signed by the till itself: the chain goes on after it)
        expect(results[0]['sig_status'], 'ok');
        expect(results[1]['status'], 'rejected');
        expect(results[1]['reason'], 'invalid_payload');
        expect(results[1]['sig_status'], 'ok');
        expect(rows(answer, 'unreadable'), isEmpty);
        expect(sync.errors, hasLength(2));

        final Map<String, Object?> again = post(<Object?>[sn.toWire()]);
        expect(rows(again, 'results').single['duplicate'], isTrue);

        // An unusable line under a number the server already holds
        final Map<String, Object?> squatter = op(at: 1)..remove('staff_id');
        final Map<String, Object?> third = post(<Object?>[
          sign(squatter, key, sb.mac).toWire(),
        ]);
        expect(rows(third, 'results'), isEmpty);
        expect(rows(third, 'unreadable').single['seq'], 1);
      },
    );
  });
}
