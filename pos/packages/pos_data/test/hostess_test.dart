import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P6-24 / P6-26: hostes stoli (kutish ro'yxati, stol belgisi), stolni boshqa ofitsiantga berish, mehmon bo'yicha
/// bo'lish, stol broni qoidalari: yo'l, tana, `Idempotency-Key` va javobni o'qish.
void main() {
  late Harness h;

  setUp(() async {
    h = Harness();
    await h.signIn();
  });

  Map<String, Object?> entry({
    String status = 'waiting',
    int? position = 1,
    String? phone = '+998901234567',
    int version = 1,
  }) => <String, Object?>{
    'uuid': 'w1',
    'branch_id': 5,
    'business_date': '2026-10-22',
    'guest_name': 'Akmal',
    'guest_phone': phone,
    'guest_phone_masked': '+99890*****67',
    'party_size': 4,
    'quoted_wait_minutes': 25,
    'note': null,
    'status': status,
    'status_label': 'Kutmoqda',
    'position': position,
    'waited_minutes': 30,
    'dining_table_id': null,
    'table_name': null,
    'order_uuid': null,
    'notified_at': null,
    'seated_at': null,
    'closed_at': null,
    'version': version,
    'created_at': '2026-10-22T12:30:00Z',
  };

  Future<RequestOptions> respond(
    Object? data,
    Future<Object?> Function() action,
  ) async {
    h.adapter.respond = (RequestOptions o) => FakeResponse(
      200,
      <String, Object?>{'success': true, 'message': 'ok', 'data': data},
    );
    await action();
    return h.last;
  }

  group('waitlist', () {
    test('reads the day with its summary and queue positions', () async {
      late PosWaitlist list;
      final RequestOptions r = await respond(<String, Object?>{
        'branch_id': 5,
        'date': '2026-10-22',
        'entries': <Object?>[entry(), entry(status: 'seated', position: null)],
        'summary': <String, Object?>{
          'total': 2,
          'waiting': 1,
          'seated': 1,
          'canceled': 0,
          'no_show': 0,
          'party_size_total': 8,
          'avg_quoted_wait_minutes': 25,
          'avg_seated_wait_minutes': 18,
          'longest_waiting_minutes': 30,
        },
      }, () async => list = await h.pos.hostess.waitlist(status: 'waiting'));

      expect(r.method, 'GET');
      expect(r.uri.path, '/api/v1/pos/waitlist');
      expect(r.uri.queryParameters, <String, String>{'status': 'waiting'});
      expect(list.entries, hasLength(2));
      expect(list.waiting.single.position, 1);
      expect(list.waiting.single.guestPhone, '+998901234567');
      expect(list.waiting.single.guestPhoneMasked, '+99890*****67');
      expect(list.waiting.single.isOverQuoted, isTrue);
      expect(list.summary.partySizeTotal, 8);
      expect(list.summary.avgSeatedWaitMinutes, 18);
      expect(list.summary.longestWaitingMinutes, 30);
    });

    test('writes carry an Idempotency-Key and only what is given', () async {
      late PosWaitlistEntry made;
      RequestOptions r = await respond(
        entry(),
        () async => made = await h.pos.hostess.add(
          guestName: 'Akmal',
          partySize: 4,
          guestPhone: '90 123 45 67',
          quotedWaitMinutes: 25,
        ),
      );
      expect(r.method, 'POST');
      expect(r.uri.path, '/api/v1/pos/waitlist');
      expect(r.headers['Idempotency-Key'], matches(uuidV4));
      expect(Harness.bodyOf(r), <String, Object?>{
        'guest_name': 'Akmal',
        'party_size': 4,
        'guest_phone': '90 123 45 67',
        'quoted_wait_minutes': 25,
      });
      expect(made.uuid, 'w1');
      expect(made.isWaiting, isTrue);

      r = await respond(
        entry(version: 2),
        () => h.pos.hostess.update('w1', partySize: 5, version: 1),
      );
      expect(r.method, 'PATCH');
      expect(r.uri.path, '/api/v1/pos/waitlist/w1');
      expect(Harness.bodyOf(r), <String, Object?>{
        'party_size': 5,
        'version': 1,
      });

      r = await respond(entry(), () => h.pos.hostess.notify('w1'));
      expect(r.uri.path, '/api/v1/pos/waitlist/w1/notify');
      expect(Harness.bodyOf(r), isEmpty);

      r = await respond(
        entry(status: 'seated', position: null),
        () => h.pos.hostess.seat(
          'w1',
          diningTableId: 7,
          openOrder: true,
          waiterId: 9,
        ),
      );
      expect(r.uri.path, '/api/v1/pos/waitlist/w1/seat');
      expect(Harness.bodyOf(r), <String, Object?>{
        'dining_table_id': 7,
        'open_order': true,
        'waiter_id': 9,
      });

      r = await respond(
        entry(status: 'canceled', position: null),
        () => h.pos.hostess.cancel('w1', reason: 'Ketdi'),
      );
      expect(r.uri.path, '/api/v1/pos/waitlist/w1/cancel');
      expect(Harness.bodyOf(r), <String, Object?>{'reason': 'Ketdi'});

      r = await respond(
        entry(status: 'no_show', position: null),
        () => h.pos.hostess.noShow('w1'),
      );
      expect(r.uri.path, '/api/v1/pos/waitlist/w1/no-show');
    });

    test('a closed entry is a conflict, a stale one too', () async {
      h.adapter.respond = (RequestOptions o) =>
          const FakeResponse(409, <String, Object?>{
            'success': false,
            'message': "Bu yozuv \"Joylashtirildi\" holatida",
            'data': <String, Object?>{'reason': 'entry_closed'},
          });
      await expectLater(
        h.pos.hostess.cancel('w1'),
        throwsA(isA<PosConflict>()),
      );
    });
  });

  group('table state and order handover', () {
    test('marks a table for cleaning and clears it', () async {
      late PosTableState state;
      RequestOptions r = await respond(
        <String, Object?>{
          'id': 7,
          'name': 'Stol 5',
          'service_state': 'cleaning',
          'service_state_at': '2026-10-22T13:00:00Z',
        },
        () async =>
            state = await h.pos.hostess.setTableCleaning(7, cleaning: true),
      );
      expect(r.method, 'PUT');
      expect(r.uri.path, '/api/v1/pos/tables/7/state');
      expect(r.headers['Idempotency-Key'], matches(uuidV4));
      expect(Harness.bodyOf(r), <String, Object?>{'state': 'cleaning'});
      expect(state.isCleaning, isTrue);

      r = await respond(<String, Object?>{
        'id': 7,
        'name': 'Stol 5',
        'service_state': null,
      }, () => h.pos.hostess.setTableCleaning(7, cleaning: false));
      expect(Harness.bodyOf(r), <String, Object?>{'state': 'free'});
    });

    test('transfers an order and numbers a line', () async {
      final Object? order = fixtureData('order_show');
      RequestOptions r = await respond(
        order,
        () => h.pos.hostess.transferOrder(
          'o1',
          version: 4,
          waiterId: 12,
          reason: 'Smena',
          approvalToken: 'tok',
        ),
      );
      expect(r.method, 'POST');
      expect(r.uri.path, '/api/v1/pos/orders/o1/transfer');
      expect(r.headers['Idempotency-Key'], matches(uuidV4));
      expect(Harness.bodyOf(r), <String, Object?>{
        'version': 4,
        'waiter_id': 12,
        'reason': 'Smena',
        'approval_token': 'tok',
      });

      r = await respond(
        order,
        () => h.pos.hostess.setLineSeat('o1', 33, version: 5, seatNo: 2),
      );
      expect(r.uri.path, '/api/v1/pos/orders/o1/items/33/seat');
      expect(Harness.bodyOf(r), <String, Object?>{'version': 5, 'seat_no': 2});

      r = await respond(
        order,
        () => h.pos.hostess.setLineSeat('o1', 33, version: 6, seatNo: null),
      );
      expect(Harness.bodyOf(r), <String, Object?>{
        'version': 6,
        'seat_no': null,
      });
    });

    test('an order without the permission asks for a manager', () async {
      h.adapter.respond = (RequestOptions o) =>
          const FakeResponse(403, <String, Object?>{
            'success': false,
            'message': "Bu amal uchun menejer tasdig'i kerak.",
            'data': <String, Object?>{
              'reason': 'approval_required',
              'permission': 'orders.transfer',
            },
          });
      await expectLater(
        h.pos.hostess.transferOrder('o1', version: 1, waiterId: 2),
        throwsA(
          isA<PosApprovalRequired>().having(
            (PosApprovalRequired e) => e.permission,
            'permission',
            'orders.transfer',
          ),
        ),
      );
    });
  });

  group('guest split, bookings and floor', () {
    test(
      'by-guest split sends its mode and the bill knows its guest',
      () async {
        late PosBilling billing;
        final Map<String, Object?> data = Map<String, Object?>.from(
          fixtureData('bills_create')! as Map<String, Object?>,
        );
        final List<Object?> bills = <Object?>[
          for (final Object? b in data['bills']! as List<Object?>)
            <String, Object?>{
              ...(b! as Map<String, Object?>),
              'split_mode': 'by_guest',
              'seat_no': 2,
              'guests_share': 1,
            },
        ];
        data['bills'] = bills;

        final RequestOptions r = await respond(
          data,
          () async => billing = await h.pos.billing.createBills(
            'o1',
            version: 3,
            split: const PosSplit.byGuest(),
          ),
        );
        expect(r.uri.path, '/api/v1/pos/orders/o1/bills');
        expect(Harness.bodyOf(r), <String, Object?>{
          'version': 3,
          'mode': 'by_guest',
        });
        expect(billing.bills.first.seatNo, 2);
        expect(billing.bills.first.splitMode, 'by_guest');
      },
    );

    test('a booking carries the deposit the rule asks for', () async {
      final Map<String, Object?> base = Map<String, Object?>.from(
        fixtureData('booking_show')! as Map<String, Object?>,
      );
      final PosBooking booking = PosBooking.fromJson(
        PosJson(<String, Object?>{
          ...base,
          'deposit_required': '400000.00',
          'deposit_missing': '150000.00',
        }),
      );
      expect(booking.depositRequired, const PosMoney.som(400000));
      expect(booking.depositMissing, const PosMoney.som(150000));
      // A list row has neither
      expect(PosBooking.fromJson(PosJson(base)).depositRequired, PosMoney.zero);
    });

    test('the floor knows a table marked for cleaning', () {
      final FloorTable table = FloorTable.fromJson(
        const PosJson(<String, Object?>{
          'id': 7,
          'name': 'Stol 5',
          'kind': 'table',
          'status': 'free',
          'service_state': 'cleaning',
          'orders': <Object?>[],
        }),
      );
      expect(table.isCleaning, isTrue);
      expect(table.status, FloorTableStatus.free);
      expect(
        FloorTable.fromJson(
          const PosJson(<String, Object?>{
            'id': 8,
            'name': 'Stol 6',
            'kind': 'table',
            'orders': <Object?>[],
          }),
        ).isCleaning,
        isFalse,
      );
    });

    test('the bootstrap carries the booking rules only with the module', () {
      final Map<String, Object?> raw = Map<String, Object?>.from(
        fixtureData('bootstrap')! as Map<String, Object?>,
      );
      expect(PosBootstrap.fromJson(PosJson(raw)).bookingRules, isNull);

      raw['booking_rules'] = <String, Object?>{
        'configured': true,
        'table_slot_minutes': 90,
        'room_slot_minutes': null,
        'tapchan_slot_minutes': 240,
        'table_min_advance_minutes': 60,
        'room_min_advance_minutes': 180,
        'tapchan_min_advance_minutes': 0,
        'max_advance_days': 30,
        'deposit_mode': 'per_guest',
        'deposit_amount': '50000.00',
        'deposit_from_guests': 6,
        'deposit_required_to_confirm': true,
      };
      final PosBookingRules rules = PosBootstrap.fromJson(PosJson(raw))
          .bookingRules!;
      expect(rules.slotMinutesFor('table'), 90);
      expect(rules.slotMinutesFor('room'), isNull);
      expect(rules.slotMinutesFor('tapchan'), 240);
      expect(rules.minAdvanceMinutesFor('room'), 180);
      expect(rules.maxAdvanceDays, 30);
      expect(rules.asksDeposit, isTrue);
      expect(rules.depositAmount, const PosMoney.som(50000));
      expect(rules.depositRequiredToConfirm, isTrue);
    });

    test('the new realtime events are typed', () {
      final PosOrderTransferred moved = PosEvent.fromWire(
        'order.transferred',
        <String, Object?>{
          'order': <String, Object?>{
            'uuid': 'o1',
            'status': 'preparing',
            'branch_id': 5,
          },
          'version': 8,
          'table_ref': 'Stol 5',
          'from_waiter_id': 3,
          'to_waiter_id': 4,
        },
      ) as PosOrderTransferred;
      expect(moved.orderUuid, 'o1');
      expect(moved.tableRef, 'Stol 5');
      expect(moved.isFromMe(3), isTrue);
      expect(moved.isToMe(4), isTrue);
      expect(moved.isToMe(3), isFalse);

      final PosWaitlistChanged waitlist = PosEvent.fromWire(
        'waitlist.changed',
        <String, Object?>{
          'entry': <String, Object?>{
            'uuid': 'w1',
            'status': 'seated',
            'branch_id': 5,
            'version': 3,
          },
        },
      ) as PosWaitlistChanged;
      expect((waitlist.entryUuid, waitlist.entryStatus), ('w1', 'seated'));

      final PosTableStateChanged table = PosEvent.fromWire(
        'table.state_changed',
        <String, Object?>{
          'table': <String, Object?>{
            'id': 7,
            'branch_id': 5,
            'service_state': 'cleaning',
          },
        },
      ) as PosTableStateChanged;
      expect((table.tableId, table.serviceState), (7, 'cleaning'));
    });
  });
}
