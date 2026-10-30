import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:test/test.dart';

/// P6-22 (C1) offline pricing parity: the till prices an offline order with the branch's service charge rules exactly as
/// the backend's PosChargesCalculator does (order type, large group, cover, stolovaya lock), so its bills stop coming
/// back with `amount_mismatch`. The fixed cases and the bootstrap rows are the backend's
/// tests/Feature/PosSync/OfflineServiceChargeRulesTest.php (100 000 of food, the branch row 10% on the hall channel).
void main() {
  // The bootstrap `offline` section as the backend sends it for that branch
  Map<String, Object?> offline({
    bool locked = false,
    Object? rules = const <String, Object?>{
      'by_order_type': <String, Object?>{
        'hall': '15.00',
        'table_service': '12.50',
        'takeaway': '0.00',
      },
      'large_group_min_guests': 8,
      'large_group_percent': '18.00',
      'cover_amount': '5000.00',
    },
    String hallPercent = '10.00',
  }) => <String, Object?>{
    'device_prefix': 'K1',
    'next_local_number': 1,
    'business_date': '2026-10-30',
    'day_type': 'weekday',
    'service_charge_locked': locked,
    'fees': <Object?>[
      for (final String channel in <String>['pos_hall', 'pos_takeaway'])
        <String, Object?>{
          'hall_id': null,
          'channel': channel,
          'service_charge_percent': channel == 'pos_hall'
              ? hallPercent
              : '0.00',
          'place_fee_amount': '0.00',
          'place_fee_mode': 'per_person',
          'rules': rules,
          'service_charge_notice': 'Xizmat haqi: zalda 15%.',
        },
    ],
    'places': <Object?>[],
    'guest_items': <Object?>[],
  };

  late SyncStore store;

  setUp(() async {
    store = await SyncStore.open(MemorySyncStorage());
  });

  /// An order opened offline with one 100 000 line; its local view's charges.
  Future<Map<String, Object?>> price(
    Map<String, Object?> section, {
    String channel = 'pos_hall',
    int? guests,
    String? tableLabel,
  }) async {
    final PosOfflineSettings settings = PosOfflineSettings.fromJson(
      PosJson(section),
    );
    final OfflineOrderBook book = OfflineOrderBook(
      store: store,
      settings: () => settings,
    );
    await store.enqueue(SyncOpType.orderOpen, <String, Object?>{
      'order': 'local-1',
      'channel': channel,
      'guests_count': guests,
      'table_label': tableLabel,
      'items': <Object?>[
        <String, Object?>{
          'line_id': 'l1',
          'name': 'Osh',
          'quantity': '1.000',
          'unit': 'piece',
          'unit_price': '100000.00',
        },
      ],
    }, staffId: 7);
    return book.order('local-1')!;
  }

  List<Object?> row(Map<String, Object?> order) {
    final Map<String, Object?> c = (order['charges']! as Map)
        .cast<String, Object?>();
    return <Object?>[
      c['service_charge_percent'],
      c['service_charge_amount'],
      c['place_fee_amount'],
      c['total'],
      c['service_charge_rule'],
      c['place_fee_kind'],
    ];
  }

  test('the backend fixed cases: by order type, large group, cover', () async {
    expect(row(await price(offline(), guests: 4)), <Object?>[
      '15.00',
      '15000.00',
      '20000.00',
      '135000.00',
      'order_type',
      'cover',
    ]);
  });

  test('8 guests or more: the large-group percent', () async {
    expect(row(await price(offline(), guests: 8)), <Object?>[
      '18.00',
      '18000.00',
      '40000.00',
      '158000.00',
      'large_group',
      'cover',
    ]);
  });

  test('a counter order with a table plate is table service', () async {
    final Map<String, Object?> order = await price(
      offline(),
      channel: 'pos_takeaway',
      guests: 2,
      tableLabel: 'A7',
    );
    expect(row(order), <Object?>[
      '12.50',
      '12500.00',
      '10000.00',
      '122500.00',
      'order_type',
      'cover',
    ]);
    expect(
      (order['charges']! as Map)['service_charge_notice'],
      'Xizmat haqi: zalda 15%.',
    );
  });

  test('takeaway: no service charge, no cover, no large-group rule', () async {
    expect(
      row(await price(offline(), channel: 'pos_takeaway', guests: 10)),
      <Object?>['0.00', '0.00', '0.00', '100000.00', 'none', 'place_fee'],
    );
  });

  test(
    'without rules (module off) the row percent applies as before',
    () async {
      expect(row(await price(offline(rules: null), guests: 3)), <Object?>[
        '10.00',
        '10000.00',
        '0.00',
        '110000.00',
        'base',
        'place_fee',
      ]);
    },
  );

  test(
    'stolovaya: every percent 0 whatever the rules say, the cover stays',
    () async {
      final Map<String, Object?> rules = <String, Object?>{
        'by_order_type': <String, Object?>{'hall': '20.00'},
        'large_group_min_guests': null,
        'large_group_percent': null,
        'cover_amount': '3000.00',
      };
      expect(
        row(
          await price(
            offline(locked: true, rules: rules, hallPercent: '0.00'),
            guests: 2,
          ),
        ),
        <Object?>['0.00', '0.00', '6000.00', '106000.00', 'none', 'cover'],
      );
    },
  );

  test('a rules object PHP sent with an empty map as [] still reads', () {
    final PosOfflineSettings s = PosOfflineSettings.fromJson(
      PosJson(
        offline(
          rules: <String, Object?>{
            'by_order_type': <Object?>[],
            'large_group_min_guests': 6,
            'large_group_percent': '20.00',
            'cover_amount': null,
          },
        ),
      ),
    );
    final PosServiceChargeRules r = s.fees.first.rules!;
    expect(r.byOrderType, isEmpty);
    expect(r.largeGroupMinGuests, 6);
    expect(r.coverAmount, isNull);
    final OfflineServiceCharge small = OfflinePricing.serviceCharge(
      s.fees.first,
      orderType: 'hall',
      guests: 5,
    );
    expect(<String>[small.percent, small.rule], <String>['10.00', 'base']);
    final OfflineServiceCharge big = OfflinePricing.serviceCharge(
      s.fees.first,
      orderType: 'hall',
      guests: 6,
    );
    expect(<String>[big.percent, big.rule], <String>['20.00', 'large_group']);
  });

  test('a by-type percent of 0 takes none (half-up rounding to the so\'m)', () {
    expect(OfflinePricing.orderType('pos_hall', 'A1'), 'hall');
    expect(OfflinePricing.orderType('pos_takeaway', ' '), 'takeaway');
    expect(OfflinePricing.orderType('pos_takeaway', '12'), 'table_service');
    expect(
      OfflinePricing.percentSom(PosMoney.parse('33333.00'), '12.50'),
      PosMoney.parse('4167.00'),
    );
  });
}
