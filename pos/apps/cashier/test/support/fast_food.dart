import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:pos_hardware/testing.dart';

import 'p22.dart';

/// Phase 6 wave 3 (fast food) for the till tests: a lavash/burger menu with a combo (slots "Asosiy", "Ichimlik", "Sous"),
/// a coffee with a size group (0,3 / 0,5 / 0,7) and an inline group ("Muzsiz"), label settings and a label (TSPL)
/// printer, packaging rules (a bag per takeaway order), pickup rules and the business day — and a thin layer over the
/// fake backend that does what the server does for them (combo parts and deltas, packaging lines, pickup time).
const int fastFoodCategory = 7;
const int drinksCategory = 8;
const int lavashId = 41;
const int lavashBigId = 42;
const int pepsiId = 43;
const int colaId = 44;
const int fantaId = 45;
const int friId = 46;
const int sauceId = 47;
const int coffeeId = 48;
const int comboId = 49;

/// The label printer (`purpose: label`, TSPL): `server-9`.
const int labelPrinterId = 9;

/// The bag rule (one per takeaway order, 1 000 so'm).
const int bagRuleId = 501;

String fastVariantUuid(int id) => FakePosState.variantUuid(id);

String optionUuid(int id) =>
    '00000000-0000-4000-8000-0000000003${id.toString().padLeft(2, '0')}';

String sizeUuid(int id) =>
    '00000000-0000-4000-8000-0000000004${id.toString().padLeft(2, '0')}';

Map<String, Object?> _product(
  int id,
  int categoryId,
  String name,
  String price, {
  List<Object?> modifierGroups = const <Object?>[],
  Map<String, Object?>? combo,
  bool available = true,
  int? stationId,
}) => <String, Object?>{
  'id': id,
  'uuid': 'product-$id',
  'store_id': FakePosState.storeId,
  'category_id': categoryId,
  'name': name,
  'description': null,
  'kind': 'dish',
  'sort_order': id,
  'batch_tracked': false,
  'is_available': available,
  'unavailable_reason': null,
  'station_id': stationId,
  'image_url': null,
  'variants': <Object?>[
    <String, Object?>{
      'id': id,
      'uuid': FakePosState.variantUuid(id),
      'product_id': id,
      'name': 'Porsiya',
      'price': price,
      'base_price': price,
      'stock': 1000,
      'unit': 'piece',
      'quantity_step': null,
      'min_quantity': null,
      'batch_consumption': '1.000',
      'unit_label': null,
      'is_available': available,
      'unavailable_reason': null,
    },
  ],
  'modifier_groups': modifierGroups,
  'combo': combo,
};

Map<String, Object?> _option(
  int id,
  int productId,
  String name, {
  String delta = '0.00',
  bool isDefault = false,
  bool available = true,
  String? variantName,
  String receipt = '',
}) => <String, Object?>{
  'id': id,
  'uuid': optionUuid(id),
  'variant_id': productId,
  'variant_uuid': FakePosState.variantUuid(productId),
  'product_id': productId,
  'name': name,
  'variant_name': variantName,
  'quantity': 1,
  'price_delta': delta,
  'modifiers': const <Object?>[],
  'is_default': isDefault,
  'label': null,
  'receipt_label': receipt,
  'is_available': available,
};

Map<String, Object?> _modifier(
  int id,
  String name, {
  String delta = '0.00',
  bool isDefault = false,
}) => <String, Object?>{
  'id': id,
  'uuid': sizeUuid(id),
  'name': name,
  'price_delta': delta,
  'max_quantity': 1,
  'is_available': true,
  'is_default': isDefault,
  'unavailable_reason': null,
};

/// The menu: lavash, lavash katta, Pepsi/Kola/Fanta 0,4 (Fanta sold out), fri, sous, coffee with sizes and the
/// "Lavash kombo" (39 000: lavash or lavash katta +5 000; Pepsi, Kola +1 000 or Fanta; an optional sauce).
void seedFastFoodMenu(FakePosState s) {
  s.categories
    ..add(<String, Object?>{
      'id': fastFoodCategory,
      'name': 'Fast food',
      'parent_id': null,
      'sort_order': 0,
      'image_url': null,
    })
    ..add(<String, Object?>{
      'id': drinksCategory,
      'name': 'Ichimliklar',
      'parent_id': null,
      'sort_order': 1,
      'image_url': null,
    });
  s.products
    ..add(_product(lavashId, fastFoodCategory, 'Lavash', '32000.00'))
    ..add(_product(lavashBigId, fastFoodCategory, 'Lavash katta', '38000.00'))
    ..add(_product(pepsiId, drinksCategory, 'Pepsi 0,4', '9000.00'))
    ..add(_product(colaId, drinksCategory, 'Kola 0,4', '9000.00'))
    ..add(
      _product(
        fantaId,
        drinksCategory,
        'Fanta 0,4',
        '9000.00',
        available: false,
      ),
    )
    ..add(_product(friId, fastFoodCategory, 'Fri', '14000.00'))
    ..add(_product(sauceId, fastFoodCategory, 'Sous', '3000.00'))
    ..add(
      _product(
        coffeeId,
        drinksCategory,
        'Kofe',
        '15000.00',
        modifierGroups: <Object?>[
          <String, Object?>{
            'id': 60,
            'uuid': 'mg-60',
            'name': 'Hajmi',
            'min_select': 1,
            'max_select': 1,
            'is_required': true,
            'sort_order': 0,
            'is_active': true,
            'display_mode': 'size',
            'modifiers': <Object?>[
              _modifier(61, '0,3'),
              _modifier(62, '0,5', delta: '4000.00', isDefault: true),
              _modifier(63, '0,7', delta: '7000.00'),
            ],
          },
          <String, Object?>{
            'id': 64,
            'uuid': 'mg-64',
            'name': 'Qo\'shimcha',
            'min_select': 0,
            'max_select': 2,
            'is_required': false,
            'sort_order': 1,
            'is_active': true,
            'display_mode': 'inline',
            'modifiers': <Object?>[
              _modifier(65, 'Muzsiz'),
              _modifier(66, 'Sirop', delta: '3000.00'),
            ],
          },
        ],
      ),
    )
    ..add(
      _product(
        comboId,
        fastFoodCategory,
        'Lavash kombo',
        '39000.00',
        combo: <String, Object?>{
          'id': 1,
          'uuid': 'combo-1',
          'slots': <Object?>[
            <String, Object?>{
              'id': 1,
              'uuid': 'slot-1',
              'name': 'Asosiy',
              'min_picks': 1,
              'max_picks': 1,
              'allow_modifiers': false,
              'options': <Object?>[
                _option(1, lavashId, 'Lavash', isDefault: true),
                _option(2, lavashBigId, 'Lavash katta', delta: '5000.00'),
              ],
            },
            <String, Object?>{
              'id': 2,
              'uuid': 'slot-2',
              'name': 'Ichimlik',
              'min_picks': 1,
              'max_picks': 1,
              'allow_modifiers': false,
              'options': <Object?>[
                _option(3, pepsiId, 'Pepsi 0,4', isDefault: true),
                _option(4, colaId, 'Kola 0,4', delta: '1000.00'),
                _option(5, fantaId, 'Fanta 0,4', available: false),
              ],
            },
            <String, Object?>{
              'id': 3,
              'uuid': 'slot-3',
              'name': 'Sous',
              'min_picks': 0,
              'max_picks': 1,
              'allow_modifiers': false,
              'options': <Object?>[_option(6, sauceId, 'Sous')],
            },
          ],
        },
      ),
    );
}

/// Fast food store blocks of the bootstrap and the server's fast food rules on top of the fake backend.
class FastFoodBackend {
  FastFoodBackend(
    this.p, {
    this.labels = true,
    this.labelPrinter = true,
    this.packaging = true,
    this.pickup = true,
    DateTime? nextDayAt,
  }) {
    seedFastFoodMenu(p.state);
    if (labelPrinter) {
      p.state.printers.add(<String, Object?>{
        'id': labelPrinterId,
        'branch_id': FakePosState.branchId,
        'name': 'Stiker printeri',
        'purpose': 'label',
        'connection': 'lan',
        'address': '192.168.1.60',
        'port': 9100,
        'paper_width': 58,
        'station_id': null,
        'device_id': null,
        'is_active': true,
        'encoding': null,
        'print_as_image': false,
      });
    }
    final DateTime now = p.state.now();
    p.state.bootstrapExtras
      ..['labels'] = labels
          ? <String, Object?>{
              'configured': true,
              'size': '40x30',
              'scope': 'categories',
              'category_ids': <int>[drinksCategory],
              'per_unit': true,
              'bag_label': true,
            }
          : null
      ..['takeaway'] = <String, Object?>{
        'packaging': packaging ? <Object?>[_bagRule] : const <Object?>[],
        'pickup': pickup
            ? <String, Object?>{
                'min_lead_minutes': 20,
                'slot_minutes': 15,
                'max_days_ahead': 0,
                'prep_minutes': 15,
              }
            : null,
        'business_day': <String, Object?>{
          'business_date': '2026-10-22',
          'starts_at': '05:00',
          'started_at': '2026-10-22T00:00:00Z',
          'next_starts_at': (nextDayAt ?? now.add(const Duration(hours: 10)))
              .toUtc()
              .toIso8601String(),
          'round_the_clock': true,
        },
      };
    _next = p.h.adapter.respond;
    p.h.adapter.respond = _respond;
    final FakeResponse? Function(FakeRequest r)? inner = p.state.extraRoutes;
    p.state.extraRoutes = (FakeRequest r) => _route(r) ?? inner?.call(r);
  }

  final P22 p;
  final bool labels;
  final bool labelPrinter;
  final bool packaging;
  final bool pickup;

  /// What the till sent: `POST /pos/orders` bodies, packaging removals (rule, reason), pickup-time changes.
  final List<Map<String, Object?>> opened = <Map<String, Object?>>[];
  final List<(int, String)> removed = <(int, String)>[];
  final List<int> restored = <int>[];
  final List<String?> pickupChanges = <String?>[];

  FakeResponse? Function(RequestOptions options)? _next;

  /// Order uuid → its packaging taken off (rule → reason).
  final Map<String, Map<int, String>> _removed = <String, Map<int, String>>{};

  FakePosState get state => p.state;

  static const Map<String, Object?> _bagRule = <String, Object?>{
    'id': bagRuleId,
    'name': 'Paket',
    'basis': 'order',
    'category_id': null,
    'product_id': null,
    'quantity': 1,
    'unit_price': '1000.00',
    'mxik': '10202002005000000',
    'package_code': '1500000',
    'unit': 'dona',
    'order_types': <String>['takeaway', 'pickup'],
    'is_active': true,
    'sort_order': 0,
  };

  List<PosPackagingRule> get _rules => packaging
      ? <PosPackagingRule>[PosPackagingRule.fromJson(PosJson.of(_bagRule))]
      : const <PosPackagingRule>[];

  /// The pickup times the till offers.
  List<Map<String, Object?>> get slots => <Map<String, Object?>>[
    for (final (String time, int minutes) in <(String, int)>[
      ('13:30', 30),
      ('13:45', 45),
      ('14:00', 60),
    ])
      <String, Object?>{
        'at': DateTime.utc(2026, 10, 22, 8, minutes).toIso8601String(),
        'time': time,
        'business_date': '2026-10-22',
      },
  ];

  /// Stickers and bag labels the label printer got (as the emulator draws them).
  List<String> get printedLabels => <String>[
    for (final List<int> bytes
        in p.h.printerFor('server-$labelPrinterId').received)
      TsplDocument.parse(bytes).render(),
  ];

  FakeResponse? _respond(RequestOptions o) {
    final FakeResponse? earlier = _next?.call(o);
    final String path = o.uri.path.replaceFirst('/api', '');
    if (o.method == 'POST' && path == '/v1/pos/orders') {
      final Map<String, Object?> body = _decode(o.data);
      final FakeResponse r = earlier ?? _handle(o);
      if (r.status < 300) {
        opened.add(body);
        _apply(r, body, fresh: true);
      }
      return r;
    }
    final RegExpMatch? round = RegExp(r'^/v1/pos/orders/([^/]+)/rounds$')
        .firstMatch(path);
    if (o.method == 'POST' && round != null) {
      final Map<String, Object?> body = _decode(o.data);
      final FakeResponse r = earlier ?? _handle(o);
      if (r.status < 300) _apply(r, body, fresh: false);
      return r;
    }
    if (o.method == 'POST' && path.endsWith('/bills')) {
      final FakeResponse r = earlier ?? _handle(o);
      final String uuid = path.split('/')[4];
      _billPackaging(uuid);
      return r;
    }
    return earlier;
  }

  FakeResponse _handle(RequestOptions o) => state.handle(
    o.method,
    o.uri.path,
    o.uri.queryParameters,
    o.data,
    (o.headers['Authorization'] as String?)?.replaceFirst('Bearer ', ''),
    idempotencyKey: o.headers['Idempotency-Key'] as String?,
  );

  /// The server's work for a new order or round: each combo line gets its parts and its delta in the price, the
  /// packaging lines are worked out and the pickup time stored.
  void _apply(
    FakeResponse r,
    Map<String, Object?> body, {
    required bool fresh,
  }) {
    final Object? data = (r.body as Map<Object?, Object?>?)?['data'];
    if (data is! Map) return;
    final Map<String, Object?> order = state.orders['${data['uuid']}']!;
    final List<Map<String, Object?>> lines = <Map<String, Object?>>[
      for (final Object? e in (body['items'] as List<Object?>?) ?? const [])
        (e! as Map<Object?, Object?>).cast<String, Object?>(),
    ];
    final List<Map<String, Object?>> its = (order['items']! as List<Object?>)
        .cast<Map<String, Object?>>();
    final List<Map<String, Object?>> added = its.sublist(
      its.length - lines.length,
    );
    var extra = 0;
    for (var i = 0; i < lines.length; i++) {
      final Object? picks = lines[i]['combo'];
      if (picks is! List) continue;
      final Map<String, Object?> product = state.products.firstWhere(
        (Map<String, Object?> x) => (x['variants']! as List<Object?>).any(
          (Object? v) =>
              (v! as Map<Object?, Object?>)['uuid'] == lines[i]['variant_id'],
        ),
      );
      final PosCombo combo = PosCombo.fromJson(PosJson.of(product['combo']));
      final PosComboResolution res = PosComboPricing.resolve(
        combo,
        <PosComboPick>[
          for (final Object? pick in picks)
            PosComboPick(
              '${(pick! as Map<Object?, Object?>)['option_id']}',
              quantity:
                  ((pick as Map<Object?, Object?>)['quantity'] as num?)
                      ?.toInt() ??
                  1,
            ),
        ],
        comboName: '${product['name']}',
      );
      final Map<String, Object?> item = added[i];
      final PosQuantity qty = PosQuantity.parse(item['quantity']);
      final int add = (res.delta.tiyin * qty.milli / 1000).round();
      extra += add;
      item
        ..['combo_parts'] = <Object?>[
          for (final PosComboPart part in res.parts) part.toJson(),
        ]
        ..['modifiers_total'] =
            (PosMoney.parse(item['modifiers_total']) + res.delta).toApiString()
        ..['total_price'] =
            (PosMoney.parse(item['total_price']) + PosMoney(add)).toApiString();
    }
    if (fresh && body['pickup_at'] != null) {
      order['pickup'] = _pickup('${body['pickup_at']}');
    }
    _money(order, extra);
    _packaging(order);
    (data as Map<Object?, Object?>)
      ..addAll(order)
      ..['items'] = order['items'];
  }

  Map<String, Object?> _pickup(String at) {
    final DateTime t = DateTime.parse(at).toUtc();
    final DateTime local = t.add(const Duration(hours: 5));
    String two(int n) => n.toString().padLeft(2, '0');
    return <String, Object?>{
      'pickup_at': t.toIso8601String(),
      'kitchen_fire_at': t
          .subtract(const Duration(minutes: 15))
          .toIso8601String(),
      'kitchen_fired_at': null,
      'waiting': true,
      'local_time':
          '${two(local.day)}.${two(local.month)} ${two(local.hour)}:${two(local.minute)}',
    };
  }

  void _money(Map<String, Object?> order, int tiyin) {
    if (tiyin == 0) return;
    final Map<String, Object?> ch =
        (order['charges'] as Map<String, Object?>?) ?? <String, Object?>{};
    order
      ..['subtotal'] = (PosMoney.parse(order['subtotal']) + PosMoney(tiyin))
          .toApiString()
      ..['total_price'] =
          (PosMoney.parse(order['total_price']) + PosMoney(tiyin))
              .toApiString();
    ch
      ..['items_subtotal'] =
          (PosMoney.parse(ch['items_subtotal']) + PosMoney(tiyin)).toApiString()
      ..['total'] = (PosMoney.parse(ch['total']) + PosMoney(tiyin))
          .toApiString();
    order['charges'] = ch;
  }

  /// The order's packaging lines by the rules (as PackagingRules::linesFor), removed ones kept at 0.
  void _packaging(Map<String, Object?> order) {
    final Map<String, Object?> ch =
        (order['charges'] as Map<String, Object?>?) ?? <String, Object?>{};
    final PosMoney before = PosMoney.parse(order['packaging_amount']);
    final Map<int, String> off = _removed['${order['uuid']}'] ??=
        <int, String>{};
    final List<PosPackagingLine> lines = OfflinePackaging.lines(
      _rules,
      OfflinePackaging.orderType(
        '${order['channel']}',
        order['table_label'] as String?,
      ),
      <OfflineDish>[
        for (final Map<String, Object?> i
            in (order['items']! as List<Object?>).cast<Map<String, Object?>>())
          if (i['kitchen_status'] != 'void')
            OfflineDish(
              productId: (i['product_variant_id'] as num?)?.toInt(),
              categoryId: _categoryOf(
                (i['product_variant_id'] as num?)?.toInt(),
              ),
              quantity: PosQuantity.parse(i['quantity']),
            ),
      ],
      removed: off,
    );
    final PosMoney amount = OfflinePackaging.total(lines);
    ch['packaging_lines'] = <Object?>[
      for (final PosPackagingLine l in lines) l.toJson(),
    ];
    ch['packaging_amount'] = amount.toApiString();
    order
      ..['charges'] = ch
      ..['packaging_amount'] = amount.toApiString();
    _money(order, (amount - before).tiyin);
  }

  int? _categoryOf(int? variantId) {
    for (final Map<String, Object?> pr in state.products) {
      if (pr['id'] == variantId) return (pr['category_id'] as num?)?.toInt();
    }
    return null;
  }

  /// The open bill carries the order's packaging (one bill on the counter) and each line's combo parts.
  void _billPackaging(String orderUuid) {
    final Map<String, Object?>? order = state.orders[orderUuid];
    if (order == null) return;
    // The fake core worked the charges out again without packaging: put the lines back on the order
    final Map<String, Object?>? ch = order['charges'] as Map<String, Object?>?;
    if (ch == null || ch['packaging_lines'] == null) {
      order['packaging_amount'] = '0.00';
      _packaging(order);
    }
    final List<Map<String, Object?>> its = (order['items']! as List<Object?>)
        .cast<Map<String, Object?>>();
    for (final Map<String, Object?> bill
        in p.backend.bills[orderUuid] ?? const <Map<String, Object?>>[]) {
      if (bill['status'] != 'open') continue;
      for (final Map<String, Object?> bi
          in (bill['items']! as List<Object?>).cast<Map<String, Object?>>()) {
        final Map<String, Object?>? oi = its
            .where((Map<String, Object?> i) => i['id'] == bi['order_item_id'])
            .firstOrNull;
        if (oi?['combo_parts'] != null) bi['combo_parts'] = oi!['combo_parts'];
      }
      if (bill['packaging_amount'] != null) continue;
      final PosMoney pack = PosMoney.parse(order['packaging_amount']);
      final List<Object?> lines = <Object?>[
        for (final Object? l
            in ((order['charges'] as Map<String, Object?>?)?['packaging_lines']
                    as List<Object?>?) ??
                const [])
          if (l is Map && l['removed'] != true)
            <String, Object?>{
              'rule_id': l['rule_id'],
              'name': l['name'],
              'quantity': l['quantity'],
              'unit': l['unit'],
              'amount': l['amount'],
            },
      ];
      bill
        ..['packaging_amount'] = pack.toApiString()
        ..['packaging_lines'] = lines
        ..['total'] = (PosMoney.parse(bill['total']) + pack).toApiString()
        ..['due_amount'] = (PosMoney.parse(bill['due_amount']) + pack)
            .toApiString();
    }
  }

  FakeResponse? _route(FakeRequest r) {
    if (r.method == 'GET' && r.path == '/v1/pos/pickup-slots') {
      return FakeResponse(200, <String, Object?>{
        'success': true,
        'data': <String, Object?>{'slots': slots},
      });
    }
    final RegExpMatch? pack = RegExp(
      r'^/v1/pos/orders/([^/]+)/packaging/(\d+)/(remove|restore)$',
    ).firstMatch(r.path);
    if (r.method == 'POST' && pack != null) {
      final Map<String, Object?> order = state.orders[pack.group(1)]!;
      final int rule = int.parse(pack.group(2)!);
      final bool remove = pack.group(3) == 'remove';
      final Map<int, String> off = _removed[pack.group(1)!] ??= <int, String>{};
      if (remove) {
        off[rule] = '${r.data['reason']}';
      } else {
        off.remove(rule);
      }
      if (remove) {
        removed.add((rule, '${r.data['reason']}'));
      } else {
        restored.add(rule);
      }
      _packaging(order);
      order['version'] = ((order['version'] as num?)?.toInt() ?? 1) + 1;
      return FakeResponse(200, <String, Object?>{
        'success': true,
        'data': order,
      });
    }
    final RegExpMatch? time = RegExp(r'^/v1/pos/orders/([^/]+)/pickup-time$')
        .firstMatch(r.path);
    if (r.method == 'PUT' && time != null) {
      final Map<String, Object?> order = state.orders[time.group(1)]!;
      final Object? at = r.data['pickup_at'];
      pickupChanges.add(at as String?);
      order['pickup'] = at == null ? null : _pickup(at);
      order['version'] = ((order['version'] as num?)?.toInt() ?? 1) + 1;
      return FakeResponse(200, <String, Object?>{
        'success': true,
        'data': order,
      });
    }
    return null;
  }

  static Map<String, Object?> _decode(Object? body) {
    if (body is Map) return body.cast<String, Object?>();
    if (body is String && body.isNotEmpty) {
      return (jsonDecode(body) as Map<Object?, Object?>)
          .cast<String, Object?>();
    }
    return const <String, Object?>{};
  }
}
