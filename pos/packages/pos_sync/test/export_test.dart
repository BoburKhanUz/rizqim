import 'dart:convert';
import 'dart:io';

import 'package:crypto/crypto.dart';
import 'package:pos_sync/pos_sync.dart';
import 'package:test/test.dart';

/// Wave M5 S4-a (docs/scale/M5_DESIGN.md 5, "As built: the export file"): the till's rescue file. Pinned to the
/// vector file the server's tests read (backend/tests/Fixtures/sync_export_vectors.json): the till must write every
/// file of it byte for byte. No key is written here: the test keys are derived from the file's seed.
Map<String, Object?> _vectors() {
  var dir = Directory.current;
  for (var i = 0; i < 8; i++) {
    final File f = File(
      '${dir.path}/backend/tests/Fixtures/sync_export_vectors.json',
    );
    if (f.existsSync()) {
      return (jsonDecode(f.readAsStringSync()) as Map).cast<String, Object?>();
    }
    dir = dir.parent;
  }
  throw StateError('sync_export_vectors.json not found');
}

final Map<String, Object?> _v = _vectors();

Map<String, Object?> _map(Object? v) => (v! as Map).cast<String, Object?>();

List<int> _keyBytes(String device, int kid) => Hmac(
  sha256,
  utf8.encode(_map(_v['test_keys'])['seed']! as String),
).convert(utf8.encode('${device.toLowerCase()}/kid-$kid')).bytes;

SyncKey _key(String device, int kid) =>
    SyncKey(key: _keyBytes(device, kid), kid: kid, deviceUuid: device);

String _hex(List<int> b) =>
    b.map((int x) => x.toRadixString(16).padLeft(2, '0')).join();

String get _device => _v['device']! as String;
String get _earlier => _v['earlier_device']! as String;

/// A key for the store tests that is not one of the vector keys (also derived, never written).
SyncKey _storeKey(String device, {int kid = 1}) => SyncKey(
  key: sha256.convert(utf8.encode('export-test/$device/$kid')).bytes,
  kid: kid,
  deviceUuid: device,
);

void main() {
  group('vector file', () {
    test('the derived test keys are the ones the file was made with', () {
      final Map<String, Object?> sums = _map(
        _map(_v['test_keys'])['sha256_of_key'],
      );
      expect(sums, isNotEmpty);
      for (final MapEntry<String, Object?> e in sums.entries) {
        final List<String> parts = e.key.split('/kid-');
        expect(
          sha256.convert(_keyBytes(parts[0], int.parse(parts[1]))).toString(),
          e.value,
          reason: e.key,
        );
      }
    });

    test('the labels are the ones of the format', () {
      expect(SyncExportFormat.fileLabel, _v['file_label']);
      expect(SyncSignature.label, _v['operation_label']);
      expect(SyncExportFormat.version, _v['version']);
    });

    test('every file of the vectors is rebuilt byte for byte', () {
      final List<Object?> files = _v['files']! as List<Object?>;
      expect(files, hasLength(4));
      for (final Object? f in files) {
        final Map<String, Object?> c = _map(f);
        final Map<String, Object?> input = _map(c['input']);
        final SyncExportFile file = SyncExport.build(
          key: _key(input['device']! as String, input['kid']! as int),
          lines: <Map<String, Object?>>[
            for (final Object? l in input['lines']! as List<Object?>) _map(l),
          ],
          createdAt: DateTime.parse(input['created_at']! as String),
        );
        final String name = c['name']! as String;
        expect(file.text, c['text'], reason: name);
        expect(file.name, c['file_name'], reason: name);
        expect(sha256.convert(file.bytes).toString(), c['sha256_of_text']);
        expect(file.fileMac, c['file_mac'], reason: name);
        final Map<String, Object?> header = _map(c['header']);
        expect(file.header, header, reason: name);
        expect(file.header.keys.toList(), header.keys.toList(), reason: name);
        expect(file.device, header['device']);
        expect(file.kid, header['kid']);
        expect(file.kids, header['kids']);
        expect(file.firstSeq, header['first_seq']);
        expect(file.lastSeq, header['last_seq']);
        expect(file.count, header['count']);
        expect(file.text.endsWith('\n'), isTrue);
        expect(file.text.contains('\r'), isFalse);
      }
    });

    test('what a reader accepts and refuses', () {
      final SyncKey key = _key(_device, 1);
      final List<Object?> cases =
          _map(_v['readers'])['cases']! as List<Object?>;
      expect(cases, hasLength(12));
      for (final Object? c in cases) {
        final Map<String, Object?> m = _map(c);
        final SyncExportCheck check = SyncExport.verify(
          utf8.encode(m['text']! as String),
          key,
        );
        expect(check.reason ?? 'ok', m['expect'], reason: m['name'] as String);
        expect(check.ok, m['expect'] == 'ok');
      }
    });

    test('a file is checked under the key of its own device and number', () {
      final Map<String, Object?> first = _map(
        (_v['files']! as List<Object?>).first,
      );
      final List<int> bytes = utf8.encode(first['text']! as String);
      expect(SyncExport.verify(bytes, _key(_device, 1)).ok, isTrue);
      expect(
        SyncExport.verify(bytes, _key(_device, 2)).reason,
        'file_mac_invalid',
      );
      expect(
        SyncExport.verify(bytes, _key(_earlier, 3)).reason,
        'file_mac_invalid',
      );
    });
  });

  group('from the queue', () {
    final String device = _device;
    late MemorySyncStorage storage;
    late SyncStore store;
    SyncKey? key;

    setUp(() async {
      storage = MemorySyncStorage();
      key = _storeKey(device);
      store = await SyncStore.open(
        storage,
        clock: () => DateTime.utc(2026, 11, 21, 9),
        keyProvider: () => key,
      );
    });

    test('the waiting operations go in as they were sent, an orphan queue '
        'with its device', () async {
      await store.enqueue(SyncOpType.orderOpen, <String, Object?>{
        'order': 'o1',
        'local_number': 'K1-001',
      }, staffId: 7);
      key = null; // one queued unsigned
      await store.enqueue(SyncOpType.billPay, <String, Object?>{
        'order': 'o1',
        'tenders': <Object?>[
          <String, Object?>{'method': 'cash', 'amount': '12000.00'},
        ],
      }, staffId: 7);
      key = _storeKey(device);
      final SyncKey earlierKey = _storeKey(_earlier, kid: 3);
      final SyncOperation orphan = SyncOperation(
        opId: 'aaaa0000-0000-4000-8000-000000000001',
        seq: 9,
        type: SyncOpType.billPay,
        occurredAt: DateTime.utc(2026, 11, 20, 8),
        staffId: 7,
        payload: const <String, Object?>{'order': 'x'},
      );
      final SyncOperation signedOrphan = orphan.sealed(
        SyncSignature.seal(
          earlierKey,
          SyncSignature.firstPrev(earlierKey),
          jsonEncode(orphan.toJson()),
        ),
      );
      final List<Map<String, Object?>> lines = SyncExport.linesOf(
        pending: store.pending,
        setAside: <SyncExportQueue>[
          SyncExportQueue(
            device: _earlier,
            operations: <SyncOperation>[signedOrphan],
          ),
          // the same op_id twice: one line
          SyncExportQueue(
            device: _earlier,
            operations: <SyncOperation>[signedOrphan],
          ),
        ],
      );
      final SyncExportFile file = SyncExport.build(
        key: key!,
        lines: lines,
        createdAt: DateTime.utc(2026, 11, 21, 9, 0, 0, 0, 123),
      );
      final List<String> text = file.text.split('\n');
      expect(text, hasLength(1 + 3 + 1 + 1)); // header, 3 lines, trailer, ''
      final Map<String, Object?> head = _map(jsonDecode(text[0]));
      expect(head['created_at'], '2026-11-21T09:00:00.000Z');
      expect(head['first_seq'], 1);
      expect(head['last_seq'], 2);
      expect(head['kids'], <int>[1, 3]);
      expect(head['count'], 3);
      // The orphan first, with its device as the last key
      final Map<String, Object?> l1 = _map(jsonDecode(text[1]));
      expect(l1.keys.last, 'dev');
      expect(l1['dev'], _earlier);
      expect(l1['signed'], signedOrphan.seal!.raw);
      // Then the till's own: the signed text unchanged, the unsigned one as the operation itself
      expect(text[2], jsonEncode(store.pending[0].seal!.toWire()));
      expect(text[3], jsonEncode(store.pending[1].toJson()));
      expect(file.name, 'rizqim-navbat-$device-1-2.rzq');
      expect(SyncExport.verify(file.bytes, key!).ok, isTrue);
      // Never a key in the file
      for (final SyncKey k in <SyncKey>[_storeKey(device), earlierKey]) {
        final List<int> bytes = sha256
            .convert(utf8.encode('export-test/${k.deviceUuid}/${k.kid}'))
            .bytes;
        expect(file.text.contains(base64Encode(bytes)), isFalse);
        expect(file.text.contains(_hex(bytes)), isFalse);
      }
    });

    test('an own line named with the header device carries no dev; an '
        'orphan without a device is the till\'s own', () {
      final SyncOperation op = SyncOperation(
        opId: 'bbbb0000-0000-4000-8000-000000000001',
        seq: 3,
        type: SyncOpType.orderClose,
        occurredAt: DateTime.utc(2026, 11, 20, 8),
        staffId: 7,
        payload: const <String, Object?>{'order': 'x'},
      );
      final List<Map<String, Object?>> lines = SyncExport.linesOf(
        pending: const <SyncOperation>[],
        setAside: <SyncExportQueue>[
          SyncExportQueue(device: null, operations: <SyncOperation>[op]),
        ],
      );
      final SyncExportFile file = SyncExport.build(
        key: _storeKey(device),
        lines: lines,
        createdAt: DateTime.utc(2026, 11, 21),
      );
      expect(file.text.contains('"dev"'), isFalse);
      expect(file.firstSeq, 3);
    });

    test('past the line limit the queue is written as several whole files', () {
      final List<Map<String, Object?>> lines = <Map<String, Object?>>[
        for (var i = 1; i <= 7; i++)
          SyncOperation(
            opId: 'cccc0000-0000-4000-8000-00000000000$i',
            seq: i,
            type: SyncOpType.orderServed,
            occurredAt: DateTime.utc(2026, 11, 20, 8),
            staffId: 7,
            payload: const <String, Object?>{'order': 'x'},
          ).toJson(),
      ];
      final List<SyncExportFile> files = SyncExport.buildAll(
        key: _storeKey(device),
        lines: lines,
        createdAt: DateTime.utc(2026, 11, 21),
        maxLines: 3,
      );
      expect(files.map((SyncExportFile f) => f.count), <int>[3, 3, 1]);
      expect(files.map((SyncExportFile f) => f.name), <String>[
        'rizqim-navbat-$device-1-3.rzq',
        'rizqim-navbat-$device-4-6.rzq',
        'rizqim-navbat-$device-7-7.rzq',
      ]);
      for (final SyncExportFile f in files) {
        expect(SyncExport.verify(f.bytes, _storeKey(device)).ok, isTrue);
      }
      expect(
        () => SyncExport.build(
          key: _storeKey(device),
          lines: lines,
          createdAt: DateTime.utc(2026, 11, 21),
          maxLines: 3,
        ),
        throwsA(isA<SyncExportRefused>()),
      );
    });

    group('SyncExporter', () {
      late Directory dir;
      var enabled = true;

      setUp(() async {
        dir = await Directory.systemTemp.createTemp('rzq_export_');
        enabled = true;
      });

      tearDown(() async {
        if (await dir.exists()) await dir.delete(recursive: true);
      });

      SyncExporter exporter({List<SyncExportQueue> setAside = const []}) =>
          SyncExporter(
            store: store,
            keyProvider: () => key,
            enabled: () => enabled,
            setAside: () => setAside,
            directory: () => dir,
            clock: () => DateTime.utc(2026, 11, 21, 10, 30),
          );

      test('writes the file to the folder and answers its path', () async {
        await store.enqueue(SyncOpType.billPay, <String, Object?>{
          'order': 'o1',
        }, staffId: 7);
        final SyncExportResult r = await exporter().export();
        expect(r.files, hasLength(1));
        expect(r.operations, 1);
        expect(r.payments, 1);
        final File f = File(r.paths.single);
        expect(f.path, '${dir.path}/rizqim-navbat-$device-1-1.rzq');
        final List<int> bytes = await f.readAsBytes();
        expect(SyncExport.verify(bytes, key!).ok, isTrue);
        expect(utf8.decode(bytes), r.files.single.text);
        // No temporary file left behind
        expect(
          dir.listSync().map((FileSystemEntity e) => e.uri.pathSegments.last),
          <String>['rizqim-navbat-$device-1-1.rzq'],
        );
        // Another folder (a USB stick)
        final Directory usb = await dir.createTemp('usb');
        final SyncExportResult r2 = await exporter().export(to: usb);
        expect(r2.paths.single, startsWith(usb.path));
      });

      test('only while the server takes such files (offline.export)', () async {
        await store.enqueue(SyncOpType.billPay, <String, Object?>{
          'order': 'o1',
        }, staffId: 7);
        enabled = false;
        final SyncExporter e = exporter();
        expect(e.available, isFalse);
        await expectLater(
          e.export(),
          throwsA(
            isA<SyncExportRefused>().having(
              (SyncExportRefused r) => r.reason,
              'reason',
              SyncExportRefused.exportOff,
            ),
          ),
        );
        expect(dir.listSync(), isEmpty);
      });

      test('no key: refused, nothing written', () async {
        key = null;
        await expectLater(
          exporter().export(),
          throwsA(
            isA<SyncExportRefused>().having(
              (SyncExportRefused r) => r.reason,
              'reason',
              SyncExportRefused.noKey,
            ),
          ),
        );
        expect(dir.listSync(), isEmpty);
      });

      test('the offer: outage past the alert with a queue, and before a '
          'disconnect', () async {
        final SyncExporter e = exporter();
        OfflineStatus status(DateTime since, int queue) =>
            OfflineStatus.evaluate(
              online: false,
              queueSize: queue,
              queuedOrders: 0,
              errorsCount: 0,
              lastContactAt: since,
              now: DateTime.utc(2026, 11, 21, 10, 30),
              limits: const OfflineLimits(),
            );
        expect(
          e.offerFor(status(DateTime.utc(2026, 11, 21, 8, 30), 3)),
          SyncExportOffer.outage,
        );
        expect(
          e.offerFor(status(DateTime.utc(2026, 11, 21, 9, 30), 3)),
          isNull,
        );
        expect(
          e.offerFor(status(DateTime.utc(2026, 11, 21, 8, 30), 0)),
          isNull,
        );
        expect(e.offerBeforeDisconnect, isNull); // nothing waiting
        await store.enqueue(SyncOpType.billPay, <String, Object?>{
          'order': 'o1',
        }, staffId: 7);
        expect(e.offerBeforeDisconnect, SyncExportOffer.beforeDisconnect);
        enabled = false;
        expect(e.offerBeforeDisconnect, isNull);
        expect(
          e.offerFor(status(DateTime.utc(2026, 11, 21, 8, 30), 3)),
          isNull,
        );
      });

      test('a queue set aside alone is offered before a disconnect', () {
        final SyncOperation op = SyncOperation(
          opId: 'dddd0000-0000-4000-8000-000000000001',
          seq: 1,
          type: SyncOpType.billPay,
          occurredAt: DateTime.utc(2026, 11, 20, 8),
          staffId: 7,
          payload: const <String, Object?>{'order': 'x'},
        );
        final SyncExporter e = exporter(
          setAside: <SyncExportQueue>[
            SyncExportQueue(device: _earlier, operations: <SyncOperation>[op]),
          ],
        );
        expect(e.offerBeforeDisconnect, SyncExportOffer.beforeDisconnect);
      });
    });
  });
}
