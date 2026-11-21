import 'dart:convert';
import 'dart:io';

import 'package:crypto/crypto.dart';
import 'package:pos_sync/pos_sync.dart';
import 'package:test/test.dart';

/// Wave M5 S4-a (docs/scale/M5_DESIGN.md 5, layer 2): every journal line also goes to a second copy outside the
/// app's own folder. A failing copy never fails a sale; on start a journal that lost lines gets back, from the copy,
/// every operation whose signature verifies, and never loses a line of its own.
const String _device = 'a4c1e2f3-5b6d-4e7f-8a9b-0c1d2e3f4a5b';

/// Derived (never written): not a key-shaped literal.
SyncKey _key({int kid = 1, String device = _device}) => SyncKey(
  key: sha256.convert(utf8.encode('mirror-test/$device/$kid')).bytes,
  kid: kid,
  deviceUuid: device,
);

/// A storage whose every write fails (a mirror folder that is gone, a full USB disk).
class _BrokenStorage extends MemorySyncStorage {
  int attempts = 0;

  @override
  Future<void> appendLine(String line) async {
    attempts++;
    throw const FileSystemException('mirror gone');
  }

  @override
  Future<void> replaceLines(List<String> next) async {
    attempts++;
    throw const FileSystemException('mirror gone');
  }

  @override
  Future<List<String>> readLines() async =>
      throw const FileSystemException('mirror gone');

  @override
  Future<void> writeDoc(String key, String content) async =>
      throw const FileSystemException('mirror gone');
}

/// A journal whose compaction fails (the restore cannot be written).
class _NoReplace extends MemorySyncStorage {
  @override
  Future<void> replaceLines(List<String> next) async =>
      throw const FileSystemException('disk full');
}

Future<SyncStore> _open(SyncStorage s, {SyncKey? key}) => SyncStore.open(
  s,
  clock: () => DateTime.utc(2026, 11, 21, 9),
  keyProvider: () => key,
);

Future<void> _sell(SyncStore store, int n, {String amount = '10000.00'}) async {
  for (var i = 0; i < n; i++) {
    await store.enqueue(SyncOpType.billPay, <String, Object?>{
      'order': 'o$i',
      'tenders': <Object?>[
        <String, Object?>{'method': 'cash', 'amount': amount},
      ],
    }, staffId: 7);
  }
}

String _crc(String json) {
  var crc = 0xffffffff;
  for (final int b in utf8.encode(json)) {
    crc ^= b;
    for (var k = 0; k < 8; k++) {
      crc = (crc & 1) != 0 ? (crc >> 1) ^ 0xedb88320 : crc >> 1;
    }
  }
  return ((crc ^ 0xffffffff) & 0xffffffff).toRadixString(16).padLeft(8, '0');
}

Map<String, Object?> _record(String line) =>
    (jsonDecode(line.substring(9)) as Map).cast<String, Object?>();

String _line(Map<String, Object?> r) {
  final String json = jsonEncode(r);
  return '${_crc(json)} $json';
}

void main() {
  late MemorySyncStorage journal;
  late MemorySyncStorage copy;
  late MirroredSyncStorage mirrored;
  final SyncKey key = _key();
  DateTime now = DateTime.utc(2026, 11, 21, 9);

  setUp(() async {
    now = DateTime.utc(2026, 11, 21, 9);
    journal = MemorySyncStorage();
    copy = MemorySyncStorage();
    mirrored = MirroredSyncStorage(journal, copy, clock: () => now);
    // Before the store opens: nothing to compare yet, the copy is ready
    expect((await mirrored.restore(keys: <SyncKey>[key])).error, isNull);
  });

  group('writing', () {
    test('every journal line is also in the copy, a compaction too', () async {
      final SyncStore store = await _open(mirrored, key: key);
      await _sell(store, 3);
      expect(copy.lines, journal.lines);
      expect(journal.lines, hasLength(3));
      expect(mirrored.lastMirroredAt, now);
      expect(mirrored.mirrorError, isNull);
      await store.applyResults(<SyncResult>[
        SyncResult(
          opId: store.pending.first.opId,
          seq: 1,
          status: SyncOpStatus.applied,
        ),
      ]);
      await store.markReloaded(); // compacts
      expect(copy.lines, journal.lines);
      // Copies (docs) stay in the journal's own folder
      await store.putDoc('menu', <String, Object?>{'a': 1});
      expect(journal.docs.keys, contains('menu'));
      expect(copy.docs, isEmpty);
    });

    test(
      'a queue set aside at a disconnect is also in the copy\'s folder',
      () async {
        final SyncStore store = await _open(mirrored, key: key);
        await store.storage.writeDoc('orphan_1', '{"value":{}}');
        expect(copy.docs['orphan_1'], '{"value":{}}');
        await store.storage.deleteDoc('orphan_1');
        expect(copy.docs, isEmpty);
        expect(journal.docs, isEmpty);
      },
    );

    test('a failing copy never fails a sale; it is written whole again once '
        'it works', () async {
      final SyncStore store = await _open(mirrored, key: key);
      await _sell(store, 1);
      copy.failNextAppend = true;
      await _sell(store, 1); // does not throw
      expect(store.pendingCount, 2);
      expect(mirrored.mirrorError, isNotNull);
      expect(copy.lines, hasLength(1));
      // Tried again (not before a minute has passed): the copy is written whole
      now = now.add(const Duration(minutes: 2));
      await _sell(store, 1);
      expect(copy.lines, journal.lines);
      expect(mirrored.mirrorError, isNull);
      // A torn write in the copy is not glued to the next line either
      copy.tearNextAppend = true;
      await _sell(store, 1);
      expect(mirrored.mirrorError, isNotNull);
      now = now.add(const Duration(minutes: 2));
      await _sell(store, 1);
      expect(store.pendingCount, 5);
      expect(copy.lines, journal.lines);
      expect(copy.tornTail, isNull);
      expect(mirrored.mirrorError, isNull);
    });

    test(
      'a copy that is gone: every sale and compaction still goes on',
      () async {
        final _BrokenStorage broken = _BrokenStorage();
        final MirroredSyncStorage m = MirroredSyncStorage(
          journal,
          broken,
          clock: () => now,
        );
        final MirrorRestore r = await m.restore(keys: <SyncKey>[key]);
        expect(r.error, isNotNull);
        final SyncStore store = await _open(m, key: key);
        for (var i = 0; i < 5; i++) {
          await _sell(store, 1);
          now = now.add(const Duration(seconds: 5));
        }
        await store.markReloaded();
        expect(journal.lines, isNotEmpty);
        expect(m.mirrorError, isNotNull);
        expect(m.lastMirroredAt, isNull);
        // A broken copy is not hammered at every sale: at most once a minute
        expect(broken.attempts, lessThan(4));
      },
    );
  });

  group('restoring on start', () {
    Future<List<String>> mirroredLines(int sales) async {
      final SyncStore store = await _open(mirrored, key: key);
      await _sell(store, sales);
      return List<String>.of(journal.lines);
    }

    test(
      'a lost journal gets back every verified operation from the copy',
      () async {
        final List<String> lines = await mirroredLines(3);
        final SyncStore before = await _open(
          MemorySyncStorage()..lines.addAll(lines),
          key: key,
        );
        journal.lines.clear(); // "clear data", a wiped app folder
        final MirrorRestore r = await mirrored.restore(keys: <SyncKey>[key]);
        expect(r.restored, 3);
        expect(r.restoredPayments, 3);
        expect(r.kept, 0);
        expect(r.error, isNull);
        final SyncStore after = await _open(mirrored, key: key);
        expect(
          after.pending.map((SyncOperation o) => o.opId),
          before.pending.map((SyncOperation o) => o.opId),
        );
        expect(
          after.pending.map((SyncOperation o) => o.seal!.mac),
          before.pending.map((SyncOperation o) => o.seal!.mac),
        );
        // The chain goes on from the restored operations
        await _sell(after, 1);
        expect(after.pending.last.seal!.prev, before.pending.last.seal!.mac);
        expect(after.lastSeq, 4);
        expect(copy.lines, journal.lines);
      },
    );

    test(
      'a journal that lost its last lines keeps every line it has',
      () async {
        final List<String> lines = await mirroredLines(4);
        journal.lines.removeRange(2, 4);
        journal.tornTail = lines[2].substring(0, 20); // the power cut
        final List<String> had = await journal.readLines();
        final MirrorRestore r = await mirrored.restore(keys: <SyncKey>[key]);
        expect(r.restored, 2);
        for (final String l in had) {
          expect(journal.lines, contains(l));
        }
        final SyncStore store = await _open(mirrored, key: key);
        expect(store.pending.map((SyncOperation o) => o.seq), <int>[
          1,
          2,
          3,
          4,
        ]);
        expect(store.damaged, isEmpty);
      },
    );

    test('a damaged line in the journal comes back from the copy', () async {
      final List<String> lines = await mirroredLines(3);
      journal.lines[1] =
          '${lines[1].substring(0, 30)}X${lines[1].substring(31)}';
      final MirrorRestore r = await mirrored.restore(keys: <SyncKey>[key]);
      expect(r.restored, 1);
      final SyncStore store = await _open(mirrored, key: key);
      expect(store.pending.map((SyncOperation o) => o.seq), <int>[1, 2, 3]);
      // The damage is still reported (never hidden)
      expect(store.damaged, hasLength(1));
    });

    test('a line changed in the copy is never restored, but kept', () async {
      final List<String> lines = await mirroredLines(2);
      final Map<String, Object?> r2 = _record(lines[1]);
      // Another amount, the checksum made right again: only the signature tells
      r2['raw'] = (r2['raw']! as String).replaceAll('10000.00', '1.00');
      copy.lines[1] = _line(r2);
      journal.lines.clear();
      final MirrorRestore r = await mirrored.restore(keys: <SyncKey>[key]);
      expect(r.restored, 1);
      expect(r.kept, 1);
      expect(r.keptKey, isNotNull);
      final Map<String, Object?> kept =
          (jsonDecode(copy.docs[r.keptKey]!) as Map).cast<String, Object?>();
      expect(kept['lines'], <Object?>[_line(r2)]);
      final SyncStore store = await _open(mirrored, key: key);
      expect(store.pending.map((SyncOperation o) => o.seq), <int>[1]);
    });

    test(
      'unsigned lines and lines of another key are kept, not restored',
      () async {
        final SyncStore store = await _open(mirrored);
        await _sell(store, 1); // no key: unsigned
        final SyncStore signed = await _open(mirrored, key: _key(kid: 2));
        await _sell(signed, 1);
        journal.lines.clear();
        final MirrorRestore r = await mirrored.restore(keys: <SyncKey>[key]);
        expect(r.restored, 0);
        expect(r.kept, 2);
        expect(journal.lines, isEmpty);
        // The copy still has them as a kept document: nothing is gone
        expect(copy.docs[r.keptKey], isNotNull);
      },
    );

    test(
      'an operation the journal knows as answered is not restored',
      () async {
        final SyncStore store = await _open(mirrored, key: key);
        await _sell(store, 2);
        await store.applyResults(<SyncResult>[
          SyncResult(
            opId: store.pending.first.opId,
            seq: 1,
            status: SyncOpStatus.applied,
          ),
        ]);
        // The journal lost the first operation's line but kept the answer
        journal.lines.removeAt(0);
        final MirrorRestore r = await mirrored.restore(keys: <SyncKey>[key]);
        expect(r.restored, 0);
        final SyncStore after = await _open(mirrored, key: key);
        expect(after.pending.map((SyncOperation o) => o.seq), <int>[2]);
      },
    );

    test(
      'a copy behind the journal: nothing dropped, the copy caught up',
      () async {
        await mirroredLines(2);
        copy.lines.removeLast();
        final List<String> had = List<String>.of(journal.lines);
        final MirrorRestore r = await mirrored.restore(keys: <SyncKey>[key]);
        expect(r.restored, 0);
        expect(r.kept, 0);
        expect(journal.lines, had);
        expect(copy.lines, had);
      },
    );

    test('the copy is not written before restore has compared it', () async {
      final MirroredSyncStorage fresh = MirroredSyncStorage(
        journal,
        copy,
        clock: () => now,
      );
      copy.lines.add('a line only the copy has');
      final SyncStore store = await _open(fresh, key: key);
      await _sell(store, 1);
      await store.markReloaded();
      expect(copy.lines, <String>['a line only the copy has']);
    });

    test('equal copies: nothing written', () async {
      await mirroredLines(2);
      final MirrorRestore r = await mirrored.restore(keys: <SyncKey>[key]);
      expect(r.changed, isFalse);
    });

    test('restore needs no flag (review 1: offline.export lived in the folder '
        'that was lost): verified lines always come back', () async {
      await mirroredLines(2);
      journal.lines.clear();
      final MirrorRestore r = await mirrored.restore(keys: <SyncKey>[key]);
      expect(r.restored, 2);
      expect(r.kept, 0);
      expect(journal.lines, hasLength(2));
    });

    test(
      'the journal cannot be rewritten: it is left as it is, the copy too',
      () async {
        final _NoReplace stuck = _NoReplace();
        final MirroredSyncStorage m = MirroredSyncStorage(
          stuck,
          copy,
          clock: () => now,
        );
        await m.restore(keys: <SyncKey>[key]);
        final SyncStore store = await _open(m, key: key);
        await _sell(store, 2);
        stuck.lines.removeLast();
        final List<String> copied = List<String>.of(copy.lines);
        final MirrorRestore r = await m.restore(keys: <SyncKey>[key]);
        expect(r.error, isNotNull);
        expect(r.restored, 0);
        expect(stuck.lines, hasLength(1));
        expect(copy.lines, copied);
        // ... and the copy is not overwritten by the appends that follow
        final SyncStore after = await _open(m, key: key);
        await _sell(after, 1);
        expect(copy.lines.take(2), copied);
      },
    );

    test('the copy cannot be kept aside: it is not touched this run', () async {
      final _BrokenDocs docs = _BrokenDocs();
      final MirroredSyncStorage m = MirroredSyncStorage(
        journal,
        docs,
        clock: () => now,
      );
      await m.restore(keys: <SyncKey>[key]);
      final SyncStore store = await _open(m);
      await _sell(store, 1); // unsigned: can only be kept
      journal.lines.clear();
      final List<String> copied = List<String>.of(docs.lines);
      final MirrorRestore r = await m.restore(keys: <SyncKey>[key]);
      expect(r.error, isNotNull);
      final SyncStore after = await _open(m, key: key);
      await _sell(after, 1);
      await after.markReloaded();
      expect(docs.lines, copied);
    });
  });

  test('FileSyncStorage as the copy: a real folder', () async {
    final Directory dir = await Directory.systemTemp.createTemp('rzq_mirror_');
    addTearDown(() => dir.delete(recursive: true));
    final FileSyncStorage j = FileSyncStorage(Directory('${dir.path}/app'));
    final FileSyncStorage c = FileSyncStorage(Directory('${dir.path}/copy'));
    final MirroredSyncStorage m = MirroredSyncStorage(
      j,
      c,
      directory: c.directory,
    );
    expect(m.directory?.path, '${dir.path}/copy');
    await m.restore(keys: <SyncKey>[key]);
    final SyncStore store = await _open(m, key: key);
    await _sell(store, 2);
    await j.close();
    await c.close();
    await File('${dir.path}/app/journal.log').delete();
    final FileSyncStorage j2 = FileSyncStorage(Directory('${dir.path}/app'));
    final MirroredSyncStorage m2 = MirroredSyncStorage(j2, c);
    final MirrorRestore r = await m2.restore(keys: <SyncKey>[key]);
    expect(r.restored, 2);
    final SyncStore after = await _open(m2, key: key);
    expect(after.pendingCount, 2);
    await j2.close();
    await c.close();
  });
}

class _BrokenDocs extends MemorySyncStorage {
  @override
  Future<void> writeDoc(String key, String content) async =>
      throw const FileSystemException('read only');
}
