import 'package:pos_sync/pos_sync.dart';
import 'package:test/test.dart';

/// P6-13: queue numbers from the till's blocks — never twice in a business day, through restarts and compaction,
/// and each till only within its own blocks (two tills offline never print the same number).
void main() {
  DateTime now = DateTime.utc(2026, 10, 22, 13);
  DateTime clock() => now;
  Future<SyncStore> openStore(SyncStorage storage) =>
      SyncStore.open(storage, clock: clock);

  const String day = '2026-10-22';
  const List<QueueRange> tillA = <QueueRange>[QueueRange(start: 1, end: 100)];
  const List<QueueRange> tillB = <QueueRange>[QueueRange(start: 101, end: 200)];

  test(
    'the rule takes the next free number of the blocks above the last one used',
    () {
      expect(QueueNumberRule.next(tillA, 0), 1);
      expect(QueueNumberRule.next(tillA, 41), 42);
      // The server already saw 1..9 used (next 10): the till does not go below
      expect(
        QueueNumberRule.next(<QueueRange>[
          const QueueRange(start: 1, end: 100, next: 10),
        ], 3),
        10,
      );
      // A block used up: the next block
      const List<QueueRange> two = <QueueRange>[
        QueueRange(start: 201, end: 300),
        QueueRange(start: 1, end: 100),
      ];
      expect(QueueNumberRule.next(two, 100), 201);
      expect(QueueNumberRule.next(two, 300), isNull);
      expect(QueueNumberRule.remaining(two, 90), 110);
      expect(
        QueueRange.fromJson(<String, Object?>{'start': 5, 'end': 9}).next,
        5,
      );
    },
  );

  test('two tills offline never print the same number, and a restart never reuses one', () async {
    final MemorySyncStorage diskA = MemorySyncStorage();
    final MemorySyncStorage diskB = MemorySyncStorage();
    final SyncStore a = await openStore(diskA);
    final SyncStore b = await openStore(diskB);

    final List<int?> numbers = <int?>[
      await a.takeQueueNumber(day, tillA),
      await b.takeQueueNumber(day, tillB),
      await a.takeQueueNumber(day, tillA),
      await b.takeQueueNumber(day, tillB),
    ];
    expect(numbers, <int>[1, 101, 2, 102]);

    // The app is killed and opened again: the numbering goes on
    final SyncStore reopened = await openStore(diskA);
    expect(reopened.lastQueueNumber(day), 2);
    expect(await reopened.takeQueueNumber(day, tillA), 3);

    // The server gave an order another number (it lies higher): never below it again
    await reopened.noteQueueNumber(day, 7);
    await reopened.noteQueueNumber(day, 5);
    expect(await reopened.takeQueueNumber(day, tillA), 8);
    expect(reopened.queueNumbersLeft(day, tillA), 92);
  });

  test('the numbers survive compaction and restart from the blocks of a new business day', () async {
    final MemorySyncStorage disk = MemorySyncStorage();
    final SyncStore store = await openStore(disk);
    for (var i = 0; i < 3; i++) {
      await store.takeQueueNumber(day, tillA);
    }
    await store.markReloaded(); // compacts the journal into its meta line

    final SyncStore reopened = await openStore(disk);
    expect(reopened.lastQueueNumber(day), 3);
    expect(await reopened.takeQueueNumber(day, tillA), 4);

    // The next business day: the new day's block from 1
    now = now.add(const Duration(days: 1));
    expect(reopened.lastQueueNumber('2026-10-23'), 0);
    expect(await reopened.takeQueueNumber('2026-10-23', tillA), 1);
    // An earlier day's note does not take the till back
    await reopened.noteQueueNumber(day, 50);
    expect(reopened.lastQueueNumber('2026-10-23'), 1);

    // Out of numbers: none (the server numbers the order)
    expect(
      await reopened.takeQueueNumber('2026-10-23', const <QueueRange>[
        QueueRange(start: 1, end: 1),
      ]),
      isNull,
    );
  });
}
