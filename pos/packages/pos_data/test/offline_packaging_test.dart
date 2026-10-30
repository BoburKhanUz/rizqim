import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:test/test.dart';

/// P6-34 (B8) offline pricing parity: the till prices an offline takeaway's packaging exactly as the backend's
/// PackagingRules / PosChargesCalculator do. The fixed case and the bootstrap rules are the backend's
/// tests/Feature/PosTakeaway/PosPackagingTest.php (`an offline takeaway is priced as the till priced it`): a bag per
/// takeaway (1 000) and a bowl per soup portion (3 000, the soups' category); 2 soups (25 000) and a shashlik (18 000).
void main() {
  const int soups = 41;
  const int grill = 7;

  // The bootstrap's `takeaway` section as the backend sends it for that branch
  Map<String, Object?> bootstrap({
    List<String> bowlTypes = const <String>['takeaway', 'pickup'],
  }) => <String, Object?>{
    'offline': <String, Object?>{
      'device_prefix': 'K1',
      'next_local_number': 1,
      'business_date': '2026-10-22',
      'day_type': 'weekday',
      'service_charge_locked': false,
      'fees': <Object?>[],
      'places': <Object?>[],
      'guest_items': <Object?>[],
    },
    'takeaway': <String, Object?>{
      'packaging': <Object?>[
        <String, Object?>{
          'id': 1,
          'branch_id': 3,
          'name': 'Paket',
          'basis': 'order',
          'basis_label': 'Buyurtmaga',
          'category_id': null,
          'product_id': null,
          'quantity': 1,
          'unit_price': '1000.00',
          'mxik': null,
          'package_code': null,
          'unit': 'dona',
          'order_types': <Object?>['takeaway', 'pickup', 'delivery'],
          'is_active': true,
          'sort_order': 1,
        },
        <String, Object?>{
          'id': 2,
          'branch_id': 3,
          'name': "Sho'rva idishi",
          'basis': 'item',
          'basis_label': 'Taomga',
          'category_id': soups,
          'product_id': null,
          'quantity': 1,
          'unit_price': '3000.00',
          'mxik': '03924000001000001',
          'package_code': '2500002',
          'unit': 'dona',
          'order_types': bowlTypes,
          'is_active': true,
          'sort_order': 2,
        },
      ],
      'pickup': <String, Object?>{
        'min_lead_minutes': 20,
        'slot_minutes': 15,
        'max_days_ahead': 1,
        'prep_minutes': 15,
      },
      'business_day': <String, Object?>{
        'business_date': '2026-10-22',
        'starts_at': '05:00',
        'started_at': '2026-10-22T00:00:00.000000Z',
        'next_starts_at': '2026-10-23T00:00:00.000000Z',
        'round_the_clock': true,
      },
    },
  };

  late SyncStore store;

  setUp(() async {
    store = await SyncStore.open(MemorySyncStorage());
  });

  Map<String, Object?> line(
    String id,
    String name,
    String quantity,
    String price,
    int category, {
    int product = 100,
    String unit = 'piece',
  }) => <String, Object?>{
    'line_id': id,
    'name': name,
    'quantity': quantity,
    'unit': unit,
    'unit_price': price,
    'product_id': product,
    'category_id': category,
  };

  /// An order opened offline with [items]; its local view.
  Future<Map<String, Object?>> open(
    Map<String, Object?> boot,
    List<Map<String, Object?>> items, {
    String channel = 'pos_takeaway',
    String? tableLabel,
  }) async {
    final PosOfflineSettings settings = PosOfflineSettings.of(boot);
    final OfflineOrderBook book = OfflineOrderBook(
      store: store,
      settings: () => settings,
    );
    await store.enqueue(SyncOpType.orderOpen, <String, Object?>{
      'order': 'local-1',
      'channel': channel,
      'guests_count': channel == 'pos_hall' ? 1 : null,
      'table_label': tableLabel,
      'items': items,
    }, staffId: 7);
    return book.order('local-1')!;
  }

  Map<String, Object?> charges(Map<String, Object?> order) =>
      (order['charges']! as Map).cast<String, Object?>();

  test('the backend fixed case: a bag and two bowls, 75 000 in all', () async {
    final Map<String, Object?> order = await open(
      bootstrap(),
      <Map<String, Object?>>[
        line('l1', "Sho'rva", '2.000', '25000.00', soups, product: 11),
        line('l2', 'Shashlik', '1.000', '18000.00', grill, product: 12),
      ],
    );
    expect(order['packaging_amount'], '7000.00');
    expect(order['total_price'], '75000.00');
    expect(charges(order)['packaging_amount'], '7000.00');
    final List<Object?> lines = charges(order)['packaging_lines']! as List;
    expect(
      lines.map((Object? l) {
        final Map<Object?, Object?> m = l! as Map;
        return <Object?>[m['rule_id'], m['name'], m['quantity'], m['amount']];
      }).toList(),
      <Object?>[
        <Object?>[1, 'Paket', 1, '1000.00'],
        <Object?>[2, "Sho'rva idishi", 2, '6000.00'],
      ],
    );
    expect((lines[1]! as Map)['mxik'], '03924000001000001');
  });

  test('the hall and a counter order carried to the table get none', () async {
    final List<Map<String, Object?>> items = <Map<String, Object?>>[
      line('l1', "Sho'rva", '1.000', '25000.00', soups),
    ];
    expect(
      charges(
        await open(bootstrap(), items, channel: 'pos_hall'),
      )['packaging_amount'],
      '0.00',
    );
    expect(
      charges(
        await open(bootstrap(), items, tableLabel: '12'),
      )['packaging_amount'],
      '0.00',
    );
  });

  test('portions round a piece line up and count a weighed line once', () {
    expect(
      OfflinePackaging.portions(PosQuantity.parse('1.5'), measured: false),
      2,
    );
    expect(
      OfflinePackaging.portions(PosQuantity.parse('2.5'), measured: true),
      1,
    );
    expect(
      OfflinePackaging.portions(PosQuantity.parse('0'), measured: false),
      0,
    );
    final List<PosPackagingLine> lines = OfflinePackaging.lines(
      PosTakeaway.of(bootstrap()).packaging,
      'takeaway',
      <OfflineDish>[
        OfflineDish(categoryId: soups, quantity: PosQuantity.parse('1.5')),
        OfflineDish(
          categoryId: soups,
          quantity: PosQuantity.parse('0.7'),
          measured: true,
        ),
      ],
      removed: <int, String>{1: "Mijoz o'z paketini olib keldi"},
    );
    expect(lines.map((PosPackagingLine l) => l.quantity), <int>[1, 3]);
    expect(lines.first.removed, isTrue);
    expect(lines.first.amount, PosMoney.zero);
    expect(OfflinePackaging.total(lines).toApiString(), '9000.00');
  });

  test('a rule the order type does not list is left out', () {
    final List<PosPackagingLine> lines = OfflinePackaging.lines(
      PosTakeaway.of(bootstrap(bowlTypes: <String>['pickup'])).packaging,
      'takeaway',
      <OfflineDish>[
        OfflineDish(categoryId: soups, quantity: PosQuantity.parse('2')),
      ],
    );
    expect(lines.map((PosPackagingLine l) => l.name), <String>['Paket']);
    expect(OfflinePackaging.orderType('pos_takeaway', null), 'takeaway');
    expect(OfflinePackaging.orderType('pos_takeaway', 'A7'), isNull);
    expect(OfflinePackaging.orderType('pos_hall', null), isNull);
  });

  test('the bill made offline carries the packaging and its lines', () async {
    final PosOfflineSettings settings = PosOfflineSettings.of(bootstrap());
    final OfflineOrderBook book = OfflineOrderBook(
      store: store,
      settings: () => settings,
    );
    await store.enqueue(SyncOpType.orderOpen, <String, Object?>{
      'order': 'local-2',
      'channel': 'pos_takeaway',
      'items': <Object?>[line('l1', "Sho'rva", '1.000', '25000.00', soups)],
    }, staffId: 7);
    await store.enqueue(SyncOpType.billCreate, <String, Object?>{
      'order': 'local-2',
      'bill': 'bill-2',
      'expected_total': '29000.00',
    }, staffId: 7);
    final Map<String, Object?> billing = book.billing('local-2')!;
    final Map<Object?, Object?> bill =
        (billing['bills']! as List).first! as Map;
    expect(bill['total'], '29000.00');
    expect(bill['packaging_amount'], '4000.00');
    expect((bill['packaging_lines']! as List).length, 2);
  });
}
