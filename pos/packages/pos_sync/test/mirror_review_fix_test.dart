import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:crypto/crypto.dart';
import 'package:pos_sync/pos_sync.dart';
import 'package:test/test.dart';

/// Wave M5 S4, fixes after review 1 (F2 till): the mirror restore does not depend on a flag that lives in the
/// journal's own folder, lines it cannot restore stay in the copy and go into the export file, the copy's own queues
/// set aside go there too, a hung copy never holds up a sale, and an export never overwrites an earlier file.
const String _device = 'a4c1e2f3-5b6d-4e7f-8a9b-0c1d2e3f4a5b';
const String _earlier = '0b7d3c1e-2f4a-4b5c-9d6e-7f8091a2b3c4';

/// Derived (never written): not a key-shaped literal.
SyncKey _key({int kid = 1, String device = _device}) => SyncKey(
  key: sha256.convert(utf8.encode('mirror-fix/$device/$kid')).bytes,
  kid: kid,
  deviceUuid: device,
);

Future<void> _sell(SyncStore store, int n, {int from = 0}) async {
  for (var i = from; i < from + n; i++) {
    await store.enqueue(SyncOpType.billPay, <String, Object?>{
      'order': 'o$i',
      'tenders': <Object?>[
        <String, Object?>{'tender': 'cash', 'amount': '10000.00'},
      ],
    }, staffId: 7);
  }
}

List<Map<String, Object?>> _body(String text) {
  final List<String> lines = const LineSplitter().convert(text);
  return <Map<String, Object?>>[
    for (final String l in lines.sublist(1, lines.length - 1))
      (jsonDecode(l) as Map).cast<String, Object?>(),
  ];
}

String _opIdOfLine(Map<String, Object?> line) {
  final Object? signed = line['signed'];
  final Map<Object?, Object?> op = signed is String
      ? jsonDecode(signed) as Map<Object?, Object?>
      : line;
  return op['op_id']! as String;
}

/// A copy whose writes wait on [gate] while it is set (a hung network share, an antivirus lock).
class _HangingStorage extends MemorySyncStorage {
  Completer<void>? gate;
  bool hangReads = false;

  @override
  Future<void> appendLine(String line) async {
    final Completer<void>? g = gate;
    if (g != null) await g.future;
    return super.appendLine(line);
  }

  @override
  Future<void> replaceLines(List<String> next) async {
    final Completer<void>? g = gate;
    if (g != null) await g.future;
    return super.replaceLines(next);
  }

  @override
  Future<List<String>> readLines() {
    if (hangReads) return Completer<List<String>>().future;
    return super.readLines();
  }
}

void main() {
  late Directory root;
  late Directory app;
  late Directory docs;
  final SyncKey key = _key();

  setUp(() async {
    root = await Directory.systemTemp.createTemp('rzq_f2_');
    app = Directory('${root.path}/support/pos_sync');
    docs = Directory('${root.path}/Documents/RIZQIM/$_device');
  });

  tearDown(() async {
    if (await root.exists()) await root.delete(recursive: true);
  });

  Future<(MirroredSyncStorage, SyncStore)> start({SyncKey? withKey}) async {
    final MirroredSyncStorage m = MirroredSyncStorage(
      FileSyncStorage(app),
      FileSyncStorage(docs),
      directory: docs,
    );
    await m.restore(keys: <SyncKey>[?withKey]);
    return (m, await SyncStore.open(m, keyProvider: () => withKey));
  }

  group('restore does not depend on the journal folder', () {
    test('the app folder is wiped (bootstrap copy gone with it): the verified '
        'payments come back from the copy', () async {
      final (_, SyncStore store) = await start(withKey: key);
      await store.putDoc('bootstrap', <String, Object?>{
        'offline': <String, Object?>{'export': true},
      });
      await _sell(store, 3);
      final List<String> ids = <String>[
        for (final SyncOperation o in store.pending) o.opId,
      ];
      await app.delete(recursive: true);

      final (MirroredSyncStorage m, SyncStore after) = await start(
        withKey: key,
      );
      expect(m.lastRestore!.restored, 3);
      expect(m.lastRestore!.restoredPayments, 3);
      expect(<String>[
        for (final SyncOperation o in after.pending) o.opId,
      ], ids);
      expect(await FileSyncStorage(docs).readLines(), hasLength(3));
    });

    test('lines that cannot be verified stay in the copy\'s folder and go into '
        'the export file', () async {
      // Queued before the till had a key: unsigned, never restored on its own
      final (_, SyncStore store) = await start();
      await _sell(store, 2);
      final List<String> ids = <String>[
        for (final SyncOperation o in store.pending) o.opId,
      ];
      await app.delete(recursive: true);

      final (MirroredSyncStorage m, SyncStore after) = await start(
        withKey: key,
      );
      expect(after.pending, isEmpty);
      expect(m.lastRestore!.kept, 2);
      expect(m.setAside, hasLength(1));
      expect(m.setAside.single.operations, hasLength(2));

      final Directory out = Directory('${root.path}/usb');
      final SyncExporter e = SyncExporter(
        store: after,
        keyProvider: () => key,
        enabled: () => true,
        directory: () => out,
      );
      expect(e.offerBeforeDisconnect, SyncExportOffer.beforeDisconnect);
      final SyncExportResult r = await e.export();
      expect(r.operations, 2);
      expect(r.payments, 2);
      final List<Map<String, Object?>> body = _body(r.files.single.text);
      expect(body.map(_opIdOfLine), ids);
      expect(body.every((Map<String, Object?> l) => l['dev'] == null), isTrue);

      // Another start: still kept (never dropped), still exported once
      final (MirroredSyncStorage m2, _) = await start(withKey: key);
      expect(m2.setAside.single.operations, hasLength(2));
    });

    test('the copy of a queue set aside goes into the export after the app '
        'folder is wiped', () async {
      final (_, SyncStore store) = await start(withKey: key);
      final SyncOperation op = SyncOperation(
        opId: 'dddd0000-0000-4000-8000-0000000000f2',
        seq: 4,
        type: SyncOpType.billPay,
        occurredAt: DateTime.utc(2026, 11, 20, 8),
        staffId: 7,
        payload: const <String, Object?>{'order': 'x'},
      );
      await store.putDoc('orphan_20261120_1_abcd', <String, Object?>{
        'set_aside_at': '2026-11-20T09:00:00.000Z',
        'reason': 'paired_again',
        'device_uuid': _earlier,
        'operations': <Object?>[op.toStored()],
      });
      await _sell(store, 1);
      await app.delete(recursive: true);

      final (MirroredSyncStorage m, SyncStore after) = await start(
        withKey: key,
      );
      expect(after.pending, hasLength(1));
      expect(m.setAside, hasLength(1));
      expect(m.setAside.single.device, _earlier);
      final SyncExportResult r = await SyncExporter(
        store: after,
        keyProvider: () => key,
        enabled: () => true,
        directory: () => docs,
      ).export();
      final List<Map<String, Object?>> body = _body(r.files.single.text);
      expect(body, hasLength(2));
      expect(body.first['dev'], _earlier);
      expect(_opIdOfLine(body.first), op.opId);
      expect(r.payments, 2);
    });

    test('lines kept aside by an earlier start are restored once they verify, '
        'and leave the kept document', () async {
      final (MirroredSyncStorage first, SyncStore store) = await start(
        withKey: key,
      );
      await _sell(store, 2);
      final List<String> lines = await FileSyncStorage(app).readLines();
      // An earlier build kept them aside (restore was off) and emptied the copy
      await first.dispose();
      await app.delete(recursive: true);
      await FileSyncStorage(docs).replaceLines(const <String>[]);
      await FileSyncStorage(docs).writeDoc(
        '${MirroredSyncStorage.keptPrefix}20261121090000000',
        jsonEncode(<String, Object?>{
          'version': 1,
          'kept_at': '2026-11-21T09:00:00.000Z',
          'reason': 'restore_off',
          'lines': lines,
        }),
      );

      final (MirroredSyncStorage m, SyncStore after) = await start(
        withKey: key,
      );
      expect(m.lastRestore!.restored, 2);
      expect(after.pending, hasLength(2));
      expect(m.setAside, isEmpty);
      expect(
        (await FileSyncStorage(docs).docKeys()).where(
          (String k) => k.startsWith(MirroredSyncStorage.keptPrefix),
        ),
        isEmpty,
      );
    });
  });

  group('a hung copy never holds up a sale', () {
    test('appends return within the bound, the journal keeps every line in '
        'order, the copy catches up once it answers', () async {
      final MemorySyncStorage journal = MemorySyncStorage();
      final _HangingStorage copy = _HangingStorage();
      final MirroredSyncStorage m = MirroredSyncStorage(
        journal,
        copy,
        writeTimeout: const Duration(milliseconds: 50),
      );
      await m.restore(keys: <SyncKey>[key]);
      final SyncStore store = await SyncStore.open(m, keyProvider: () => key);
      await _sell(store, 1);
      copy.gate = Completer<void>();
      final Stopwatch w = Stopwatch()..start();
      await _sell(store, 3, from: 1).timeout(const Duration(seconds: 2));
      expect(w.elapsed, lessThan(const Duration(seconds: 1)));
      expect(store.pending.map((SyncOperation o) => o.seq), <int>[1, 2, 3, 4]);
      expect(journal.lines, hasLength(4));
      expect(m.mirrorError, isNotNull);

      copy.gate!.complete();
      copy.gate = null;
      await Future<void>.delayed(const Duration(milliseconds: 20));
      expect(copy.lines, journal.lines);
      expect(m.mirrorError, isNull);
    });

    test('a copy that hangs on start: restore gives up, the journal is '
        'untouched and the till opens', () async {
      final MemorySyncStorage journal = MemorySyncStorage();
      final _HangingStorage copy = _HangingStorage()..hangReads = true;
      final MirroredSyncStorage m = MirroredSyncStorage(
        journal,
        copy,
        writeTimeout: const Duration(milliseconds: 50),
        restoreTimeout: const Duration(milliseconds: 50),
      );
      final MirrorRestore r = await m
          .restore(keys: <SyncKey>[key])
          .timeout(const Duration(seconds: 2));
      expect(r.error, isNotNull);
      expect(m.mirroring, isFalse);
      final SyncStore store = await SyncStore.open(m, keyProvider: () => key);
      await _sell(store, 2);
      expect(journal.lines, hasLength(2));
    });
  });

  test('export file names are unique across runs: an earlier file is never '
      'overwritten', () async {
    final SyncStore store = await SyncStore.open(MemorySyncStorage());
    var at = DateTime.utc(2026, 11, 21, 10);
    SyncExporter e() => SyncExporter(
      store: store,
      keyProvider: () => key,
      enabled: () => true,
      directory: () => docs,
      clock: () => at,
    );
    final SyncExportResult first = await e().export();
    final String firstText = await File(first.paths.single).readAsString();
    at = at.add(const Duration(minutes: 5));
    final SyncExportResult second = await e().export();
    expect(second.paths.single, isNot(first.paths.single));
    expect(await File(first.paths.single).readAsString(), firstText);
    expect(
      await File(second.paths.single).readAsString(),
      second.files.single.text,
    );
    expect(second.paths.single, endsWith(SyncExportFormat.extension));
  });
}
