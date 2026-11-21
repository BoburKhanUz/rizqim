import 'dart:convert';
import 'dart:io';

import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'offline_test.dart' show OfflineHarness;

/// Wave M5 stage S6-b: the scenarios of docs/scale/M5_DESIGN.md 7.1 that the stage tests did not prove whole (the
/// table "where each scenario lives" is in that section): 6 (the till's side of the rescue file) and 7 (a journal line
/// changed by hand, observe and enforce).
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

  Future<void> start() async {
    h = await OfflineHarness.create();
    FakeCashierBackend(h.state);
    sync = FakeSyncBackend(h.state);
    h.state.offlineSigning = true;
    h.state.advertiseSigningKid = true;
    h.state.openShift();
    await h.signIn();
  }

  tearDown(() => h.pos.dispose());

  Future<void> settle() async {
    for (var i = 0; i < 20; i++) {
      await Future<void>.delayed(Duration.zero);
    }
  }

  PosLineInput line(int variantId) => PosLineInput(
    variantUuid: FakePosState.variantUuid(variantId),
    quantity: PosQuantity.parse('1'),
  );

  String shift() => h.state.shift!['uuid']! as String;

  Future<PosBill> billOf(String order) async {
    final PosOrder o = await h.pos.orders.get(order);
    final PosBilling billing = await h.pos.billing.createBills(
      order,
      version: o.version,
    );
    return billing.bills.single;
  }

  Map<String, Object?> resultOf(String type) => sync.results.values.firstWhere(
    (Map<String, Object?> r) => r['type'] == type,
  );

  group(
    'scenario 6: a 401 on the terminal token with 5 operations pending',
    () {
      test(
        'nothing is deleted; the rescue file holds all 5, readable under the '
        'till\'s key; the server was sent nothing',
        () async {
          await start();
          final String room = h.state.seedOrder(tableId: 1, guests: 2);
          await h.warmUp();
          await h.goOffline();
          final PosBill bill = await billOf(room);
          await h.pos.billing.pay(
            bill.uuid,
            tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
            shiftUuid: shift(),
          );
          for (var i = 0; i < 2; i++) {
            await h.pos.orders.open(
              channel: PosChannel.takeaway,
              items: <PosLineInput>[line(1)],
              send: true,
            );
          }
          final List<SyncOperation> queued = h.offline.store.pending;
          expect(queued, hasLength(5));
          expect(queued.every((SyncOperation o) => o.seal != null), isTrue);
          final List<String> ids = <String>[
            for (final SyncOperation o in queued) o.opId,
          ];

          // The owner disconnects the till on the server; the next sync meets the 401
          h.state.paired = false;
          h.adapter.offline = false;
          await h.offline.syncNow();
          await settle();
          expect(h.client.auth.terminal, isNull);
          expect(sync.received, isEmpty);
          final String disk = <String>[
            ...h.disk.lines,
            ...h.disk.docs.values,
          ].join('\n');
          for (final String id in ids) {
            expect(disk, contains(id), reason: 'operation $id was deleted');
          }
          final PosOrphanQueue orphan = h.offline.orphans.single;
          expect(orphan.operations.map((SyncOperation o) => o.opId), ids);

          // The till is paired again (a new key); "Navbatni faylga saqlash": the set-aside queue goes into a file sealed with the till's key
          await h.client.auth.pairTerminal('ABCD-EFGH');
          await h.client.auth.signInStaff(userId: 3, pin: h.state.pins[3]!);
          await h.pos.session.loadBootstrap();
          expect(h.offline.orphans, hasLength(1));
          final Directory dir = await Directory.systemTemp.createTemp(
            'rzq_s6_',
          );
          addTearDown(() => dir.delete(recursive: true));
          final SyncExporter exporter = SyncExporter(
            store: h.offline.store,
            keyProvider: h.pos.syncKeys!.current,
            enabled: () => true,
            setAside: () => <SyncExportQueue>[
              for (final PosOrphanQueue o in h.offline.orphans)
                SyncExportQueue(
                  device: o.deviceUuid!,
                  operations: o.operations,
                ),
            ],
            directory: () => dir,
            clock: () => h.clock.now,
          );
          final SyncExportResult result = await exporter.export();
          expect(result.operations, 5);
          expect(result.payments, 1);
          // (every line carries the signed text as it was queued; the ids are read from that text)
          final List<String> inFile = <String>[
            for (final f in result.files)
              for (final String l in f.text.split('\n').skip(1))
                if (l.contains('"signed"'))
                  (jsonDecode(
                        (jsonDecode(l) as Map<String, Object?>)['signed']!
                            as String,
                      ) as Map<String, Object?>)['op_id']!
                      as String,
          ];
          expect(inFile, unorderedEquals(ids));
          expect(
            SyncExport.verify(
              File(result.paths.first).readAsBytesSync(),
              h.pos.syncKeys!.current()!,
            ).ok,
            isTrue,
          );
        },
      );
    },
  );

  group('scenario 7: a journal line changed by hand', () {
    /// A cash sale queued offline (open, bill, pay, close), the app stopped; the amount of the payment on the disk is
    /// changed and the line's checksum made right again. Returns the queue as it was.
    Future<List<SyncOperation>> sellThenEditPayment() async {
      await h.warmUp();
      await h.goOffline();
      await h.offline.sender.stop();
      final PosOrder order = await h.pos.orders.open(
        channel: PosChannel.takeaway,
        items: <PosLineInput>[line(1)],
        send: true,
      );
      final PosBill bill = await billOf(order.uuid);
      await h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: shift(),
      );
      final List<SyncOperation> queued = h.offline.store.pending;
      await h.pos.dispose();
      final SyncOperation pay = queued.firstWhere(
        (SyncOperation o) => o.type == SyncOpType.billPay,
      );
      final int at = h.disk.lines.indexWhere(
        (String l) => _record(l)['raw'] == pay.seal!.raw,
      );
      expect(at, isNonNegative);
      final Map<String, Object?> edited = _record(h.disk.lines[at]);
      String lower(String s) =>
          s.replaceAll(RegExp(r'"amount":"\d+\.\d+"'), '"amount":"1000.00"');
      expect(lower(edited['raw']! as String), isNot(edited['raw']));
      edited['raw'] = lower(edited['raw']! as String);
      edited['op'] = jsonDecode(lower(jsonEncode(edited['op'])));
      h.disk.lines[at] = _line(edited);
      return queued;
    }

    Future<void> restart() async {
      h = await OfflineHarness.create(
        disk: h.disk,
        adapter: h.adapter,
        clock: h.clock,
        keys: h.keys,
        restore: true,
      );
      h.adapter.offline = false;
      await h.client.auth.signInStaff(userId: 3, pin: h.state.pins[3]!);
      expect((await h.offline.syncNow()).drained, isTrue);
      await settle();
    }

    test('observe: the payment is applied, sig_status bad, the manager is '
        'told; the lines before and after are ok', () async {
      await start();
      await sellThenEditPayment();
      await restart();
      // (the server applies what the SIGNED text says: the payment is recorded with the changed amount, so the bill is
      // underpaid for the manager; the money is never dropped)
      expect(resultOf(SyncOpType.billPay)['status'], 'conflict');
      expect(resultOf(SyncOpType.billPay)['reason'], 'underpaid');
      expect(resultOf(SyncOpType.billPay)['sig_status'], 'bad');
      expect(resultOf(SyncOpType.orderOpen)['sig_status'], 'ok');
      expect(sync.sigStatus.values, contains('bad'));
      // (the one conflict is on the manager's list; nothing else)
      expect(sync.errors, hasLength(1));
    });

    test('enforce: the payment is recorded and flagged payment_unverified, '
        'never dropped', () async {
      await start();
      sync.enforceSignatures = true;
      await sellThenEditPayment();
      await restart();
      final Map<String, Object?> pay = resultOf(SyncOpType.billPay);
      expect(pay['sig_status'], 'bad');
      expect(pay['status'], 'conflict');
      expect(pay['reason'], 'payment_unverified');
      expect(
        sync.results.values.where(
          (Map<String, Object?> r) => r['status'] == 'rejected',
        ),
        isEmpty,
      );
    });

    test('a line removed from the journal: the one after it is chain_gap, '
        'applied all the same', () async {
      await start();
      await h.warmUp();
      await h.goOffline();
      await h.offline.sender.stop();
      final PosOrder order = await h.pos.orders.open(
        channel: PosChannel.takeaway,
        items: <PosLineInput>[line(1)],
        send: true,
      );
      final PosBill bill = await billOf(order.uuid);
      await h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: shift(),
      );
      final List<SyncOperation> queued = h.offline.store.pending;
      await h.pos.dispose();
      final SyncOperation gone = queued.firstWhere(
        (SyncOperation o) => o.type == SyncOpType.billCreate,
      );
      h.disk.lines.removeWhere(
        (String l) => _record(l)['raw'] == gone.seal!.raw,
      );
      await restart();
      final SyncOperation next = queued[queued.indexOf(gone) + 1];
      expect(sync.sigStatus[next.opId], 'chain_gap');
      expect(sync.sigStatus[queued.first.opId], 'ok');
      // (its bill was never created on the server: the money is recorded unallocated, not dropped)
      expect(resultOf(SyncOpType.billPay)['status'], 'conflict');
      expect(resultOf(SyncOpType.billPay)['reason'], 'payment_unallocated');
    });
  });
}
