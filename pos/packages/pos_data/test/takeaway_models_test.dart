import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:test/test.dart';

/// P6-34: the bootstrap's `takeaway` section, the order's packaging and pickup time, and the 24/7 business day the
/// till rolls its queue blocks over with (05:00 Tashkent = 00:00 UTC).
void main() {
  final Map<String, Object?> section = <String, Object?>{
    'packaging': <Object?>[
      <String, Object?>{
        'id': 1,
        'name': 'Paket',
        'basis': 'order',
        'quantity': 1,
        'unit_price': '1000.00',
        'unit': 'dona',
        'order_types': <Object?>['takeaway'],
        'is_active': true,
        'sort_order': 1,
      },
    ],
    'pickup': <String, Object?>{
      'min_lead_minutes': 30,
      'slot_minutes': 10,
      'max_days_ahead': 0,
      'prep_minutes': 12,
    },
    'business_day': <String, Object?>{
      'business_date': '2026-10-22',
      'starts_at': '05:00',
      'started_at': '2026-10-22T00:00:00.000000Z',
      'next_starts_at': '2026-10-23T00:00:00.000000Z',
      'round_the_clock': true,
    },
  };

  test('the section reads rules, pickup rules and the business day', () {
    final PosTakeaway t = PosTakeaway.of(<String, Object?>{
      'takeaway': section,
    });
    expect(t.packaging.single.name, 'Paket');
    expect(t.packaging.single.perOrder, isTrue);
    expect(t.packaging.single.unitPrice.toApiString(), '1000.00');
    expect(t.pickup!.minLeadMinutes, 30);
    expect(t.pickup!.prepMinutes, 12);
    expect(
      t.pickup!.earliest(DateTime.utc(2026, 10, 22, 13)),
      DateTime.utc(2026, 10, 22, 13, 30),
    );
    expect(t.businessDay!.roundTheClock, isTrue);

    final PosTakeaway none = PosTakeaway.of(const <String, Object?>{});
    expect(none.packaging, isEmpty);
    expect(none.pickup, isNull);
    expect(none.businessDay, isNull);
  });

  test('23:59, 00:00 and 04:59 are the same business day, 05:00 the next', () {
    final PosBusinessDay day = PosTakeaway.of(<String, Object?>{
      'takeaway': section,
    }).businessDay!;
    // Tashkent is UTC+5
    expect(day.dateAt(DateTime.utc(2026, 10, 22, 18, 59)), '2026-10-22');
    expect(day.dateAt(DateTime.utc(2026, 10, 22, 19)), '2026-10-22');
    expect(day.dateAt(DateTime.utc(2026, 10, 22, 23, 59)), '2026-10-22');
    expect(day.isOverAt(DateTime.utc(2026, 10, 22, 23, 59)), isFalse);
    expect(day.isOverAt(DateTime.utc(2026, 10, 23)), isTrue);
    expect(day.dateAt(DateTime.utc(2026, 10, 23)), '2026-10-23');
    expect(day.dateAt(DateTime.utc(2026, 10, 24, 1)), '2026-10-24');
  });

  test('the order and the bill show their packaging and the pickup time', () {
    final PosOrder order = PosOrder.fromJson(
      const PosJson(<String, Object?>{
        'uuid': 'o-1',
        'status': 'accepted',
        'channel': 'pos_takeaway',
        'type': 'pickup',
        'version': 3,
        'items': <Object?>[],
        'packaging_amount': '7000.00',
        'pickup': <String, Object?>{
          'pickup_at': '2026-10-22T14:00:00.000000Z',
          'kitchen_fire_at': '2026-10-22T13:45:00.000000Z',
          'kitchen_fired_at': null,
          'waiting': true,
          'local_time': '22.10 19:00',
        },
        'charges': <String, Object?>{
          'packaging_amount': '7000.00',
          'packaging_lines': <Object?>[
            <String, Object?>{
              'rule_id': 1,
              'name': 'Paket',
              'basis': 'order',
              'quantity': 1,
              'unit': 'dona',
              'unit_price': '1000.00',
              'amount': '0.00',
              'mxik': null,
              'package_code': null,
              'removed': true,
              'removed_reason': "Mijoz o'z paketini olib keldi",
            },
          ],
        },
      }),
    );
    expect(order.packagingAmount, PosMoney.parse('7000.00'));
    expect(order.packagingLines.single.removed, isTrue);
    expect(order.packagingLines.single.removedReason, contains('paketini'));
    expect(order.pickup!.waiting, isTrue);
    expect(order.pickup!.kitchenFireAt, DateTime.utc(2026, 10, 22, 13, 45));
    expect(order.pickup!.localTime, '22.10 19:00');

    final PosOrder plain = PosOrder.fromJson(
      const PosJson(<String, Object?>{
        'uuid': 'o-2',
        'status': 'accepted',
        'channel': 'pos_hall',
        'version': 1,
        'items': <Object?>[],
      }),
    );
    expect(plain.pickup, isNull);
    expect(plain.packagingLines, isEmpty);
    expect(plain.packagingAmount, PosMoney.zero);
  });
}
