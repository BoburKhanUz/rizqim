import 'dart:math';

import 'package:pos_sync/pos_sync.dart';
import 'package:test/test.dart';

/// Wave M5 S1 review (design bug 5): the journal was lost while the pairing survived, so the till numbers its
/// operations from 1 again and the server, which already holds those numbers, answers 409 `seq_reused` for every
/// batch. The till adopts the server's `last_seq`: new operations go after it, and the operations the server never
/// took are renumbered (their `op_id` and payload, the idempotency key included, stay).
class ServerWithHistory implements SyncTransport {
  ServerWithHistory(Iterable<int> seqs) {
    taken.addAll(seqs);
  }

  /// Sequence numbers this device has already used on the server.
  final Set<int> taken = <int>{};
  final Map<String, int> applied = <String, int>{};
  final List<List<int>> batches = <List<int>>[];

  int get lastSeq => taken.isEmpty ? 0 : taken.reduce(max);

  @override
  Future<SyncBatchResponse> send(SyncBatchRequest request) async {
    batches.add(<int>[for (final SyncOperation o in request.operations) o.seq]);
    final List<SyncResult> results = <SyncResult>[];
    for (final SyncOperation o in request.operations) {
      if (applied.containsKey(o.opId)) {
        results.add(
          SyncResult(
            opId: o.opId,
            seq: applied[o.opId]!,
            status: SyncOpStatus.applied,
            duplicate: true,
          ),
        );
        continue;
      }
      if (taken.contains(o.seq)) {
        throw SyncRefused(
          'Navbat raqami (${o.seq}) boshqa amalga berilgan.',
          status: 409,
          reason: 'seq_reused',
        );
      }
      taken.add(o.seq);
      applied[o.opId] = o.seq;
      results.add(
        SyncResult(opId: o.opId, seq: o.seq, status: SyncOpStatus.applied),
      );
    }
    return SyncBatchResponse(results: results);
  }
}

void main() {
  late MemorySyncStorage disk;
  late SyncStore store;

  setUp(() async {
    disk = MemorySyncStorage();
    store = await SyncStore.open(disk, random: Random(5));
  });

  Future<List<SyncOperation>> queue(int n) async => <SyncOperation>[
    for (var i = 0; i < n; i++)
      await store.enqueue(SyncOpType.billPay, <String, Object?>{
        'order': 'o$i',
        'idempotency_key': 'key-$i',
      }, staffId: 3),
  ];

  group('SyncStore.adoptServerSeq', () {
    test('unsent operations numbered below the server\'s last_seq are renumbered after it; op ids, payloads and '
        'their order stay; the next operation follows', () async {
      final List<SyncOperation> before = await queue(3);
      expect(before.map((SyncOperation o) => o.seq), <int>[1, 2, 3]);

      expect(await store.adoptServerSeq(40), isTrue);

      expect(store.pending.map((SyncOperation o) => o.seq), <int>[41, 42, 43]);
      expect(
        store.pending.map((SyncOperation o) => o.opId),
        before.map((SyncOperation o) => o.opId),
      );
      expect(
        store.pending.map((SyncOperation o) => o.payload['idempotency_key']),
        <String>['key-0', 'key-1', 'key-2'],
      );
      expect(store.lastSeq, 43);
      final SyncOperation next = (await queue(1)).single;
      expect(next.seq, 44);
    });

    test('the new numbers are on the disk: a restart reads them', () async {
      final List<SyncOperation> before = await queue(2);
      await store.adoptServerSeq(40);
      final SyncStore again = await SyncStore.open(disk);
      expect(again.pending.map((SyncOperation o) => o.seq), <int>[41, 42]);
      expect(
        again.pending.map((SyncOperation o) => o.opId),
        before.map((SyncOperation o) => o.opId),
      );
      expect(again.lastSeq, 42);
    });

    test('an empty queue only moves the counter', () async {
      expect(await store.adoptServerSeq(40), isTrue);
      expect((await queue(1)).single.seq, 41);
      expect((await SyncStore.open(disk)).lastSeq, 41);
    });

    test('nothing collides: nothing changes', () async {
      await queue(2);
      await store.applyResults(<SyncResult>[
        SyncResult(
          opId: store.pending.first.opId,
          seq: 1,
          status: SyncOpStatus.applied,
        ),
      ]);
      final int lines = disk.lines.length;
      expect(await store.adoptServerSeq(1), isFalse);
      expect(store.pending.single.seq, 2);
      expect(store.lastSeq, 2);
      expect(disk.lines.length, lines);
    });

    test('an acknowledged operation keeps its number', () async {
      await queue(2);
      final String first = store.pending.first.opId;
      await store.applyResults(<SyncResult>[
        SyncResult(opId: first, seq: 1, status: SyncOpStatus.rejected),
      ]);
      await store.adoptServerSeq(40);
      expect(store.errors.single.seq, 1);
      expect(store.pending.single.seq, 41);
    });

    test('the disk fails: the numbers stay as they were', () async {
      await queue(2);
      final _NoRewrite failing = _NoRewrite(disk);
      final SyncStore s = await SyncStore.open(failing);
      failing.fail = true;
      await expectLater(s.adoptServerSeq(40), throwsStateError);
      expect(s.pending.map((SyncOperation o) => o.seq), <int>[1, 2]);
      expect(s.lastSeq, 2);
    });
  });

  group('SyncSender', () {
    test('409 seq_reused: the sender asks for the server\'s last_seq, the queue is renumbered and sent in the same '
        'attempt', () async {
      final ServerWithHistory server = ServerWithHistory(<int>[
        for (var i = 1; i <= 40; i++) i,
      ]);
      final List<SyncOperation> before = await queue(3);
      var asked = 0;
      final SyncSender sender = SyncSender(store: store, transport: server)
        ..serverLastSeq = () async {
          asked++;
          return server.lastSeq;
        };

      final SyncDrainResult r = await sender.drain();

      expect(r.drained, isTrue, reason: '${r.error}');
      expect(asked, 1);
      expect(server.batches, <List<int>>[
        <int>[1, 2, 3],
        <int>[41, 42, 43],
      ]);
      expect(server.applied.keys, before.map((SyncOperation o) => o.opId));
      expect(store.pendingCount, 0);
      expect((await queue(1)).single.seq, 44);
    });

    test('409 seq_reused and the server\'s number cannot be read: the queue stays, the attempt fails as '
        'before', () async {
      final ServerWithHistory server = ServerWithHistory(<int>[1, 2, 3]);
      await queue(2);
      final SyncSender sender = SyncSender(store: store, transport: server)
        ..serverLastSeq = () async => null;
      final SyncDrainResult r = await sender.drain();
      expect(
        r.error,
        isA<SyncRefused>().having(
          (SyncRefused e) => e.reason,
          'reason',
          'seq_reused',
        ),
      );
      expect(store.pending.map((SyncOperation o) => o.seq), <int>[1, 2]);
      expect(server.batches, hasLength(1));
    });

    test('409 seq_reused although nothing collides with the number the server names: one attempt, no '
        'loop', () async {
      final ServerWithHistory server = ServerWithHistory(<int>[1, 2, 3]);
      await queue(2);
      final SyncSender sender = SyncSender(store: store, transport: server)
        ..serverLastSeq = () async => 0;
      final SyncDrainResult r = await sender.drain();
      expect(r.error, isA<SyncRefused>());
      expect(server.batches, hasLength(1));
    });
  });
}

class _NoRewrite implements SyncStorage {
  _NoRewrite(this._inner);

  final MemorySyncStorage _inner;
  bool fail = false;

  @override
  Future<void> appendLine(String line) => _inner.appendLine(line);

  @override
  Future<void> deleteDoc(String key) => _inner.deleteDoc(key);

  @override
  Future<List<String>> docKeys() => _inner.docKeys();

  @override
  Future<String?> readDoc(String key) => _inner.readDoc(key);

  @override
  Future<List<String>> readLines() => _inner.readLines();

  @override
  Future<void> replaceLines(List<String> next) {
    if (fail) throw StateError('disk full');
    return _inner.replaceLines(next);
  }

  @override
  Future<void> writeDoc(String key, String content) =>
      _inner.writeDoc(key, content);
}
