import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

Map<String, Object?> _event({String status = 'hold', bool full = true}) =>
    <String, Object?>{
      'uuid': 'e1',
      'branch_id': 1,
      'event_date': '2027-03-20',
      'slot': 'evening',
      'slot_label': 'Kechqurun',
      'start_time': '18:00',
      'end_time': '23:00',
      'starts_at': '2027-03-20T13:00:00.000000Z',
      'ends_at': '2027-03-20T18:00:00.000000Z',
      'hall_ids': <int>[5, 6],
      'halls': <Object?>[
        <String, Object?>{'id': 5, 'name': 'Oq zal'},
        <String, Object?>{'id': 6, 'name': "Ko'k zal"},
      ],
      'event_type': 'toy',
      'event_type_label': "To'y",
      'status': status,
      'status_label': 'Vaqtincha band',
      'title': null,
      'customer_name': 'Karimovlar',
      'customer_phone': '+998901234567',
      'guests_guaranteed': 180,
      'guests_minimum': 200,
      'guests_actual': null,
      'guests_billed': 200,
      'package_id': 3,
      'package_name': 'Standart',
      'total': '45300000.00',
      'paid_total': '0.00',
      'outstanding': '45300000.00',
      'hold_expires_at': '2026-11-17T05:00:00.000000Z',
      'frozen': false,
      'contract_no': null,
      'version': 2,
      if (full) ...<String, Object?>{
        'package_total': '30000000.00',
        'hall_total': '10000000.00',
        'extras_total': '5300000.00',
        'orders_total': '0.00',
        'quote': <String, Object?>{
          'currency': 'USD',
          'rate': '12750.0000',
          'total': '3552.94',
        },
        'lines': <Object?>[
          <String, Object?>{
            'uuid': 'l1',
            'kind': 'package',
            'kind_label': 'Paket',
            'name': 'Standart',
            'extra_id': null,
            'hall_id': null,
            'quantity': '1',
            'persons': 200,
            'per_person': true,
            'unit_price': '150000.00',
            'amount': '30000000.00',
            'frozen': false,
            'note': null,
          },
        ],
        'payments': <Object?>[
          <String, Object?>{
            'uuid': 'p1',
            'kind': 'deposit',
            'due_date': '2026-11-20',
            'amount': '3000000.00',
            'status': 'due',
            'tender': null,
            'paid_at': null,
          },
        ],
        'warnings': <Object?>[
          <String, Object?>{
            'code': 'guests_over_threshold',
            'message': "Ko'p mehmon",
          },
        ],
      },
    };

/// P6-51/P6-52: tadbirlar repozitoriysi — yo'llar, tana, `Idempotency-Key` va javobni o'qish.
void main() {
  late Harness h;

  setUp(() async {
    h = Harness();
    await h.signIn();
  });

  Future<RequestOptions> call(
    Object? data,
    Future<Object?> Function() action,
  ) async {
    h.adapter.respond = (RequestOptions o) => FakeResponse(
      200,
      <String, Object?>{'success': true, 'message': '', 'data': data},
    );
    await action();
    return h.last;
  }

  test('calendar reads halls with their events and the slot times', () async {
    late PosBanquetCalendar cal;
    final RequestOptions r = await call(
      <String, Object?>{
        'branch_id': 1,
        'from': '2027-03-01',
        'to': '2027-03-31',
        'timezone': 'Asia/Tashkent',
        'slots': <String, Object?>{
          'morning': <String, Object?>{'start': '06:00', 'end': '11:00'},
          'day': <String, Object?>{'start': '12:00', 'end': '16:00'},
          'evening': <String, Object?>{'start': '18:00', 'end': '23:00'},
        },
        'halls': <Object?>[
          <String, Object?>{
            'id': 5,
            'name': 'Oq zal',
            'is_active': true,
            'events': <Object?>[_event(full: false)],
          },
        ],
      },
      () async => cal = await h.pos.banquets.calendar(
        from: '2027-03-01',
        to: '2027-03-31',
      ),
    );

    expect(r.uri.path, '/api/v1/pos/banquets/calendar');
    expect(r.uri.queryParameters, <String, String>{
      'from': '2027-03-01',
      'to': '2027-03-31',
    });
    expect(cal.slots[PosBanquetSlot.evening], ('18:00', '23:00'));
    expect(cal.halls.single.events.single.status, PosBanquetStatus.hold);
    expect(cal.halls.single.events.single.hallIds, <int>[5, 6]);
    expect(cal.halls.single.events.single.lines, isEmpty);
  });

  test(
    'a hold sends the draft with an Idempotency-Key and reads the card',
    () async {
      late PosBanquetEvent e;
      final RequestOptions r = await call(
        _event(),
        () async => e = await h.pos.banquets.createHold(
          const PosBanquetDraft(
            eventDate: '2027-03-20',
            slot: PosBanquetSlot.evening,
            hallIds: <int>[5, 6],
            customerName: 'Karimovlar',
            guestsGuaranteed: 180,
            packageId: 3,
            extras: <PosBanquetExtraInput>[
              PosBanquetExtraInput.catalog(
                7,
                quantity: PosQuantity.milli(2000),
              ),
              PosBanquetExtraInput.free(
                name: 'Karnay',
                unitPrice: PosMoney.som(1500000),
              ),
            ],
          ),
        ),
      );

      expect(r.method, 'POST');
      expect(r.uri.path, '/api/v1/pos/banquets');
      expect(r.headers['Idempotency-Key'], matches(uuidV4));
      expect(Harness.bodyOf(r), <String, Object?>{
        'event_date': '2027-03-20',
        'slot': 'evening',
        'hall_ids': <Object?>[5, 6],
        'customer_name': 'Karimovlar',
        'guests_guaranteed': 180,
        'banquet_package_id': 3,
        'extras': <Object?>[
          <String, Object?>{'extra_id': 7, 'quantity': '2.000'},
          <String, Object?>{
            'name': 'Karnay',
            'unit_price': '1500000.00',
            'per_person': false,
          },
        ],
      });
      expect(e.total, const PosMoney.som(45300000));
      expect(e.guestsBilled, 200);
      expect(e.lines.single.perPerson, isTrue);
      expect(e.payments.single.kind, 'deposit');
      expect(e.warnings.single.code, 'guests_over_threshold');
      expect(e.quoteCurrency, 'USD');
      expect(e.status.isEditable, isTrue);
    },
  );

  test('update, freeze and unfreeze carry the version', () async {
    RequestOptions r = await call(
      _event(),
      () => h.pos.banquets.update(
        'e1',
        const PosBanquetDraft(
          eventDate: '2027-04-11',
          slot: PosBanquetSlot.day,
        ),
        version: 2,
      ),
    );
    expect(r.method, 'PATCH');
    expect(r.uri.path, '/api/v1/pos/banquets/e1');
    expect(Harness.bodyOf(r), <String, Object?>{
      'event_date': '2027-04-11',
      'slot': 'day',
      'version': 2,
    });

    r = await call(_event(), () => h.pos.banquets.freeze('e1', version: 3));
    expect(r.uri.path, '/api/v1/pos/banquets/e1/freeze');
    expect(Harness.bodyOf(r), <String, Object?>{'version': 3});

    r = await call(
      _event(),
      () => h.pos.banquets.unfreeze('e1', version: 4, reason: 'Qayta tuzildi'),
    );
    expect(r.uri.path, '/api/v1/pos/banquets/e1/unfreeze');
    expect(Harness.bodyOf(r), <String, Object?>{
      'version': 4,
      'reason': 'Qayta tuzildi',
    });
  });

  test('catalog reads packages, extras and hall rent per slot', () async {
    late PosBanquetCatalog c;
    await call(<String, Object?>{
      'packages': <Object?>[
        <String, Object?>{
          'id': 3,
          'name': 'Standart',
          'per_person_price': '150000.00',
          'minimum_guests': 200,
          'included': <String>['Osh'],
        },
      ],
      'extras': <Object?>[
        <String, Object?>{
          'id': 7,
          'name': 'Salfetka',
          'price': '5000.00',
          'pricing': 'per_person',
        },
      ],
      'halls': <Object?>[
        <String, Object?>{
          'hall_id': 5,
          'hall_name': 'Oq zal',
          'branch_id': 1,
          'is_active': true,
          'rates': <String, Object?>{
            'morning': null,
            'day': '3000000.00',
            'evening': '5000000.00',
            'custom': null,
          },
        },
      ],
      'settings': <String, Object?>{
        'slots': <String, Object?>{
          'evening': <String, Object?>{'start': '17:00', 'end': '23:30'},
        },
        'hold_hours': 48,
      },
      'event_types': <Object?>[],
    }, () async => c = await h.pos.banquets.catalog());

    expect(c.packages.single.minimumGuests, 200);
    expect(c.extras.single.perPerson, isTrue);
    expect(c.hallRates[5], <PosBanquetSlot, PosMoney>{
      PosBanquetSlot.day: const PosMoney.som(3000000),
      PosBanquetSlot.evening: const PosMoney.som(5000000),
    });
    expect(c.slots[PosBanquetSlot.evening], ('17:00', '23:30'));
    expect(c.holdHours, 48);
  });
}
