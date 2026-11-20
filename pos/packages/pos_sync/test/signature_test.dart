import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:crypto/crypto.dart';
import 'package:pos_sync/pos_sync.dart';
import 'package:test/test.dart';

/// Wave M5 S3 (docs/scale/M5_DESIGN.md 4): queued operations are signed (HMAC-SHA256) and chained. The scheme is
/// pinned to the vector file shared with the server (backend/tests/Fixtures/sync_signature_vectors.json); no key is
/// written there or here: a test key is derived from the file's seed.
Map<String, Object?> _vectors() {
  var dir = Directory.current;
  for (var i = 0; i < 8; i++) {
    final File f = File(
      '${dir.path}/backend/tests/Fixtures/sync_signature_vectors.json',
    );
    if (f.existsSync()) {
      return (jsonDecode(f.readAsStringSync()) as Map).cast<String, Object?>();
    }
    dir = dir.parent;
  }
  throw StateError('sync_signature_vectors.json not found');
}

List<int> _hmac(List<int> key, List<int> data) =>
    Hmac(sha256, key).convert(data).bytes;

String _hex(List<int> bytes) =>
    <String>[for (final int b in bytes) b.toRadixString(16).padLeft(2, '0')]
        .join();

List<int> _unhex(String hex) => <int>[
  for (var i = 0; i < hex.length; i += 2)
    int.parse(hex.substring(i, i + 2), radix: 16),
];

/// The scheme, written out a second time in the test (never through the code under test).
String _mac(List<int> key, String prev, String raw) =>
    _hex(_hmac(key, <int>[..._unhex(prev), ...utf8.encode(raw)]));

String _first(List<int> key, String device) =>
    _hex(_hmac(key, utf8.encode('rizqim-sync/v1/${device.toLowerCase()}')));

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

class _Server implements SyncTransport {
  final List<Map<String, Object?>> bodies = <Map<String, Object?>>[];
  final Set<int> taken = <int>{};

  @override
  Future<SyncBatchResponse> send(SyncBatchRequest request) async {
    bodies.add(
      (jsonDecode(jsonEncode(request.toJson())) as Map).cast<String, Object?>(),
    );
    for (final SyncOperation o in request.operations) {
      if (taken.contains(o.seq)) {
        throw const SyncRefused('seq', status: 409, reason: 'seq_reused');
      }
    }
    return SyncBatchResponse(
      results: <SyncResult>[
        for (final SyncOperation o in request.operations)
          SyncResult(opId: o.opId, seq: o.seq, status: SyncOpStatus.applied),
      ],
    );
  }
}

void main() {
  final Map<String, Object?> v = _vectors();
  final String device = v['device']! as String;
  final String seed = (v['test_keys']! as Map)['seed']! as String;
  List<int> keyOf(int kid) => _hmac(utf8.encode(seed), utf8.encode('kid-$kid'));
  SyncKey key(int kid, [String? dev]) => SyncKey(
    key: Uint8List.fromList(keyOf(kid)),
    kid: kid,
    deviceUuid: dev ?? device,
  );
  final Map<String, Object?> firstPrev = (v['first_prev']! as Map)
      .cast<String, Object?>();
  final List<Map<String, Object?>> chains = <Map<String, Object?>>[
    for (final Object? c in v['chains']! as List<Object?>)
      (c! as Map).cast<String, Object?>(),
  ];
  List<Map<String, Object?>> opsOf(Map<String, Object?> chain) =>
      <Map<String, Object?>>[
        for (final Object? o in chain['operations']! as List<Object?>)
          (o! as Map).cast<String, Object?>(),
      ];

  group('the shared vectors', () {
    test('the derived test keys are the ones the file was made with', () {
      final Map<Object?, Object?> sums =
          (v['test_keys']! as Map)['sha256_of_key']! as Map;
      for (final int kid in <int>[1, 2]) {
        expect(sha256.convert(keyOf(kid)).toString(), sums['$kid']);
      }
    });

    test('first prev of each key', () {
      for (final int kid in <int>[1, 2]) {
        expect(SyncSignature.firstPrev(key(kid)), firstPrev['$kid']);
        // The device uuid is lower-cased
        expect(
          SyncSignature.firstPrev(key(kid, device.toUpperCase())),
          firstPrev['$kid'],
        );
      }
    });

    test('every mac of every chain, over the exact text of `signed`', () {
      expect(chains, hasLength(2));
      for (final Map<String, Object?> chain in chains) {
        final int kid = chain['kid']! as int;
        var prev = firstPrev['$kid']! as String;
        for (final Map<String, Object?> o in opsOf(chain)) {
          expect(o['prev'], prev, reason: '${chain['name']}');
          expect(
            SyncSignature.mac(key(kid), prev, o['signed']! as String),
            o['mac'],
            reason: '${chain['name']}',
          );
          prev = o['mac']! as String;
        }
      }
    });

    test('isMac: 64 lower-case hex characters only', () {
      expect(SyncSignature.isMac(firstPrev['1']), isTrue);
      expect(
        SyncSignature.isMac((firstPrev['1']! as String).toUpperCase()),
        isFalse,
      );
      expect(SyncSignature.isMac('ab'), isFalse);
      expect(SyncSignature.isMac(null), isFalse);
    });

    test('a key is 32 bytes, from base64, and never printed', () {
      final SyncKey k = SyncKey.fromBase64(
        base64Encode(keyOf(1)),
        kid: 1,
        deviceUuid: device,
      );
      expect(SyncSignature.firstPrev(k), firstPrev['1']);
      expect(k.toString(), isNot(contains(base64Encode(keyOf(1)))));
      expect(k.toString(), isNot(contains(_hex(keyOf(1)))));
      expect(
        () => SyncKey.fromBase64('AAAA', kid: 1, deviceUuid: device),
        throwsArgumentError,
      );
      expect(
        () => SyncKey(key: Uint8List(32), kid: 0, deviceUuid: device),
        throwsArgumentError,
      );
    });
  });

  group('the journal', () {
    late MemorySyncStorage disk;
    late SyncStore store;
    SyncKey? current;

    Future<SyncStore> open() =>
        SyncStore.open(disk, keyProvider: () => current);

    setUp(() async {
      disk = MemorySyncStorage();
      current = key(1);
      store = await open();
    });

    Future<SyncOperation> add([String note = 'a']) => store.enqueue(
      SyncOpType.orderOpen,
      <String, Object?>{'order': 'o-$note', 'note': 'Choy 🍵 "$note"\nЎзбекча'},
      staffId: 7,
    );

    Future<void> ack(Iterable<SyncOperation> ops) =>
        store.applyResults(<SyncResult>[
          for (final SyncOperation o in ops)
            SyncResult(opId: o.opId, seq: o.seq, status: SyncOpStatus.applied),
        ]);

    test('no key: the operation is queued and sent as before', () async {
      current = null;
      final SyncOperation op = await add();
      expect(op.seal, isNull);
      expect(op.toWire(), op.toJson());
      expect(_record(disk.lines.single)['op'], isA<Map<Object?, Object?>>());
      expect(_record(disk.lines.single).containsKey('raw'), isFalse);
    });

    test(
      'with a key: serialised once, signed, chained from first prev',
      () async {
        final SyncOperation a = await add('a');
        final SyncOperation b = await add('b');
        final SyncSeal sa = a.seal!;
        final SyncSeal sb = b.seal!;
        // raw is the operation's JSON, and it is what the journal line keeps
        expect(jsonDecode(sa.raw), a.toJson());
        expect(sa.kid, 1);
        expect(sa.prev, _first(keyOf(1), device));
        expect(sa.mac, _mac(keyOf(1), sa.prev, sa.raw));
        expect(sb.prev, sa.mac);
        expect(sb.mac, _mac(keyOf(1), sb.prev, sb.raw));
        final Map<String, Object?> line = _record(disk.lines.first);
        expect(line['k'], 'op');
        expect(line['raw'], sa.raw);
        expect(line['mac'], sa.mac);
        expect(line['prev'], sa.prev);
        expect(line['kid'], 1);
        expect(line.containsKey('op'), isFalse);
        // The wire shape
        expect(a.toWire(), <String, Object?>{
          'signed': sa.raw,
          'mac': sa.mac,
          'prev': sa.prev,
          'kid': 1,
        });
      },
    );

    test('the key is never written to the journal', () async {
      await add('a');
      await ack(store.pending);
      await add('b');
      await store.markReloaded();
      final String all = disk.lines.join('\n');
      expect(all, isNot(contains(base64Encode(keyOf(1)))));
      expect(all, isNot(contains(_hex(keyOf(1)))));
    });

    test(
      'reopened: raw, mac, prev and kid are the same, the chain goes on',
      () async {
        final SyncOperation a = await add('a');
        final SyncOperation b = await add('b');
        store = await open();
        expect(store.pending.map((SyncOperation o) => o.toWire()), <Object?>[
          a.toWire(),
          b.toWire(),
        ]);
        final SyncOperation c = await add('c');
        expect(c.seal!.prev, b.seal!.mac);
      },
    );

    test(
      'a journal holding the vector chain is sent byte for byte, also after a '
      'compaction (a raw that jsonEncode would not write the same way)',
      () async {
        final List<Map<String, Object?>> ops = opsOf(chains.first);
        disk.lines.addAll(<String>[
          for (final Map<String, Object?> o in ops)
            _line(<String, Object?>{
              'k': 'op',
              'raw': o['signed'],
              'mac': o['mac'],
              'prev': o['prev'],
              'kid': o['kid'],
              'dev': device,
            }),
        ]);
        // (the vectors have one whose text is not what jsonEncode gives back)
        expect(
          ops.any(
            (Map<String, Object?> o) =>
                jsonEncode(jsonDecode(o['signed']! as String)) != o['signed'],
          ),
          isTrue,
        );
        store = await open();
        expect(store.pending.map((SyncOperation o) => o.toWire()), ops);
        await store.markReloaded(); // compaction
        expect(store.pending.map((SyncOperation o) => o.toWire()), ops);
        store = await open();
        expect(store.pending.map((SyncOperation o) => o.toWire()), ops);
        expect(store.lastSeq, 5);
        final SyncOperation next = await add('n');
        expect(next.seal!.prev, ops.last['mac']);
      },
    );

    test('after the answers and a compaction the chain goes on from the last '
        'acknowledged mac', () async {
      final SyncOperation a = await add('a');
      final SyncOperation b = await add('b');
      await ack(<SyncOperation>[a, b]);
      await store.markReloaded();
      await store.markReloaded(); // answered operations are below the floor now
      store = await open();
      expect(store.pending, isEmpty);
      final SyncOperation c = await add('c');
      expect(c.seal!.prev, b.seal!.mac);
      expect(c.seal!.mac, _mac(keyOf(1), b.seal!.mac, c.seal!.raw));
    });

    test(
      'a new key (rotation) starts its own chain; what waits under the old one '
      'is left as it is',
      () async {
        final SyncOperation a = await add('a');
        current = key(2);
        final SyncOperation b = await add('b');
        final SyncOperation c = await add('c');
        expect(store.pending.first.toWire(), a.toWire());
        expect(b.seal!.kid, 2);
        expect(b.seal!.prev, _first(keyOf(2), device));
        expect(b.seal!.mac, _mac(keyOf(2), b.seal!.prev, b.seal!.raw));
        expect(c.seal!.prev, b.seal!.mac);
      },
    );

    test(
      'another device (re-paired) with the same kid starts from its own first '
      'prev',
      () async {
        final SyncOperation a = await add('a');
        await ack(<SyncOperation>[a]);
        const String other = '11111111-2222-4333-8444-555555555555';
        current = key(1, other);
        final SyncOperation b = await add('b');
        expect(b.seal!.prev, _first(keyOf(1), other));
      },
    );

    test('an unsigned operation in between does not break the chain', () async {
      final SyncOperation a = await add('a');
      current = null;
      final SyncOperation b = await add('b');
      current = key(1);
      final SyncOperation c = await add('c');
      expect(b.seal, isNull);
      expect(c.seal!.prev, a.seal!.mac);
    });

    test('a key provider that fails never stops the operation: it is queued '
        'unsigned', () async {
      store.keyProvider = () => throw StateError('secure storage is locked');
      final SyncOperation a = await add('a');
      expect(a.seal, isNull);
      expect(store.pending, hasLength(1));
      store.keyProvider = () async => key(1);
      final SyncOperation b = await add('b');
      expect(b.seal!.prev, _first(keyOf(1), device));
    });

    test(
      'adoptServerSeq: renumbered operations are signed again and chained from '
      'the last acknowledged mac',
      () async {
        final SyncOperation a = await add('a');
        await ack(<SyncOperation>[a]);
        final SyncOperation b = await add('b');
        final SyncOperation c = await add('c');
        expect(await store.adoptServerSeq(10), isTrue);
        final List<SyncOperation> p = store.pending;
        expect(p.map((SyncOperation o) => o.seq), <int>[11, 12]);
        expect(p.map((SyncOperation o) => o.opId), <String>[b.opId, c.opId]);
        expect(jsonDecode(p[0].seal!.raw), p[0].toJson());
        expect((jsonDecode(p[0].seal!.raw) as Map)['seq'], 11);
        expect(p[0].seal!.prev, a.seal!.mac);
        expect(p[0].seal!.mac, _mac(keyOf(1), a.seal!.mac, p[0].seal!.raw));
        expect(p[0].seal!.mac, isNot(b.seal!.mac));
        expect(p[1].seal!.prev, p[0].seal!.mac);
        expect(p[1].seal!.mac, _mac(keyOf(1), p[1].seal!.prev, p[1].seal!.raw));
        // On the disk too, and new operations follow
        store = await open();
        expect(store.pending.map((SyncOperation o) => o.toWire()), <Object?>[
          p[0].toWire(),
          p[1].toWire(),
        ]);
        final SyncOperation d = await add('d');
        expect(d.seq, 13);
        expect(d.seal!.prev, p[1].seal!.mac);
      },
    );

    test('adoptServerSeq with a lost journal: the chain starts from first prev '
        '(the server says chain_gap once)', () async {
      await add('a');
      await add('b');
      expect(await store.adoptServerSeq(40), isTrue);
      final List<SyncOperation> p = store.pending;
      expect(p[0].seal!.prev, _first(keyOf(1), device));
      expect(p[0].seal!.mac, _mac(keyOf(1), p[0].seal!.prev, p[0].seal!.raw));
      expect(p[1].seal!.prev, p[0].seal!.mac);
    });

    test(
      'adoptServerSeq under a rotated key: everything waiting is signed with '
      'the key the till has now',
      () async {
        await add('a');
        current = key(2);
        await add('b');
        expect(await store.adoptServerSeq(40), isTrue);
        final List<SyncOperation> p = store.pending;
        expect(p.map((SyncOperation o) => o.seal!.kid), <int>[2, 2]);
        expect(p[0].seal!.prev, _first(keyOf(2), device));
        expect(p[1].seal!.prev, p[0].seal!.mac);
        expect(p[1].seal!.mac, _mac(keyOf(2), p[1].seal!.prev, p[1].seal!.raw));
      },
    );

    test('adoptServerSeq without a key: a stale mac is never kept', () async {
      await add('a');
      current = null;
      expect(await store.adoptServerSeq(40), isTrue);
      expect(store.pending.single.seal, isNull);
      expect(store.pending.single.seq, 41);
      expect(_record(disk.lines.last).containsKey('mac'), isFalse);
    });

    test('adoptServerSeq that cannot be written: the old numbers AND the old '
        'signatures stay', () async {
      final SyncOperation a = await add('a');
      final _FailingReplace failing = _FailingReplace(disk);
      store = await SyncStore.open(failing, keyProvider: () => current);
      failing.fail = true;
      await expectLater(store.adoptServerSeq(40), throwsA(anything));
      expect(store.pending.single.toWire(), a.toWire());
    });

    test(
      'adoptServerSeq that only moves the counter leaves signatures alone',
      () async {
        final SyncOperation a = await add('a');
        await ack(<SyncOperation>[a]);
        store = await open();
        expect(await store.adoptServerSeq(30), isTrue);
        final SyncOperation b = await add('b');
        expect(b.seq, 31);
        expect(b.seal!.prev, a.seal!.mac);
      },
    );

    test(
      'clear (terminal disconnected): unsent operations are dropped, the chain '
      'goes back to the last acknowledged mac',
      () async {
        final SyncOperation a = await add('a');
        await ack(<SyncOperation>[a]);
        await add('b');
        await store.clear();
        final SyncOperation c = await add('c');
        expect(c.seal!.prev, a.seal!.mac);
        store = await open();
        final SyncOperation d = await add('d');
        expect(d.seal!.prev, c.seal!.mac);
      },
    );

    test('an old journal (unsigned lines) is read as before and new operations '
        'are signed after it', () async {
      current = null;
      final SyncOperation a = await add('a');
      current = key(1);
      store = await open();
      expect(store.pending.single.toWire(), a.toJson());
      final SyncOperation b = await add('b');
      expect(b.seal!.prev, _first(keyOf(1), device));
    });

    test(
      'a signed line whose raw is not an operation is damage, not a crash',
      () async {
        await add('a');
        disk.lines.add(
          _line(<String, Object?>{
            'k': 'op',
            'raw': 'not json',
            'mac': 'x',
            'prev': 'y',
            'kid': 1,
          }),
        );
        store = await open();
        expect(store.pending, hasLength(1));
        expect(store.damaged, hasLength(1));
      },
    );

    test('the sender posts {signed, mac, prev, kid}; unsigned ones in the old '
        'shape, both in one batch', () async {
      final SyncOperation a = await add('a');
      current = null;
      final SyncOperation b = await add('b');
      final _Server server = _Server();
      final SyncDrainResult r = await SyncSender(
        store: store,
        transport: server,
      ).drain();
      expect(r.drained, isTrue);
      final List<Object?> sent = server.bodies.single['operations']! as List;
      expect(sent, <Object?>[
        <String, Object?>{
          'signed': a.seal!.raw,
          'mac': a.seal!.mac,
          'prev': a.seal!.prev,
          'kid': 1,
        },
        jsonDecode(jsonEncode(b.toJson())),
      ]);
    });

    test(
      'the sender after 409 seq_reused: renumbered, signed again, sent',
      () async {
        final SyncOperation a = await add('a');
        await add('b');
        final _Server server = _Server()..taken.addAll(<int>[1, 2, 3]);
        final SyncSender sender = SyncSender(store: store, transport: server)
          ..serverLastSeq = () async => 3;
        final SyncDrainResult r = await sender.drain();
        expect(r.drained, isTrue);
        expect(server.bodies, hasLength(2));
        final List<Object?> again = server.bodies[1]['operations']! as List;
        final Map<Object?, Object?> first = again.first! as Map;
        final String raw = first['signed']! as String;
        expect((jsonDecode(raw) as Map)['seq'], 4);
        expect((jsonDecode(raw) as Map)['op_id'], a.opId);
        expect(first['prev'], _first(keyOf(1), device));
        expect(first['mac'], _mac(keyOf(1), first['prev']! as String, raw));
        expect((again[1]! as Map)['prev'], first['mac']);
        // The chain goes on from what the server took
        final SyncOperation c = await add('c');
        expect(c.seal!.prev, (again[1]! as Map)['mac']);
      },
    );
  });
}

class _FailingReplace implements SyncStorage {
  _FailingReplace(this.inner);

  final MemorySyncStorage inner;
  bool fail = false;

  @override
  Future<void> appendLine(String line) => inner.appendLine(line);

  @override
  Future<void> deleteDoc(String key) => inner.deleteDoc(key);

  @override
  Future<List<String>> docKeys() => inner.docKeys();

  @override
  Future<String?> readDoc(String key) => inner.readDoc(key);

  @override
  Future<List<String>> readLines() => inner.readLines();

  @override
  Future<void> replaceLines(List<String> lines) async {
    if (fail) throw const SyncStorageCrash();
    await inner.replaceLines(lines);
  }

  @override
  Future<void> writeDoc(String key, String content) =>
      inner.writeDoc(key, content);
}
