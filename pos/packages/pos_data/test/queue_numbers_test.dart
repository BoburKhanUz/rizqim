import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P6-13: order numbers and calling — the bootstrap's `queue` (the till's blocks), an order's `queue`, the bill's
/// talon, the `queue.*` events and the repository's requests.
void main() {
  group('models', () {
    test('the bootstrap queue section gives the blocks; an older server reads as numbering off', () {
      final PosBootstrap b = PosBootstrap.fromJson(
        PosJson(<String, Object?>{
          ...(fixtureData('bootstrap')! as Map<Object?, Object?>)
              .cast<String, Object?>(),
          'queue': <String, Object?>{
            'numbers': true,
            'calling': true,
            'business_date': '2026-10-22',
            'block_size': 100,
            'low_water': 20,
            'blocks': <Object?>[
              <String, Object?>{'start': 101, 'end': 200, 'next': 110},
            ],
            'next_number': 110,
          },
        }),
      );
      final PosQueueState q = b.queue;
      expect(q.numbers, isTrue);
      expect(q.calling, isTrue);
      expect(q.businessDate, '2026-10-22');
      expect(q.blocks.single.start, 101);
      expect(q.blocks.single.next, 110);
      expect(q.nextNumber, 110);

      final PosBootstrap old = PosBootstrap.fromJson(
        PosJson(
          (fixtureData('bootstrap')! as Map<Object?, Object?>)
              .cast<String, Object?>(),
        ),
      );
      expect(old.queue.numbers, isFalse);
      expect(old.queue.blocks, isEmpty);
    });

    test('a till running low on numbers asks for its next block', () async {
      final SyncStore store = await SyncStore.open(MemorySyncStorage());
      const PosQueueState q = PosQueueState(
        numbers: true,
        businessDate: '2026-10-22',
        blocks: <QueueRange>[QueueRange(start: 1, end: 100, next: 70)],
      );
      expect(q.runningLow(store), isFalse);
      await store.noteQueueNumber('2026-10-22', 85);
      expect(q.runningLow(store), isTrue);
      expect(const PosQueueState().runningLow(store), isFalse);
    });

    test('an order carries its number and pickup state; the bill its talon', () {
      final PosOrder order = PosOrder.fromJson(
        PosJson.of(<String, Object?>{
          'uuid': 'u',
          'status': 'delivered',
          'channel': 'pos_takeaway',
          'version': 4,
          'items': <Object?>[],
          'queue': <String, Object?>{
            'number': 57,
            'business_date': '2026-10-22',
            'status': 'ready',
            'ready_at': '2026-10-22T13:05:00Z',
            'called_at': null,
            'call_count': 0,
            'handed_at': null,
          },
        }),
      );
      expect(order.queue?.number, 57);
      expect(order.queue?.status, PosPickupStatus.ready);
      expect(order.queue?.status?.label, 'Tayyor');
      expect(order.queue?.readyAt, DateTime.utc(2026, 10, 22, 13, 5));

      final Map<String, Object?> bill =
          ((fixtureData('bills_create')! as Map<Object?, Object?>)['bills']!
                      as List<Object?>)
                  .first! as Map<String, Object?>;
      final PosBill plain = PosBill.fromJson(PosJson(bill));
      expect(plain.ticket, isNull);
      final PosBill withTicket = PosBill.fromJson(
        PosJson(<String, Object?>{
          ...bill,
          'ticket': <String, Object?>{
            'number': 57,
            'business_date': '2026-10-22',
            'order_label': 'Olib ketish',
            'message': "Buyurtmangiz tayyor bo'lganda raqamingiz ekranda chiqadi va chaqiriladi.",
          },
        }),
      );
      final PosQueueTicket t = withTicket.ticket!;
      expect(t.number, 57);
      final QueueSlip slip = t.slip(
        header: const ReceiptHeader(storeName: 'Osh markazi'),
        createdAt: DateTime.utc(2026, 10, 22, 13),
        items: const <String>['2 x Osh'],
      );
      expect(slip.number, '57');
      expect(slip.orderLabel, 'Olib ketish');
      expect(slip.items, <String>['2 x Osh']);
    });

    test('queue events: ready, called again, handed, canceled', () {
      Map<String, Object?> data(String? status, {bool called = false}) =>
          <String, Object?>{
            'order': <String, Object?>{'uuid': 'o-1', 'status': 'delivered'},
            'queue': <String, Object?>{
              'number': 57,
              'business_date': '2026-10-22',
              'status': status,
              'channel': 'pos_takeaway',
              'call_count': called ? 2 : 0,
              'called': called,
            },
          };
      final PosQueueEvent ready =
          PosEvent.fromWire('queue.ready', data('ready')) as PosQueueEvent;
      expect(ready.orderUuid, 'o-1');
      expect(ready.number, 57);
      expect(ready.announce, isTrue);
      expect(ready.called, isFalse);
      final PosQueueEvent again =
          PosEvent.fromWire('queue.ready', data('ready', called: true))
              as PosQueueEvent;
      expect(again.called, isTrue);
      expect(again.callCount, 2);
      final PosQueueEvent handed =
          PosEvent.fromWire('queue.handed', data('handed')) as PosQueueEvent;
      expect(handed.announce, isFalse);
      expect(handed.pickupStatus, 'handed');
      expect(
        PosEvent.fromWire('queue.canceled', data(null)),
        isA<PosQueueEvent>(),
      );
      expect(
        PosEvent.fromWire('queue.preparing', <String, Object?>{}),
        isA<PosUnknownEvent>(),
      );
    });
  });

  group('repository', () {
    late Harness h;

    setUp(() async {
      h = Harness();
      await h.signIn();
    });

    test('board, blocks and the number moves go to their endpoints with an Idempotency-Key', () async {
      final Map<String, Object?> entry = <String, Object?>{
        'order_uuid': 'o-1',
        'number': 57,
        'business_date': '2026-10-22',
        'status': 'ready',
        'channel': 'pos_takeaway',
        'order_status': 'delivered',
        'ready_at': '2026-10-22T13:05:00Z',
        'called_at': null,
        'call_count': 1,
        'handed_at': null,
        'opened_at': '2026-10-22T13:00:00Z',
      };
      h.adapter.respond = (o) {
        final String p = o.uri.path;
        if (p.endsWith('/pos/queue')) {
          return FakeResponse(200, <String, Object?>{
            'success': true,
            'data': <String, Object?>{
              'business_date': '2026-10-22',
              'preparing': <Object?>[],
              'ready': <Object?>[entry],
            },
          });
        }
        if (p.endsWith('/pos/queue/blocks')) {
          return const FakeResponse(200, <String, Object?>{
            'success': true,
            'data': <String, Object?>{
              'numbers': true,
              'calling': true,
              'business_date': '2026-10-22',
              'block_size': 100,
              'low_water': 20,
              'blocks': <Object?>[
                <String, Object?>{'start': 1, 'end': 100, 'next': 90},
                <String, Object?>{'start': 201, 'end': 300, 'next': 201},
              ],
              'next_number': 90,
            },
          });
        }
        if (p.contains('/pos/orders/o-1/')) {
          return FakeResponse(200, <String, Object?>{
            'success': true,
            'data': entry,
          });
        }
        return null;
      };
      final PosQueueRepository queue = PosQueueRepository(h.pos.http);

      final PosQueueBoard board = await queue.board();
      expect(board.ready.single.number, 57);
      expect(board.ready.single.status, PosPickupStatus.ready);
      expect(board.preparing, isEmpty);

      final PosQueueState state = await queue.reserveBlocks();
      expect(state.blocks.map((QueueRange r) => r.start), <int>[1, 201]);
      expect(h.last.headers['Idempotency-Key'], matches(uuidV4));

      for (final String action in <String>['ready', 'call', 'handed']) {
        final PosQueueEntry e = await switch (action) {
          'ready' => queue.ready('o-1'),
          'call' => queue.call('o-1'),
          _ => queue.handed('o-1'),
        };
        expect(e.callCount, 1);
        expect(h.last.method, 'POST');
        expect(h.last.uri.path, endsWith('/pos/orders/o-1/$action'));
        expect(h.last.headers['Idempotency-Key'], matches(uuidV4));
      }
    });
  });
}
