import 'dart:async';

import 'package:dio/dio.dart' show RequestOptions;
import 'package:fake_async/fake_async.dart';
import 'package:pos_core/pos_core.dart' show PosMoney;
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

void main() {
  group('PosEvent parsing', () {
    const Map<String, Object?> order = <String, Object?>{
      'uuid': 'o1',
      'status': 'preparing',
      'store_id': 1,
      'branch_id': 5,
      'courier_id': null,
      'updated_at': '2026-10-22T08:00:00Z',
    };

    test('every POS event is typed', () {
      expect(
        PosEvent.fromWire('order.created', <String, Object?>{'order': order}),
        isA<PosOrderEvent>(),
      );
      final PosOrderEvent status = PosEvent.fromWire(
        'order.status_changed',
        <String, Object?>{'order': order},
      ) as PosOrderEvent;
      expect(status.order.status, 'preparing');
      expect(status.order.branchId, 5);
      expect(status.orderUuid, 'o1');

      final PosOrderItemsChanged items = PosEvent.fromWire(
        'order.items_changed',
        <String, Object?>{
          'order': order,
          'version': 7,
          'table_id': 3,
          'change': 'round_added',
          'item_ids': <int>[11, 12],
        },
      ) as PosOrderItemsChanged;
      expect(
        (items.version, items.tableId, items.change),
        (7, 3, 'round_added'),
      );
      expect(items.itemIds, <int>[11, 12]);

      final PosOrderBillChanged bill = PosEvent.fromWire(
        'order.bill_changed',
        <String, Object?>{
          'order': order,
          'bill': <String, Object?>{
            'uuid': 'b1',
            'status': 'paid',
            'fiscal_status': 'pending',
          },
          'change': 'paid',
        },
      ) as PosOrderBillChanged;
      expect(
        (bill.billUuid, bill.billStatus, bill.fiscalStatus),
        ('b1', 'paid', 'pending'),
      );
      expect(
        (PosEvent.fromWire('order.bill_changed', <String, Object?>{
          'order': order,
          'bill': null,
          'change': 'x',
        }) as PosOrderBillChanged).billUuid,
        isNull,
      );

      final PosKitchenItemChanged kitchen = PosEvent.fromWire(
        'kitchen.item_status_changed',
        <String, Object?>{
          'order': order,
          'item': <String, Object?>{
            'id': 812,
            'kitchen_status': 'ready',
            'ready_at': '2026-10-22T08:00:00Z',
          },
          'items_total': 3,
          'items_ready': 2,
        },
      ) as PosKitchenItemChanged;
      expect(
        (kitchen.itemId, kitchen.kitchenStatus, kitchen.itemsReady),
        (812, 'ready', 2),
      );

      final PosShiftChanged shift = PosEvent.fromWire(
        'shift.changed',
        <String, Object?>{
          'shift': <String, Object?>{
            'uuid': 's1',
            'status': 'closed',
            'store_id': 1,
            'branch_id': 5,
            'device_uuid': 'd1',
            'business_date': '2026-10-22',
            'z_number': 4,
          },
          'change': 'closed',
          'changed_at': '2026-10-22T08:00:00Z',
        },
      ) as PosShiftChanged;
      expect(
        (shift.shiftUuid, shift.deviceUuid, shift.zNumber, shift.change),
        ('s1', 'd1', 4, 'closed'),
      );

      final PosBookingChanged booking = PosEvent.fromWire(
        'booking.changed',
        <String, Object?>{
          'booking': <String, Object?>{
            'uuid': 'bk',
            'status': 'confirmed',
            'branch_id': 5,
            'version': 3,
            'starts_at': '2026-10-23T13:00:00Z',
            'ends_at': '2026-10-23T18:00:00Z',
          },
        },
      ) as PosBookingChanged;
      expect((booking.bookingUuid, booking.version), ('bk', 3));
      expect(booking.startsAt, DateTime.utc(2026, 10, 23, 13));

      final PosFiscalReceiptUpdated receipt = PosEvent.fromWire(
        'fiscal.receipt_updated',
        <String, Object?>{
          'receipt': <String, Object?>{
            'uuid': 'r1',
            'type': 'sale',
            'status': 'issued',
            'store_id': 1,
            'branch_id': 5,
            'subject_type': 'pos_bill',
            'subject_id': 9,
          },
        },
      ) as PosFiscalReceiptUpdated;
      expect(
        (receipt.receiptUuid, receipt.status, receipt.subjectId),
        ('r1', 'issued', 9),
      );

      final PosMenuAvailabilityChanged menu = PosEvent.fromWire(
        'menu.availability_changed',
        <String, Object?>{
          'store_id': 1,
          'branch_id': 5,
          'reason': 'stop_list',
          'items': <Object?>[
            <String, Object?>{'type': 'variant', 'id': 41, 'available': false},
          ],
          'changed_at': '2026-10-22T08:00:00Z',
        },
      ) as PosMenuAvailabilityChanged;
      expect(menu.reason, 'stop_list');
      expect(menu.items.single, (type: 'variant', id: 41, available: false));
    });

    test('unknown or broken events never throw', () {
      expect(
        PosEvent.fromWire('something.else', <String, Object?>{}),
        isA<PosUnknownEvent>(),
      );
      expect(
        PosEvent.fromWire('order.created', <String, Object?>{'order': 5}),
        isA<PosUnknownEvent>(),
      );
      expect(
        PosEvent.fromWire('shift.changed', <String, Object?>{}),
        isA<PosUnknownEvent>(),
      );
    });
  });

  group('PosRealtime', () {
    late Harness h;
    late FakePosRealtimeHub hub;
    late PosRealtime rt;

    setUp(() async {
      h = Harness();
      hub = FakePosRealtimeHub();
      h.state.onEvent = hub.broadcast;
      await h.signIn();
      rt = PosRealtime.create(
        client: h.client,
        terminal: h.client.auth.terminal!,
        apiBaseUrl: 'https://api.test/api',
        appKey: FakePosState.realtimeKey,
        connector: hub.connect,
      )!;
    });

    tearDown(() => rt.dispose());

    Future<void> settle() =>
        Future<void>.delayed(const Duration(milliseconds: 20));

    test('subscribes to private-pos-branch.{id} with the terminal token and goes live', () async {
      final List<PosLiveState> states = <PosLiveState>[];
      rt.states.listen(states.add);
      int resyncs = 0;
      rt.resubscribed.listen((_) => resyncs++);
      expect(rt.state, PosLiveState.off);
      rt.start();
      await settle();
      expect(rt.channel, 'private-pos-branch.1');
      expect(rt.isLive, isTrue);
      expect(
        states,
        containsAllInOrder(<PosLiveState>[
          PosLiveState.connecting,
          PosLiveState.live,
        ]),
      );
      expect(resyncs, 1);
      final RequestOptions auth = h.adapter
          .to('POST', '/broadcasting/auth')
          .single;
      expect(
        auth.headers['Authorization'],
        'Bearer ${FakePosState.terminalToken}',
      );
    });

    test('events from the API arrive typed', () async {
      rt.start();
      await settle();
      final List<PosEvent> events = <PosEvent>[];
      rt.events.listen(events.add);
      final PosOrder o = await h.pos.orders.open(
        channel: PosChannel.hall,
        tableId: 1,
        guestsCount: 2,
      );
      await h.pos.orders.setGuests(o.uuid, version: o.version, guestsCount: 3);
      await h.pos.shifts.open(openingCash: PosMoney.zero);
      await settle();
      expect(events.map((PosEvent e) => e.runtimeType), <Type>[
        PosOrderEvent,
        PosOrderItemsChanged,
        PosShiftChanged,
      ]);
      expect((events[1] as PosOrderItemsChanged).change, 'guests_changed');
      expect(events.first.orderUuid, o.uuid);
    });

    test(
      'a dropped connection reconnects and fires resubscribed again',
      () async {
        int resyncs = 0;
        rt.resubscribed.listen((_) => resyncs++);
        rt.start();
        await settle();
        hub.dropAll();
        await settle();
        expect(rt.state, isNot(PosLiveState.live));
        await Future<void>.delayed(const Duration(milliseconds: 1200));
        expect(rt.isLive, isTrue);
        expect(resyncs, 2);
        expect(hub.connects, 2);
      },
    );

    test(
      'a refused signature is subscriptionFailed (polling keeps going)',
      () async {
        h.adapter.respond = (RequestOptions o) =>
            o.uri.path.contains('broadcasting')
            ? const FakeResponse(403, <String, Object?>{'message': 'Forbidden'})
            : null;
        rt.start();
        await settle();
        expect(rt.state, PosLiveState.subscriptionFailed);
      },
    );

    test('no key or no branch → no realtime (polling only)', () {
      expect(
        PosRealtime.create(
          client: h.client,
          terminal: h.client.auth.terminal!,
          apiBaseUrl: 'https://api.test/api',
          appKey: '',
        ),
        isNull,
      );
    });
  });

  group('PosRefreshScheduler', () {
    test('polls every 15 s without realtime', () {
      fakeAsync((FakeAsync async) {
        final List<PosRefreshReason> reasons = <PosRefreshReason>[];
        final PosRefreshScheduler s = PosRefreshScheduler(
          onRefresh: (PosRefreshReason r) async => reasons.add(r),
        )..start();
        async.elapse(const Duration(seconds: 46));
        expect(reasons, <PosRefreshReason>[
          PosRefreshReason.poll,
          PosRefreshReason.poll,
          PosRefreshReason.poll,
        ]);
        unawaited(s.refreshNow());
        async.flushMicrotasks();
        expect(reasons.last, PosRefreshReason.manual);
        unawaited(s.dispose());
        async.elapse(const Duration(minutes: 1));
        expect(reasons, hasLength(4));
      });
    });

    test('live: resync at once, events debounced, safety poll every 60 s', () {
      fakeAsync((FakeAsync async) {
        final Harness h = Harness();
        final FakePosRealtimeHub hub = FakePosRealtimeHub();
        h.state.onEvent = hub.broadcast;
        unawaited(h.signIn());
        async.elapse(const Duration(seconds: 1));
        final PosRealtime rt = PosRealtime.create(
          client: h.client,
          terminal: h.client.auth.terminal!,
          apiBaseUrl: 'https://api.test/api',
          appKey: FakePosState.realtimeKey,
          connector: hub.connect,
        )!;
        final List<PosRefreshReason> reasons = <PosRefreshReason>[];
        final PosRefreshScheduler s = PosRefreshScheduler(
          realtime: rt,
          eventFilter: (PosEvent e) => e is PosOrderItemsChanged,
          onRefresh: (PosRefreshReason r) async => reasons.add(r),
        )..start();
        rt.start();
        async.elapse(const Duration(milliseconds: 50));
        expect(rt.isLive, isTrue);
        expect(reasons, <PosRefreshReason>[PosRefreshReason.resync]);

        for (var i = 0; i < 3; i++) {
          hub.broadcast('order.items_changed', <String, Object?>{
            'order': <String, Object?>{'uuid': 'o1', 'status': 'preparing'},
            'change': 'sent',
          });
        }
        hub.broadcast('booking.changed', <String, Object?>{
          'booking': <String, Object?>{'uuid': 'b', 'status': 'confirmed'},
        });
        async.elapse(const Duration(seconds: 1));
        expect(reasons, <PosRefreshReason>[
          PosRefreshReason.resync,
          PosRefreshReason.event,
        ]);

        async.elapse(const Duration(seconds: 59));
        expect(reasons.last, PosRefreshReason.safetyPoll);
        unawaited(s.dispose());
        unawaited(rt.dispose());
        async.flushMicrotasks();
      });
    });

    test(
      'one refresh at a time; a request during a refresh runs once after it',
      () {
        fakeAsync((FakeAsync async) {
          int running = 0;
          int maxRunning = 0;
          final List<PosRefreshReason> reasons = <PosRefreshReason>[];
          final PosRefreshScheduler s = PosRefreshScheduler(
            pollInterval: Duration.zero,
            onRefresh: (PosRefreshReason r) async {
              running++;
              maxRunning = running > maxRunning ? running : maxRunning;
              reasons.add(r);
              await Future<void>.delayed(const Duration(seconds: 1));
              running--;
            },
          )..start();
          unawaited(s.refreshNow());
          unawaited(s.refreshNow());
          unawaited(s.refreshNow());
          async.elapse(const Duration(seconds: 5));
          expect(maxRunning, 1);
          expect(reasons, <PosRefreshReason>[
            PosRefreshReason.manual,
            PosRefreshReason.manual,
          ]);
          unawaited(s.dispose());
        });
      },
    );
  });
}
