import 'dart:io';
import 'dart:math';

import 'package:pos_sync/pos_sync.dart';
import 'package:test/test.dart';

void main() {
  DateTime now = DateTime.utc(2026, 10, 22, 13);
  DateTime clock() => now;

  // One random source for the whole run, as the device's Random.secure would be
  final Random random = Random(7);
  Future<SyncStore> openStore(SyncStorage storage) =>
      SyncStore.open(storage, clock: clock, random: random);

  group('durable queue', () {
    test('an operation written and never sent survives a restart (power cut before sending)', () async {
      final MemorySyncStorage disk = MemorySyncStorage();
      final SyncStore store = await openStore(disk);
      final SyncOperation a = await store.enqueue(
        SyncOpType.orderOpen,
        <String, Object?>{
          'order': 'o-1',
          'local_number': store.nextLocalNumber('K1'),
        },
        staffId: 3,
      );
      final SyncOperation b = await store.enqueue(
        SyncOpType.orderRound,
        <String, Object?>{'order': 'o-1'},
        staffId: 3,
      );
      // No send, no close: the app is killed here

      final SyncStore reopened = await openStore(disk);
      expect(reopened.pending.map((SyncOperation o) => o.opId), <String>[
        a.opId,
        b.opId,
      ]);
      expect(reopened.pending.map((SyncOperation o) => o.seq), <int>[1, 2]);
      expect(reopened.lastSeq, 2);
      expect(reopened.pending.first.payload['local_number'], 'K1-001');

      // Numbering goes on, never repeats
      final SyncOperation c = await reopened.enqueue(
        SyncOpType.orderOpen,
        <String, Object?>{
          'order': 'o-2',
          'local_number': reopened.nextLocalNumber('K1'),
        },
        staffId: 3,
      );
      expect(c.seq, 3);
      expect(c.payload['local_number'], 'K1-002');
      expect(c.opId, isNot(a.opId));
    });

    test(
      'a line torn by a power cut is dropped and does not swallow the next one',
      () async {
        final MemorySyncStorage disk = MemorySyncStorage();
        final SyncStore store = await openStore(disk);
        await store.enqueue(SyncOpType.orderOpen, <String, Object?>{
          'order': 'o-1',
        }, staffId: 3);
        disk.tearNextAppend = true;
        await expectLater(
          store.enqueue(SyncOpType.orderRound, <String, Object?>{
            'order': 'o-1',
          }, staffId: 3),
          throwsA(isA<SyncStorageCrash>()),
        );

        // Restart: the half-written round is gone (it was never confirmed to the cashier), the open is there
        final SyncStore reopened = await openStore(disk);
        expect(reopened.pending.map((SyncOperation o) => o.type), <String>[
          SyncOpType.orderOpen,
        ]);
        expect(
          disk.tornTail,
          isNull,
          reason: 'the torn tail is cut before anything is appended',
        );

        final SyncOperation next = await reopened.enqueue(
          SyncOpType.orderSend,
          <String, Object?>{'order': 'o-1'},
          staffId: 3,
        );
        expect(next.seq, 2);
        final SyncStore again = await openStore(disk);
        expect(again.pending.map((SyncOperation o) => o.type), <String>[
          SyncOpType.orderOpen,
          SyncOpType.orderSend,
        ]);
      },
    );

    test('a write that fails leaves nothing behind', () async {
      final MemorySyncStorage disk = MemorySyncStorage();
      final SyncStore store = await openStore(disk);
      disk.failNextAppend = true;
      await expectLater(
        store.enqueue(SyncOpType.orderOpen, <String, Object?>{
          'order': 'o-1',
        }, staffId: 3),
        throwsA(isA<SyncStorageCrash>()),
      );
      expect(store.pendingCount, 0);
      expect((await openStore(disk)).pendingCount, 0);
    });

    test(
      'answers take operations off the queue, keep errors and the server ids',
      () async {
        final MemorySyncStorage disk = MemorySyncStorage();
        final SyncStore store = await openStore(disk);
        final SyncOperation open = await store.enqueue(
          SyncOpType.orderOpen,
          <String, Object?>{'order': 'client-1'},
          staffId: 3,
        );
        final SyncOperation bill = await store.enqueue(
          SyncOpType.billCreate,
          <String, Object?>{'order': 'client-1', 'bill': 'bill-1'},
          staffId: 3,
        );
        final SyncOperation pay = await store.enqueue(
          SyncOpType.billPay,
          <String, Object?>{'order': 'client-1', 'bill': 'bill-1'},
          staffId: 3,
        );

        await store.applyResults(<SyncResult>[
          SyncResult(
            opId: open.opId,
            seq: 1,
            status: SyncOpStatus.applied,
            refs: const <String, Object?>{'order_uuid': 'srv-1'},
          ),
          SyncResult(
            opId: bill.opId,
            seq: 2,
            status: SyncOpStatus.applied,
            refs: const <String, Object?>{'bill_uuid': 'srv-bill'},
          ),
          SyncResult(
            opId: pay.opId,
            seq: 3,
            status: SyncOpStatus.conflict,
            reason: 'underpaid',
            message: 'Kam',
          ),
        ]);

        expect(store.pendingCount, 0);
        expect(store.resolve('client-1'), 'srv-1');
        expect(store.resolve('bill-1'), 'srv-bill');
        expect(store.errors.single.reason, 'underpaid');

        final SyncStore reopened = await openStore(disk);
        expect(reopened.pendingCount, 0);
        expect(reopened.resolve('client-1'), 'srv-1');
        expect(reopened.errors.single.opId, pay.opId);
        expect(reopened.lastSeq, 3);
      },
    );

    test(
      'an answer for an operation already answered changes nothing',
      () async {
        final SyncStore store = await openStore(MemorySyncStorage());
        final SyncOperation a = await store.enqueue(
          SyncOpType.orderOpen,
          <String, Object?>{'order': 'o'},
          staffId: 3,
        );
        final SyncResult r = SyncResult(
          opId: a.opId,
          seq: 1,
          status: SyncOpStatus.applied,
        );
        await store.applyResults(<SyncResult>[r]);
        await store.applyResults(<SyncResult>[r]);
        expect(store.answered, hasLength(1));
      },
    );

    test(
      'compaction keeps the queue, the counters and the server ids',
      () async {
        final MemorySyncStorage disk = MemorySyncStorage();
        final SyncStore store = await openStore(disk);
        for (var i = 0; i < SyncStore.compactAfterLines; i++) {
          final SyncOperation o = await store.enqueue(
            SyncOpType.orderOpen,
            <String, Object?>{
              'order': 'o-$i',
              'local_number': store.nextLocalNumber('K1'),
            },
            staffId: 3,
          );
          await store.applyResults(<SyncResult>[
            SyncResult(
              opId: o.opId,
              seq: o.seq,
              status: SyncOpStatus.applied,
              refs: <String, Object?>{'order_uuid': 's-$i'},
            ),
          ]);
        }
        await store.markReloaded();
        final SyncOperation waiting = await store.enqueue(
          SyncOpType.orderRound,
          <String, Object?>{'order': 'o-1'},
          staffId: 3,
        );
        expect(disk.lines.length, lessThan(10));

        final SyncStore reopened = await openStore(disk);
        expect(reopened.pending.single.opId, waiting.opId);
        expect(reopened.lastSeq, SyncStore.compactAfterLines + 1);
        expect(reopened.resolve('o-799'), 's-799');
        expect(reopened.nextLocalNumber('K1'), 'K1-801');
      },
    );

    test('the local number never goes below the server\'s next one', () async {
      final SyncStore store = await openStore(MemorySyncStorage());
      expect(store.nextLocalNumber('K2', serverNext: 18), 'K2-018');
      expect(store.nextLocalNumber('K2', serverNext: 18), 'K2-019');
    });
  });

  group('copies', () {
    test(
      'a copy keeps when it was taken and the seq it already includes',
      () async {
        final MemorySyncStorage disk = MemorySyncStorage();
        final SyncStore store = await openStore(disk);
        await store.enqueue(SyncOpType.orderOpen, <String, Object?>{
          'order': 'o',
        }, staffId: 3);
        await store.putDoc('catalog:pos_hall', <String, Object?>{
          'menu_version': 'm1',
        });
        now = now.add(const Duration(hours: 3));

        final SyncStore reopened = await openStore(disk);
        final SyncDoc doc = reopened.doc('catalog:pos_hall')!;
        expect(doc.map!['menu_version'], 'm1');
        expect(doc.seq, 1);
        expect(doc.age(now), const Duration(hours: 3));
        expect(reopened.operationsAfter(doc.seq), isEmpty);
      },
    );

    test('the last contact is remembered across restarts', () async {
      final MemorySyncStorage disk = MemorySyncStorage();
      final SyncStore store = await openStore(disk);
      await store.markContact();
      expect((await openStore(disk)).lastContactAt, now);
    });
  });

  test('the file storage writes through fsync and survives a reopen', () async {
    final Directory dir = await Directory.systemTemp.createTemp(
      'pos_sync_test',
    );
    addTearDown(() => dir.delete(recursive: true));
    final FileSyncStorage disk = FileSyncStorage(dir);
    final SyncStore store = await openStore(disk);
    final SyncOperation a = await store.enqueue(
      SyncOpType.orderOpen,
      <String, Object?>{'order': 'o-1', 'local_number': 'K1-001'},
      staffId: 3,
    );
    await store.putDoc('bootstrap', <String, Object?>{'menu_version': 'm2'});
    // A torn tail as a power cut would leave it
    await File('${dir.path}/journal.log')
        .writeAsString('deadbeef {"k":"op","op":{"op', mode: FileMode.append);
    await disk.close();

    final FileSyncStorage again = FileSyncStorage(dir);
    final SyncStore reopened = await openStore(again);
    expect(reopened.pending.single.opId, a.opId);
    expect(reopened.doc('bootstrap')!.map!['menu_version'], 'm2');
    await reopened.enqueue(SyncOpType.orderRound, <String, Object?>{
      'order': 'o-1',
    }, staffId: 3);
    await again.close();
    expect((await openStore(FileSyncStorage(dir))).pendingCount, 2);
  });

  group('writes that come at the same time', () {
    Future<Directory> tempDir() async {
      final Directory dir = await Directory.systemTemp.createTemp(
        'pos_sync_race',
      );
      addTearDown(() => dir.delete(recursive: true));
      return dir;
    }

    List<String> seqs(SyncStore s) => <String>[
      for (final SyncOperation o in s.pending) '${o.seq}:${o.payload['order']}',
    ];

    test('two operations queued at once get their own seq each', () async {
      final SyncStore store = await openStore(MemorySyncStorage());
      final List<SyncOperation> ops = await Future.wait(<Future<SyncOperation>>[
        store.enqueue(SyncOpType.orderOpen, <String, Object?>{
          'order': 'a',
        }, staffId: 3),
        store.enqueue(SyncOpType.orderOpen, <String, Object?>{
          'order': 'b',
        }, staffId: 3),
      ]);
      expect(ops.map((SyncOperation o) => o.seq), <int>[1, 2]);
      expect(store.lastSeq, 2);
    });

    test('queueing and the sender\'s answer at once on a fresh file: both reach the disk', () async {
      final Directory dir = await tempDir();
      final FileSyncStorage first = FileSyncStorage(dir);
      await (await openStore(first)).enqueue(
        SyncOpType.orderOpen,
        <String, Object?>{'order': 'o1'},
        staffId: 3,
      );
      await first.close();

      // Restart (journal not open yet); the network is back: the sender's answer comes while the cashier pays
      final FileSyncStorage disk = FileSyncStorage(dir);
      final SyncStore store = await openStore(disk);
      final String opened = store.pending.single.opId;
      await Future.wait(<Future<void>>[
        store.enqueue(SyncOpType.billPay, <String, Object?>{
          'order': 'o1',
          'bill': 'b1',
        }, staffId: 3),
        store.applyResults(<SyncResult>[
          SyncResult(opId: opened, seq: 1, status: SyncOpStatus.applied),
        ]),
      ]);
      expect(store.pending.map((SyncOperation o) => o.type), <String>[
        SyncOpType.billPay,
      ]);
      await disk.close();

      final SyncStore again = await openStore(FileSyncStorage(dir));
      expect(
        again.pending.map((SyncOperation o) => '${o.seq}:${o.type}'),
        <String>['2:${SyncOpType.billPay}'],
      );
      expect(again.answered.single.opId, opened);
    });

    test('many operations and answers at once on a file: seq unique, nothing lost after a restart', () async {
      final Directory dir = await tempDir();
      final FileSyncStorage disk = FileSyncStorage(dir);
      final SyncStore store = await openStore(disk);
      await store.enqueue(SyncOpType.orderOpen, <String, Object?>{
        'order': 'first',
      }, staffId: 3);
      final String firstId = store.pending.single.opId;
      await Future.wait(<Future<void>>[
        store.enqueue(SyncOpType.orderRound, <String, Object?>{
          'order': 'a',
        }, staffId: 3),
        store.applyResults(<SyncResult>[
          SyncResult(opId: firstId, seq: 1, status: SyncOpStatus.applied),
        ]),
        store.enqueue(SyncOpType.orderRound, <String, Object?>{
          'order': 'x',
        }, staffId: 3),
        store.putDoc('order:a', <String, Object?>{'version': 1}),
        store.enqueue(SyncOpType.orderRound, <String, Object?>{
          'order': 'y',
        }, staffId: 3),
        store.markReloaded(),
        store.markContact(),
      ]);
      expect(seqs(store), <String>['2:a', '3:x', '4:y']);
      await disk.close();

      final SyncStore again = await openStore(FileSyncStorage(dir));
      expect(seqs(again), <String>['2:a', '3:x', '4:y']);
      expect(again.lastSeq, 4);
    });

    test('forgetting the terminal empties the queue and copies but keeps the seq going', () async {
      final MemorySyncStorage disk = MemorySyncStorage();
      final SyncStore store = await openStore(disk);
      await store.enqueue(SyncOpType.orderOpen, <String, Object?>{
        'order': 'o',
        'local_number': 'K1-004',
      }, staffId: 3);
      await store.putDoc('order:o', <String, Object?>{'version': 1});
      await store.clear();
      expect(store.pending, isEmpty);
      expect(store.docKeys(), isEmpty);

      final SyncStore reopened = await openStore(disk);
      expect(reopened.pending, isEmpty);
      expect(reopened.docKeys(), isEmpty);
      // The server refuses a seq the device already used (seq_reused)
      final SyncOperation next = await reopened.enqueue(
        SyncOpType.orderOpen,
        <String, Object?>{'order': 'p'},
        staffId: 3,
      );
      expect(next.seq, 2);
      expect(reopened.nextLocalNumber('K1'), 'K1-005');
    });
  });
}
