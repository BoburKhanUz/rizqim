import 'dart:convert';
import 'dart:math';

import 'package:pos_sync/pos_sync.dart';
import 'package:pos_sync/src/crc32.dart';
import 'package:test/test.dart';

/// Wave M5 S3, fixes after the two reviews (signed offline queue, the till's journal).
const String _device = '0b9d3c7e-1111-4222-8333-444455556666';

// (generated, never a key-shaped literal)
SyncKey _key([int kid = 1]) => SyncKey(
  key: <int>[for (var i = 0; i < 32; i++) Random(7 + kid).nextInt(256) ^ i],
  kid: kid,
  deviceUuid: _device,
);

Map<String, Object?> _record(String line) =>
    (jsonDecode(line.substring(9)) as Map).cast<String, Object?>();

String _line(Map<String, Object?> record) {
  final String json = jsonEncode(record);
  return '${crc32Hex(json)} $json';
}

/// What a build from before signing does with an `op` line of the journal (pos_sync before M5 S3, store.dart
/// `case 'op'`): the operation is whatever the record keeps under `op`, nothing else is looked at.
SyncOperation _preSigningReader(String line) => SyncOperation.fromJson(
  (_record(line)['op'] as Map? ?? const <String, Object?>{})
      .cast<String, Object?>(),
);

/// What a build from before signing writes when it compacts: it knows nothing of `chain` in the `meta` record, nor of
/// `raw` / `mac` / `prev` / `kid` on an `op` line.
void _compactAsBeforeSigning(MemorySyncStorage disk) {
  for (var i = 0; i < disk.lines.length; i++) {
    final Map<String, Object?> r = _record(disk.lines[i]);
    if (r['k'] == 'meta') {
      disk.lines[i] = _line(<String, Object?>{...r}..remove('chain'));
    } else if (r['k'] == 'op') {
      disk.lines[i] = _line(<String, Object?>{'k': 'op', 'op': r['op']});
    }
  }
}

class _Disk extends MemorySyncStorage {
  bool failDocWrites = false;

  @override
  Future<void> writeDoc(String key, String content) async {
    if (failDocWrites) throw const SyncStorageCrash();
    await super.writeDoc(key, content);
  }
}

class _Answers implements SyncTransport {
  _Answers(this.answer);

  final Map<String, Object?> Function(SyncBatchRequest request) answer;
  final List<SyncBatchRequest> requests = <SyncBatchRequest>[];

  @override
  Future<SyncBatchResponse> send(SyncBatchRequest request) async {
    requests.add(request);
    return SyncBatchResponse.fromJson(answer(request));
  }
}

Map<String, Object?> _applied(SyncOperation o, {String? sig}) =>
    <String, Object?>{
      'op_id': o.opId,
      'seq': o.seq,
      'status': 'applied',
      'sig_status': ?sig,
    };

void main() {
  late _Disk disk;
  SyncKey? current;

  Future<SyncStore> open() => SyncStore.open(disk, keyProvider: () => current);

  setUp(() {
    disk = _Disk();
    current = _key();
  });

  Future<SyncOperation> add(SyncStore store, [String order = 'o1']) =>
      store.enqueue(SyncOpType.orderOpen, <String, Object?>{
        'order': order,
      }, staffId: 1);

  Future<void> ack(SyncStore store, SyncOperation o) =>
      store.applyResults(<SyncResult>[
        SyncResult(opId: o.opId, seq: o.seq, status: SyncOpStatus.applied),
      ]);

  group('rev2 M1: a build from before signing reads a signed journal line', () {
    test('the signed line keeps the operation under `op` beside the signature, '
        'and the old reader queues it', () async {
      final SyncStore store = await open();
      final SyncOperation paid = await store.enqueue(
        SyncOpType.billPay,
        <String, Object?>{'order': 'o1', 'amount': '25000.00'},
        staffId: 1,
      );
      final Map<String, Object?> line = _record(disk.lines.single);
      expect(line['raw'], paid.seal!.raw);
      expect(line['mac'], paid.seal!.mac);
      expect(line['op'], jsonDecode(paid.seal!.raw));

      final SyncOperation old = _preSigningReader(disk.lines.single);
      expect(old.opId, paid.opId);
      expect(old.seq, paid.seq);
      expect(old.type, SyncOpType.billPay);
      expect(old.payload['amount'], '25000.00');
    });

    test(
      'this build still sends the signed text, whatever `op` says',
      () async {
        SyncStore store = await open();
        final SyncOperation a = await add(store);
        final Map<String, Object?> line = _record(disk.lines.single);
        disk.lines[0] = _line(<String, Object?>{
          ...line,
          'op': <String, Object?>{
            ...(line['op']! as Map).cast<String, Object?>(),
            'staff_id': 99,
          },
        });
        store = await open();
        expect(store.pending.single.toWire(), a.seal!.toWire());
        expect(store.pending.single.staffId, 1);
      },
    );

    test('it survives a compaction', () async {
      SyncStore store = await open();
      final SyncOperation a = await add(store);
      await store.markReloaded();
      expect(
        disk.lines.map(_record).where((Map<String, Object?> r) {
          return r['k'] == 'op';
        }).single['op'],
        a.toJson(),
      );
      store = await open();
      expect(store.pending.single.seal!.mac, a.seal!.mac);
    });
  });

  group('rev2 M2: the chain head outlives a compaction by an old build', () {
    test('rolled back, compacted, updated again with an empty queue: the next '
        'operation follows the last one the server answered', () async {
      SyncStore store = await open();
      final SyncOperation a = await add(store);
      await ack(store, a);
      await store.markReloaded();
      await store.dispose();

      _compactAsBeforeSigning(disk);

      store = await open();
      final SyncOperation c = await add(store, 'o3');
      expect(c.seal!.prev, a.seal!.mac);
    });

    test('the journal wins over an older note of the head', () async {
      SyncStore store = await open();
      final SyncOperation a = await add(store);
      await ack(store, a);
      final Map<String, String> noteAfterA = Map<String, String>.of(disk.docs);
      final SyncOperation b = await add(store, 'o2');
      await ack(store, b);
      // (a power cut before the note of b was written)
      disk.docs
        ..clear()
        ..addAll(noteAfterA);

      store = await open();
      expect((await add(store, 'o3')).seal!.prev, b.seal!.mac);
    });

    test(
      'the note is not a copy of server data and holds no operation',
      () async {
        final SyncStore store = await open();
        await ack(store, await add(store));
        expect(store.docKeys(), isEmpty);
        await store.clear();
        // A cleared store keeps counting and keeps its chain
        final SyncStore again = await open();
        expect(again.docKeys(), isEmpty);
      },
    );

    test(
      'a head note that cannot be written never fails the answers',
      () async {
        final SyncStore store = await open();
        final SyncOperation a = await add(store);
        disk.failDocWrites = true;
        await ack(store, a);
        expect(store.hasPending, isFalse);
      },
    );
  });

  group('rev2 M4: the server says what the signature was worth', () {
    test('SyncResult reads sig_status', () {
      final SyncResult r = SyncResult.fromJson(<String, Object?>{
        'op_id': 'x',
        'seq': 1,
        'status': 'applied',
        'sig_status': 'bad',
      });
      expect(r.sigStatus, 'bad');
      expect(
        SyncResult.fromJson(<String, Object?>{
          'op_id': 'x',
          'status': 'applied',
        }).sigStatus,
        isNull,
      );
    });

    test('a `bad` answer to a signed operation names the key that failed; a '
        'good one under that key clears it', () async {
      final SyncStore store = await open();
      expect(store.badKid, isNull);
      final SyncOperation a = await add(store);
      await store.applyResults(<SyncResult>[
        SyncResult.fromJson(_applied(a, sig: 'bad')),
      ]);
      expect(store.badKid, 1);

      final SyncOperation b = await add(store, 'o2');
      await store.applyResults(<SyncResult>[
        SyncResult.fromJson(_applied(b, sig: 'ok')),
      ]);
      expect(store.badKid, isNull);
    });

    test(
      'an unsigned operation answered `bad` or `missing` names no key',
      () async {
        current = null;
        final SyncStore store = await open();
        final SyncOperation a = await add(store);
        await store.applyResults(<SyncResult>[
          SyncResult.fromJson(_applied(a, sig: 'missing')),
        ]);
        expect(store.badKid, isNull);
      },
    );
  });

  group(
    'rev2 L4: a signed operation written somewhere else keeps its signature',
    () {
      test('toStored / fromStored', () async {
        final SyncStore store = await open();
        final SyncOperation a = await add(store);
        final Map<String, Object?> stored = (jsonDecode(
          jsonEncode(a.toStored()),
        ) as Map).cast<String, Object?>();
        expect(stored['raw'], a.seal!.raw);
        expect(stored['mac'], a.seal!.mac);
        expect(stored['prev'], a.seal!.prev);
        expect(stored['kid'], 1);
        // (an old reader takes the operation's own fields)
        expect(SyncOperation.fromJson(stored).opId, a.opId);
        final SyncOperation back = SyncOperation.fromStored(stored);
        expect(back.toWire(), a.toWire());

        current = null;
        final SyncOperation plain = await add(store, 'o2');
        expect(plain.toStored(), plain.toJson());
        expect(SyncOperation.fromStored(plain.toStored()).seal, isNull);
      });
    },
  );

  group(
    'F1 outcome: a signed line the server could not use (`unreadable`)',
    () {
      test(
        'leaves the queue, is kept as damage and shown as rejected; the rest '
        'goes on',
        () async {
          SyncStore store = await open();
          final SyncOperation a = await add(store);
          final SyncOperation b = await add(store, 'o2');
          final _Answers server = _Answers(
            (SyncBatchRequest r) => <String, Object?>{
              'results': <Object?>[
                for (final SyncOperation o in r.operations)
                  if (o.opId != a.opId) _applied(o, sig: 'chain_gap'),
              ],
              'unreadable': <Object?>[
                for (var i = 0; i < r.operations.length; i++)
                  if (r.operations[i].opId == a.opId)
                    <String, Object?>{
                      'index': i,
                      'op_id': null,
                      'seq': null,
                      'reason': 'invalid_payload',
                      'message': 'Navbatdagi yozuv buzilgan.',
                    },
              ],
            },
          );
          final SyncDrainResult r = await SyncSender(
            store: store,
            transport: server,
          ).drain();

          expect(r.drained, isTrue);
          expect(server.requests, hasLength(1));
          expect(store.hasPending, isFalse);
          // Not lost: the exact signed text is kept with the journal's damage
          expect(store.damaged.single.text, contains(a.seal!.mac));
          expect(
            jsonDecode(store.damaged.single.text.substring(9)),
            containsPair('raw', a.seal!.raw),
          );
          final SyncOperation shown = store.errors.single;
          expect(shown.opId, a.opId);
          expect(shown.status, SyncOpStatus.rejected);
          expect(shown.reason, 'invalid_payload');
          expect(shown.message, 'Navbatdagi yozuv buzilgan.');
          // The server holds nothing of it: the chain's head is the last operation it did answer
          expect((await add(store, 'o3')).seal!.prev, b.seal!.mac);

          store = await open();
          expect(
            store.pending.map((SyncOperation o) => o.payload['order']),
            <String>['o3'],
          );
          expect(store.damaged, hasLength(1));
          expect(store.errors.single.opId, a.opId);
        },
      );

      test('a batch that holds nothing else does not stop the queue', () async {
        final SyncStore store = await open();
        await add(store);
        final _Answers server = _Answers(
          (SyncBatchRequest r) => <String, Object?>{
            'results': <Object?>[],
            'unreadable': <Object?>[
              <String, Object?>{'index': 0, 'reason': 'invalid_payload'},
            ],
          },
        );
        final SyncSender sender = SyncSender(store: store, transport: server);
        final SyncDrainResult r = await sender.drain();
        expect(r.error, isNull);
        expect(r.left, 0);
        expect(sender.failures, 0);
      });

      test('an index outside the batch is ignored', () async {
        final SyncStore store = await open();
        final SyncOperation a = await add(store);
        final _Answers server = _Answers(
          (SyncBatchRequest r) => <String, Object?>{
            'results': <Object?>[_applied(a)],
            'unreadable': <Object?>[
              <String, Object?>{'index': 7, 'reason': 'invalid_payload'},
              <String, Object?>{'index': -1},
              'x',
            ],
          },
        );
        final SyncDrainResult r = await SyncSender(
          store: store,
          transport: server,
        ).drain();
        expect(r.drained, isTrue);
        expect(store.damaged, isEmpty);
        expect(store.errors, isEmpty);
      });
    },
  );
}
