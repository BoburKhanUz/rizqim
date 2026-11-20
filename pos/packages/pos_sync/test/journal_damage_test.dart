import 'dart:io';
import 'dart:math';

import 'package:pos_sync/pos_sync.dart';
import 'package:test/test.dart';

/// A real journal file whose next append "fails half-way" on demand: half of the line reaches the disk, the caller
/// gets an error and the app keeps running (disk full, I/O error).
class _FailingFileStorage implements SyncStorage {
  _FailingFileStorage(this.dir) : inner = FileSyncStorage(dir);

  final Directory dir;
  final FileSyncStorage inner;
  bool tearNextAppend = false;

  @override
  Future<void> appendLine(String line) async {
    if (tearNextAppend) {
      tearNextAppend = false;
      // The storage's own handle is closed first, so that its next write lands after the partial bytes (as it would
      // after a short write on that handle)
      await inner.close();
      await File('${dir.path}/journal.log').writeAsString(
        line.substring(0, line.length ~/ 2),
        mode: FileMode.append,
        flush: true,
      );
      throw const FileSystemException('No space left on device');
    }
    await inner.appendLine(line);
  }

  @override
  Future<List<String>> readLines() => inner.readLines();

  @override
  Future<void> replaceLines(List<String> lines) => inner.replaceLines(lines);

  @override
  Future<String?> readDoc(String key) => inner.readDoc(key);

  @override
  Future<void> writeDoc(String key, String content) =>
      inner.writeDoc(key, content);

  @override
  Future<void> deleteDoc(String key) => inner.deleteDoc(key);

  @override
  Future<List<String>> docKeys() => inner.docKeys();
}

void main() {
  final DateTime now = DateTime.utc(2026, 11, 19, 13);
  final Random random = Random(11);
  Future<SyncStore> openStore(SyncStorage storage) =>
      SyncStore.open(storage, clock: () => now, random: random);

  Future<SyncOperation> put(
    SyncStore store,
    String type, [
    String id = 'o-1',
  ]) => store.enqueue(type, <String, Object?>{'order': id}, staffId: 3);

  Future<Directory> tempDir() async {
    final Directory dir = await Directory.systemTemp.createTemp(
      'pos_sync_damage',
    );
    addTearDown(() => dir.delete(recursive: true));
    return dir;
  }

  group('M5 bug 3: a failed append while the app keeps running', () {
    test(
      'two good operations written after a failed append survive a restart',
      () async {
        final MemorySyncStorage disk = MemorySyncStorage();
        final SyncStore store = await openStore(disk);
        final SyncOperation open = await put(store, SyncOpType.orderOpen);
        disk.tearNextAppend = true;
        await expectLater(
          put(store, SyncOpType.orderRound),
          throwsA(isA<SyncStorageCrash>()),
        );
        // No restart: the cashier goes on, and both of these are confirmed ("Saqlandi")
        final SyncOperation bill = await put(store, SyncOpType.billCreate);
        final SyncOperation pay = await put(store, SyncOpType.billPay);
        expect(store.pending.map((SyncOperation o) => o.opId), <String>[
          open.opId,
          bill.opId,
          pay.opId,
        ]);

        final SyncStore reopened = await openStore(disk);
        expect(reopened.pending.map((SyncOperation o) => o.opId), <String>[
          open.opId,
          bill.opId,
          pay.opId,
        ]);
        expect(reopened.pending.map((SyncOperation o) => o.seq), <int>[
          1,
          2,
          3,
        ]);
        expect(reopened.lastSeq, 3);
      },
    );

    test('the same on a real file', () async {
      final Directory dir = await tempDir();
      final _FailingFileStorage disk = _FailingFileStorage(dir);
      final SyncStore store = await openStore(disk);
      final SyncOperation open = await put(store, SyncOpType.orderOpen);
      disk.tearNextAppend = true;
      await expectLater(
        put(store, SyncOpType.orderRound),
        throwsA(isA<FileSystemException>()),
      );
      final SyncOperation bill = await put(store, SyncOpType.billCreate);
      final SyncOperation pay = await put(store, SyncOpType.billPay);
      await disk.inner.close();

      final FileSyncStorage again = FileSyncStorage(dir);
      final SyncStore reopened = await openStore(again);
      await again.close();
      expect(reopened.pending.map((SyncOperation o) => o.opId), <String>[
        open.opId,
        bill.opId,
        pay.opId,
      ]);
    });
  });

  group('a line that reached the disk but was not confirmed', () {
    test('the failed operation does not come back beside the one that took its seq', () async {
      final MemorySyncStorage disk = MemorySyncStorage();
      final SyncStore store = await openStore(disk);
      final SyncOperation open = await put(store, SyncOpType.orderOpen);
      // The whole line is written, the fsync fails: the cashier sees an error and pays again
      disk.landNextAppendThenFail = true;
      await expectLater(
        put(store, SyncOpType.billPay),
        throwsA(isA<SyncStorageCrash>()),
      );
      final SyncOperation pay = await put(store, SyncOpType.billPay);
      expect(pay.seq, 2);

      final SyncStore reopened = await openStore(disk);
      expect(reopened.pending.map((SyncOperation o) => o.opId), <String>[
        open.opId,
        pay.opId,
      ], reason: 'one payment, not two');
      expect(reopened.pending.map((SyncOperation o) => o.seq), <int>[1, 2]);
    });
  });

  group('M5 bug 3: one unreadable line in the middle', () {
    test('a flipped byte in a middle line: the later lines survive', () async {
      final MemorySyncStorage disk = MemorySyncStorage();
      final SyncStore store = await openStore(disk);
      final SyncOperation a = await put(store, SyncOpType.orderOpen);
      await put(store, SyncOpType.orderRound);
      final SyncOperation c = await put(store, SyncOpType.billCreate);
      final SyncOperation d = await put(store, SyncOpType.billPay);
      final String line = disk.lines[1];
      disk.lines[1] = line.replaceFirst('"order"', '"ordep"');

      final SyncStore reopened = await openStore(disk);
      expect(reopened.pending.map((SyncOperation o) => o.opId), <String>[
        a.opId,
        c.opId,
        d.opId,
      ]);
      expect(reopened.lastSeq, 4);
    });

    test('the same on a real file', () async {
      final Directory dir = await tempDir();
      final FileSyncStorage disk = FileSyncStorage(dir);
      final SyncStore store = await openStore(disk);
      final SyncOperation a = await put(store, SyncOpType.orderOpen);
      await put(store, SyncOpType.orderRound);
      final SyncOperation c = await put(store, SyncOpType.billPay);
      await disk.close();
      final File journal = File('${dir.path}/journal.log');
      final List<int> bytes = await journal.readAsBytes();
      // One bit of one byte in the second line
      final int second = bytes.indexOf(10) + 20;
      bytes[second] ^= 0x01;
      await journal.writeAsBytes(bytes, flush: true);

      final FileSyncStorage again = FileSyncStorage(dir);
      final SyncStore reopened = await openStore(again);
      await again.close();
      expect(reopened.pending.map((SyncOperation o) => o.opId), <String>[
        a.opId,
        c.opId,
      ]);
    });
  });

  group('what is reported', () {
    test('an unreadable middle line is reported, and still after a compaction and a restart', () async {
      final MemorySyncStorage disk = MemorySyncStorage();
      final SyncStore store = await openStore(disk);
      await put(store, SyncOpType.orderOpen);
      await put(store, SyncOpType.orderRound);
      final SyncOperation c = await put(store, SyncOpType.billPay);
      expect(store.damaged, isEmpty);
      final String bad = disk.lines[1].replaceFirst('"order"', '"ordep"');
      disk.lines[1] = bad;

      final SyncStore reopened = await openStore(disk);
      expect(reopened.damaged, hasLength(1));
      expect(reopened.damaged.single.line, 2);
      expect(reopened.damaged.single.text, bad);
      expect(reopened.damaged.single.foundAt, now);
      expect(disk.lines, contains(bad), reason: 'the file is not rewritten');

      // The queue drains and the journal is compacted: the report stays
      await reopened.applyResults(<SyncResult>[
        for (final SyncOperation o in reopened.pending)
          SyncResult(opId: o.opId, seq: o.seq, status: SyncOpStatus.applied),
      ]);
      await reopened.markReloaded();
      expect(disk.lines, isNot(contains(bad)));
      final SyncStore again = await openStore(disk);
      expect(again.pending, isEmpty);
      expect(again.lastSeq, c.seq);
      expect(again.damaged.single.line, 2);
      expect(again.damaged.single.text, bad);

      // Forgetting the terminal clears it with everything else
      await again.clear();
      expect((await openStore(disk)).damaged, isEmpty);
    });

    test(
      'a torn last line after a power cut is dropped, not reported',
      () async {
        final MemorySyncStorage disk = MemorySyncStorage();
        final SyncStore store = await openStore(disk);
        final SyncOperation a = await put(store, SyncOpType.orderOpen);
        disk.tearNextAppend = true;
        await expectLater(
          put(store, SyncOpType.billPay),
          throwsA(isA<SyncStorageCrash>()),
        );

        final SyncStore reopened = await openStore(disk);
        expect(reopened.pending.single.opId, a.opId);
        expect(reopened.damaged, isEmpty);
        expect(disk.tornTail, isNull);
        expect(disk.lines, hasLength(1));
      },
    );

    test('a failed append the app fenced off is not damage, whatever part of it reached the disk', () async {
      for (final String how in <String>['nothing', 'half', 'whole']) {
        final MemorySyncStorage disk = MemorySyncStorage();
        final SyncStore store = await openStore(disk);
        final SyncOperation a = await put(store, SyncOpType.orderOpen);
        switch (how) {
          case 'nothing':
            disk.failNextAppend = true;
          case 'half':
            disk.tearNextAppend = true;
          default:
            disk.landNextAppendThenFail = true;
        }
        await expectLater(
          put(store, SyncOpType.orderRound),
          throwsA(isA<SyncStorageCrash>()),
        );
        final SyncOperation b = await put(store, SyncOpType.billPay);

        final SyncStore reopened = await openStore(disk);
        expect(reopened.pending.map((SyncOperation o) => o.opId), <String>[
          a.opId,
          b.opId,
        ], reason: how);
        expect(reopened.damaged, isEmpty, reason: how);
        // And the journal goes on as usual
        final SyncOperation c = await put(reopened, SyncOpType.orderClose);
        expect(c.seq, 3, reason: how);
        expect((await openStore(disk)).pendingCount, 3, reason: how);
      }
    });

    test('failed appends in a row, then a good one', () async {
      final MemorySyncStorage disk = MemorySyncStorage();
      final SyncStore store = await openStore(disk);
      final SyncOperation a = await put(store, SyncOpType.orderOpen);
      disk.tearNextAppend = true;
      await expectLater(
        put(store, SyncOpType.orderRound),
        throwsA(isA<SyncStorageCrash>()),
      );
      // The record that fences the first failure off tears as well
      disk.tearNextAppend = true;
      await expectLater(
        put(store, SyncOpType.orderRound),
        throwsA(isA<SyncStorageCrash>()),
      );
      final SyncOperation b = await put(store, SyncOpType.billCreate);
      expect(b.seq, 2);
      // And once more: this time the fence is written and the operation after it tears
      disk.tearNextAppend = true;
      await expectLater(
        put(store, SyncOpType.orderRound),
        throwsA(isA<SyncStorageCrash>()),
      );
      final SyncOperation c = await put(store, SyncOpType.billPay);
      expect(c.seq, 3);

      final SyncStore reopened = await openStore(disk);
      expect(reopened.pending.map((SyncOperation o) => o.opId), <String>[
        a.opId,
        b.opId,
        c.opId,
      ]);
      expect(reopened.damaged, isEmpty);
    });

    test(
      'a failed answer write: the operation stays queued and is answered later',
      () async {
        final MemorySyncStorage disk = MemorySyncStorage();
        final SyncStore store = await openStore(disk);
        final SyncOperation a = await put(store, SyncOpType.billPay);
        final SyncResult ok = SyncResult(
          opId: a.opId,
          seq: a.seq,
          status: SyncOpStatus.applied,
        );
        disk.landNextAppendThenFail = true;
        await expectLater(
          store.applyResults(<SyncResult>[ok]),
          throwsA(isA<SyncStorageCrash>()),
        );
        expect(store.pending.single.opId, a.opId);
        final SyncOperation b = await put(store, SyncOpType.orderClose);

        // The unconfirmed answer is void: the payment is still queued (the server answers a resend by op_id)
        final SyncStore reopened = await openStore(disk);
        expect(reopened.pending.map((SyncOperation o) => o.opId), <String>[
          a.opId,
          b.opId,
        ]);
        await reopened.applyResults(<SyncResult>[ok]);
        expect((await openStore(disk)).pending.single.opId, b.opId);
      },
    );
  });
}
