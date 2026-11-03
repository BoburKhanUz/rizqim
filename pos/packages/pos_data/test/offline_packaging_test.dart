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

  // Owner decision 03.11.2026: a drink inside a combo counts for the item rules as its own dish. The fixed cases of
  // the backend's tests/Feature/PosFastFood/ComboPackagingTest.php (`the fixed cases count the drinks inside combos`):
  // a bag per order (1 500), a cup per drink (1 000, the drinks' category) and a box per combo (2 000, the combo product).
  group('combo parts (the backend fixed cases)', () {
    const int drinks = 50;
    const int combo = 20;
    const int lavash = 23;
    const int pepsi = 21;
    const int fanta = 22;
    const int ayran = 25;
    const int sauce = 26;

    Map<String, Object?> rule(
      int id,
      String name,
      String basis,
      String price, {
      int? category,
      int? product,
    }) => <String, Object?>{
      'id': id,
      'branch_id': 3,
      'name': name,
      'basis': basis,
      'basis_label': basis,
      'category_id': category,
      'product_id': product,
      'quantity': 1,
      'unit_price': price,
      'mxik': null,
      'package_code': null,
      'unit': 'dona',
      'order_types': <Object?>['takeaway', 'pickup', 'delivery'],
      'is_active': true,
      'sort_order': id,
    };

    final List<PosPackagingRule> rules = PosTakeaway.of(<String, Object?>{
      'takeaway': <String, Object?>{
        'packaging': <Object?>[
          rule(1, 'Paket', 'order', '1500.00'),
          rule(2, 'Stakan', 'item', '1000.00', category: drinks),
          rule(3, 'Kombo qutisi', 'item', '2000.00', product: combo),
        ],
      },
    }).packaging;

    OfflineDish part(
      int product,
      int? category,
      int pieces, {
      bool measured = false,
    }) => OfflineDish(
      productId: product,
      categoryId: category,
      quantity: PosQuantity.whole(pieces),
      measured: measured,
    );

    List<List<Object?>> view(List<PosPackagingLine> lines) => <List<Object?>>[
      for (final PosPackagingLine l in lines)
        <Object?>[l.name, l.quantity, l.amount.toApiString()],
    ];

    test('A: two combos and a pepsi of its own — 3 cups, 2 boxes, a bag', () {
      final List<PosPackagingLine> lines = OfflinePackaging.lines(
        rules,
        'takeaway',
        <OfflineDish>[
          OfflineDish(
            productId: combo,
            quantity: PosQuantity.parse('2'),
            parts: <OfflineDish>[
              part(lavash, null, 1),
              part(pepsi, drinks, 1),
              part(sauce, null, 1),
            ],
          ),
          OfflineDish(
            productId: pepsi,
            categoryId: drinks,
            quantity: PosQuantity.parse('1'),
          ),
        ],
      );
      expect(view(lines), <List<Object?>>[
        <Object?>['Paket', 1, '1500.00'],
        <Object?>['Stakan', 3, '3000.00'],
        <Object?>['Kombo qutisi', 2, '4000.00'],
      ]);
      expect(OfflinePackaging.total(lines).toApiString(), '8500.00');
    });

    test('B: three combos of two ayran and a weighed drink part, the cup taken off', () {
      final List<PosPackagingLine> lines = OfflinePackaging.lines(
        rules,
        'takeaway',
        <OfflineDish>[
          OfflineDish(
            productId: combo,
            quantity: PosQuantity.parse('3'),
            parts: <OfflineDish>[
              part(ayran, drinks, 2),
              part(fanta, drinks, 1, measured: true),
            ],
          ),
        ],
        removed: <int, String>{2: "Mijoz o'z stakanini olib keldi"},
      );
      expect(view(lines), <List<Object?>>[
        <Object?>['Paket', 1, '1500.00'],
        <Object?>['Stakan', 7, '0.00'],
        <Object?>['Kombo qutisi', 3, '6000.00'],
      ]);
      expect(lines[1].removed, isTrue);
    });

    test(
      'C: a combo in the drinks category counts for it and so does its drink',
      () {
        final List<PosPackagingLine> lines = OfflinePackaging.lines(
          rules,
          'takeaway',
          <OfflineDish>[
            OfflineDish(
              productId: combo,
              categoryId: drinks,
              quantity: PosQuantity.parse('1'),
              parts: <OfflineDish>[part(pepsi, drinks, 1)],
            ),
          ],
        );
        expect(view(lines), <List<Object?>>[
          <Object?>['Paket', 1, '1500.00'],
          <Object?>['Stakan', 2, '2000.00'],
          <Object?>['Kombo qutisi', 1, '2000.00'],
        ]);
      },
    );

    test('the parts are read from the line\'s combo_parts snapshot', () {
      final List<OfflineDish> parts = OfflinePackaging.comboParts(<Object?>[
        <String, Object?>{'product_id': lavash, 'quantity': 1, 'unit': 'piece'},
        <String, Object?>{
          'product_id': pepsi,
          'category_id': drinks,
          'quantity': 2,
          'unit': 'piece',
        },
        <String, Object?>{'product_id': fanta, 'quantity': 1, 'unit': 'l'},
      ], categoryOf: (int id) => id == fanta ? drinks : null);
      expect(parts.map((OfflineDish d) => d.categoryId), <int?>[
        null,
        drinks,
        drinks,
      ]);
      expect(parts.map((OfflineDish d) => d.quantity.wholePart), <int>[
        1,
        2,
        1,
      ]);
      expect(parts.map((OfflineDish d) => d.measured), <bool>[
        false,
        false,
        true,
      ]);
      expect(OfflinePackaging.comboParts(null), isEmpty);
    });

    test(
      'an order opened offline counts the drinks of its combo lines',
      () async {
        final Map<String, Object?> boot = <String, Object?>{
          ...bootstrap(),
          'takeaway': <String, Object?>{
            ...(bootstrap()['takeaway']! as Map<String, Object?>),
            'packaging': <Object?>[
              rule(1, 'Paket', 'order', '1500.00'),
              rule(2, 'Stakan', 'item', '1000.00', category: drinks),
              rule(3, 'Kombo qutisi', 'item', '2000.00', product: combo),
            ],
          },
        };
        final Map<String, Object?> order = await open(
          boot,
          <Map<String, Object?>>[
            <String, Object?>{
              ...line(
                'l1',
                'Lavash kombo',
                '2.000',
                '45000.00',
                30,
                product: combo,
              ),
              'combo_parts': <Object?>[
                <String, Object?>{
                  'product_id': lavash,
                  'category_id': 31,
                  'quantity': 1,
                  'unit': 'piece',
                },
                <String, Object?>{
                  'product_id': pepsi,
                  'category_id': drinks,
                  'quantity': 1,
                  'unit': 'piece',
                },
              ],
            },
            line('l2', 'Pepsi', '1.000', '8000.00', drinks, product: pepsi),
          ],
        );
        expect(order['packaging_amount'], '8500.00');
        expect(
          (charges(order)['packaging_lines']! as List).map(
            (Object? l) => (l! as Map)['quantity'],
          ),
          <int>[1, 3, 2],
        );
      },
    );
  });
}
