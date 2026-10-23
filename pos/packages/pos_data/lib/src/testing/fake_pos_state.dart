import 'dart:convert';

/// Soxta javob.
class FakeResponse {
  const FakeResponse(
    this.status,
    this.body, {
    this.headers = const <String, String>{},
  });

  final int status;
  final Object? body;
  final Map<String, String> headers;
}

typedef FakeEventSink = void Function(String event, Map<String, Object?> data);

/// Kirgan xodimning so'rovi ([FakePosState.extraRoutes] uchun): yo'l `/api` siz (`/v1/pos/...`).
class FakeRequest {
  const FakeRequest({
    required this.method,
    required this.path,
    required this.query,
    required this.data,
    required this.member,
    this.idempotencyKey,
  });

  final String method;
  final String path;
  final Map<String, String> query;
  final Map<String, Object?> data;

  /// Kirgan xodim (`id`, `name`, `role`).
  final Map<String, Object?> member;
  final String? idempotencyKey;
}

/// Soxta POS backend holati (Flutter'siz): ulash, xodim PIN'i, bootstrap, menyu, zal, buyurtmalar (ochish, bosqich,
/// yuborish, o'chirish, berildi, mehmonlar, ko'chirish, bekor qilish, xizmat qatori), menejer tasdig'i, smena,
/// real-time imzosi. Javob shakllari haqiqiy backend'dagidek (docs/API.md P3-12 … P3-18; mahalliy backend'dan olingan
/// namunalar bo'yicha). Schyot, bron va nasiya — bo'sh ro'yxatlar (P3-22 o'z testlarida kengaytiradi yoki
/// `FakePosAdapter.respond` bilan javob beradi).
///
/// Widget testlari ([FakePosAdapter] — Dio adapteri) va Linux'da ilovani suratga olish ([FakePosServer]) shu sinfni
/// ishlatadi.
class FakePosState {
  FakePosState({DateTime Function()? clock, this.onEvent})
    : now = clock ?? DateTime.now {
    _seedMenu();
  }

  static const String terminalToken = 'pos-terminal-token';
  static const String deviceUuid = '22222222-2222-4222-8222-222222222222';
  static const int storeId = 1;
  static const int branchId = 1;
  static const String realtimeKey = 'pos-key';
  static const String menuVersion = 'menu00000001';

  DateTime Function() now;
  FakeEventSink? onEvent;

  /// Ulash kodi rad etiladi (`pairing_invalid`).
  bool rejectPairing = false;

  /// Terminal ulangan (token yaroqli).
  bool paired = false;

  /// Ulanganda qurilma turi va nomi.
  String deviceName = 'Kassa 1';

  /// Xodimlar (PIN klaviaturasi va bootstrap uchun).
  List<Map<String, Object?>> staff = <Map<String, Object?>>[
    <String, Object?>{
      'id': 3,
      'name': 'Kamola Karimova',
      'role': 'cashier',
      'staff_code': '1003',
    },
    <String, Object?>{
      'id': 4,
      'name': 'Botir Aliyev',
      'role': 'waiter',
      'staff_code': '1004',
    },
    <String, Object?>{
      'id': 2,
      'name': 'Malika Rahimova',
      'role': 'manager',
      'staff_code': '1002',
    },
  ];

  /// Xodim id → PIN.
  Map<int, String> pins = <int, String>{3: '2468', 4: '1357', 2: '5926'};

  /// Rol → ruxsatlar (backend `RolePermissions` dagidek).
  Map<String, List<String>> rolePermissions = <String, List<String>>{
    'cashier': <String>[
      'orders.view',
      'orders.manage',
      'tables.manage',
      'menu.view',
      'pos.open_shift',
      'pos.close_shift',
      'pos.take_payment',
      'bookings.manage',
    ],
    'waiter': <String>[
      'orders.view',
      'orders.manage',
      'tables.manage',
      'menu.view',
    ],
    'manager': <String>[
      'orders.view',
      'orders.manage',
      'orders.cancel',
      'orders.discount',
      'orders.void_item',
      'tables.manage',
      'menu.view',
      'devices.manage',
      'pos.open_shift',
      'pos.close_shift',
      'pos.cash_in_out',
      'pos.take_payment',
      'pos.reprint_bill',
      'bookings.manage',
      'customer_accounts.manage',
      'pos.refund',
      'reports.view',
    ],
  };

  /// Filial POS sozlamasi: ekran qulfi (daqiqa).
  int screenLockMinutes = 5;

  /// Bootstrap'dagi printerlar.
  List<Map<String, Object?>> printers = <Map<String, Object?>>[
    <String, Object?>{
      'id': 1,
      'branch_id': branchId,
      'name': 'Kassa printeri',
      'purpose': 'receipt',
      'connection': 'lan',
      'address': '192.168.1.50',
      'port': 9100,
      'paper_width': 80,
      'station_id': null,
      'device_id': null,
      'is_active': true,
      'encoding': 'cp866',
      'print_as_image': false,
    },
    <String, Object?>{
      'id': 2,
      'branch_id': branchId,
      'name': 'Osh sexi',
      'purpose': 'kitchen',
      'connection': 'lan',
      'address': '192.168.1.51',
      'port': 9100,
      'paper_width': 58,
      'station_id': 1,
      'device_id': null,
      'is_active': true,
      'encoding': null,
      'print_as_image': true,
    },
  ];

  List<Map<String, Object?>> halls = <Map<String, Object?>>[
    <String, Object?>{'id': 1, 'name': 'Asosiy zal', 'sort_order': 1},
    <String, Object?>{'id': 2, 'name': 'Hovli', 'sort_order': 2},
  ];

  List<Map<String, Object?>> tables = <Map<String, Object?>>[
    for (var i = 1; i <= 4; i++)
      <String, Object?>{
        'id': i,
        'hall_id': 1,
        'name': '$i-xona',
        'kind': 'room',
        'seats': 10,
        'layout': <String, Object?>{'x': null, 'y': null, 'w': null, 'h': null},
        'sort_order': i,
        'min_spend': null,
        'min_spend_mode': 'per_table',
      },
    for (var i = 1; i <= 3; i++)
      <String, Object?>{
        'id': 10 + i,
        'hall_id': 2,
        'name': 'Tapchan $i',
        'kind': 'tapchan',
        'seats': 6,
        'layout': <String, Object?>{'x': null, 'y': null, 'w': null, 'h': null},
        'sort_order': i,
        'min_spend': null,
        'min_spend_mode': 'per_table',
      },
  ];

  /// Menyu (`GET /catalog`): kategoriyalar va mahsulotlar.
  final List<Map<String, Object?>> categories = <Map<String, Object?>>[];
  final List<Map<String, Object?>> products = <Map<String, Object?>>[];
  final Map<String, Object?> availability = <String, Object?>{
    'branch_id': branchId,
    'stopped': <String, Object?>{
      'products': <int>[],
      'variants': <int>[],
      'modifiers': <int>[],
    },
    'sold_out': <String, Object?>{'variants': <int>[]},
    'stop_list': <Object?>[],
    'batches': <Object?>[
      <String, Object?>{
        'id': 1,
        'uuid': 'batch-1',
        'product_id': 3,
        'product_variant_id': null,
        'label': null,
        'quantity_produced': 10,
        'quantity_left': 8,
        'started_at': '2026-10-22T07:00:00Z',
      },
    ],
  };

  /// Ochiq smena (`null` — yo'q).
  Map<String, Object?>? shift;

  /// Bootstrap'dagi tez qo'shish tugmalari (`quick_items`, P3-26): bo'sh — ilova nom bo'yicha taxmin qiladi.
  final List<Map<String, Object?>> quickItems = <Map<String, Object?>>[];

  /// Zal xaritasidagi yaqin bronlar: joy id → `{booking_uuid, starts_at, guests, contact_name, contact_phone}` (bo'sh
  /// joy `reserved_soon`).
  final Map<int, Map<String, Object?>> reservations =
      <int, Map<String, Object?>>{};

  /// Schyoti chiqarilgan buyurtmalar (zal xaritasida `bill_printed`).
  final Set<String> billPrinted = <String>{};

  /// Buyurtmalar (uuid → to'liq POS ko'rinishi).
  final Map<String, Map<String, Object?>> orders =
      <String, Map<String, Object?>>{};

  /// Server javob bermaydigan yo'llar (sinash uchun): yo'l qismi → status.
  final Map<String, int> failPaths = <String, int>{};

  /// Qo'shimcha yo'llar (P3-22: `FakeCashierBackend` — schyot, to'lov, smena, bron, nasiya, onlayn navbat): xodim
  /// aniqlangandan keyin birinchi bo'lib so'raladi; `null` — odatdagi javob.
  FakeResponse? Function(FakeRequest request)? extraRoutes;

  /// P3-31: terminalning o'z tokeni bilan keladigan yo'llar (`POST /pos/sync`, `GET /pos/sync/status`) va xodim
  /// sessiyasi bilan keladigan sinxronlash yo'llari ([FakeSyncBackend]).
  FakeResponse? Function(
    String method,
    String path,
    Map<String, String> query,
    Map<String, Object?> data,
  )?
  terminalRoutes;
  FakeResponse? Function(FakeRequest request)? syncRoutes;

  final Map<String, int> _staffTokens = <String, int>{};

  /// P3-31: so'rovni [staffId] xodim nomidan bajaradi (sinxronlashda har amal o'z xodimi bilan keladi).
  FakeResponse handleAsStaff(
    int staffId,
    String method,
    String path,
    Object? body, {
    Map<String, String> query = const <String, String>{},
    String? idempotencyKey,
  }) {
    final String token = 'sync-staff-$staffId';
    _staffTokens[token] = staffId;
    try {
      return handle(
        method,
        path,
        query,
        body,
        token,
        idempotencyKey: idempotencyKey,
      );
    } finally {
      _staffTokens.remove(token);
    }
  }

  final Map<String, ({String permission, bool used, DateTime expires})>
  _approvals = <String, ({String permission, bool used, DateTime expires})>{};
  final Map<int, int> _wrongPins = <int, int>{};
  int _seq = 0;
  int _itemSeq = 100;

  /// So'rovlar jurnali: `METHOD /path`.
  final List<String> log = <String>[];

  /// Har o'zgartiruvchi so'rovning `Idempotency-Key` i (yo'l bilan).
  final List<(String path, String? key)> idempotencyKeys =
      <(String, String?)>[];

  // --- menyu ---------------------------------------------------------------------------------------------------------

  void _seedMenu() {
    categories
      ..add(<String, Object?>{
        'id': 1,
        'name': 'Choy va non',
        'parent_id': null,
        'sort_order': 1,
        'image_url': null,
      })
      ..add(<String, Object?>{
        'id': 2,
        'name': 'Taomlar',
        'parent_id': null,
        'sort_order': 2,
        'image_url': null,
      });
    products
      ..add(_product(1, 1, 'Choy', 'Choynak', '5000.00', variantId: 1))
      ..add(_product(2, 1, 'Non', 'Dona', '4000.00', variantId: 2))
      ..add(
        _product(
          3,
          2,
          'Osh',
          'Choyxona oshi',
          '120000.00',
          variantId: 3,
          unit: 'kg',
          step: '0.500',
          min: '0.500',
          unitLabel: 'kg (guruch)',
          stationId: 1,
          batchTracked: true,
        ),
      )
      ..add(
        _product(
          4,
          2,
          'Shashlik',
          'Six',
          '18000.00',
          variantId: 4,
          modifierGroups: <Object?>[
            <String, Object?>{
              'id': 1,
              'uuid': 'mg-1',
              'name': "Qo'shimcha",
              'min_select': 0,
              'max_select': null,
              'is_required': false,
              'sort_order': 0,
              'is_active': true,
              'modifiers': <Object?>[
                <String, Object?>{
                  'id': 1,
                  'uuid': '00000000-0000-4000-8000-0000000000a1',
                  'name': 'Piyoz',
                  'price_delta': '2000.00',
                  'max_quantity': 1,
                  'is_available': true,
                  'unavailable_reason': null,
                },
              ],
            },
          ],
        ),
      );
  }

  static String variantUuid(int id) =>
      '00000000-0000-4000-8000-${id.toString().padLeft(12, '0')}';

  Map<String, Object?> _product(
    int id,
    int categoryId,
    String name,
    String variantName,
    String price, {
    required int variantId,
    String unit = 'piece',
    String? step,
    String? min,
    String? unitLabel,
    int? stationId,
    bool batchTracked = false,
    List<Object?> modifierGroups = const <Object?>[],
  }) => <String, Object?>{
    'id': id,
    'uuid': 'product-$id',
    'store_id': storeId,
    'category_id': categoryId,
    'name': name,
    'description': null,
    'kind': 'dish',
    'sort_order': 0,
    'batch_tracked': batchTracked,
    'is_available': true,
    'unavailable_reason': null,
    'station_id': stationId,
    'image_url': null,
    'variants': <Object?>[
      <String, Object?>{
        'id': variantId,
        'uuid': variantUuid(variantId),
        'product_id': id,
        'name': variantName,
        'price': price,
        'base_price': price,
        'stock': 1000,
        'unit': unit,
        'quantity_step': step,
        'min_quantity': min,
        'batch_consumption': '1.000',
        'unit_label': unitLabel,
        'is_available': true,
        'unavailable_reason': null,
      },
    ],
    'modifier_groups': modifierGroups,
  };

  Map<String, Object?>? _variant(String uuid) {
    for (final Map<String, Object?> p in products) {
      for (final Object? v in p['variants']! as List<Object?>) {
        final Map<String, Object?> variant = (v! as Map<Object?, Object?>)
            .cast<String, Object?>();
        if (variant['uuid'] == uuid) {
          return <String, Object?>{...variant, 'product': p};
        }
      }
    }
    return null;
  }

  // --- yordamchi --------------------------------------------------------------------------------------------------

  /// Kirgan xodim (token bo'yicha).
  Map<String, Object?>? staffOf(String? token) {
    final int? id = _staffTokens[token];
    if (id == null) return null;
    for (final Map<String, Object?> s in staff) {
      if (s['id'] == id) return s;
    }
    return null;
  }

  List<String> permissionsOf(Map<String, Object?> member) =>
      rolePermissions['${member['role']}'] ?? const <String>[];

  /// Buyurtma qo'shadi (ochilgan, oshxonaga yuborilgan qatorlar bilan) — skrinshot va testlar uchun.
  String seedOrder({
    int? tableId,
    int guests = 4,
    List<(int variantId, String quantity)> lines = const <(int, String)>[
      (3, '1.500'),
      (1, '2'),
    ],
    bool sent = true,
    Duration age = const Duration(minutes: 95),
    int? waiterId,
  }) {
    final Map<String, Object?> order = _newOrder(
      channel: tableId == null ? 'pos_takeaway' : 'pos_hall',
      tableId: tableId,
      guests: guests,
      openedBy: 3,
      waiterId: waiterId,
      openedAt: now().subtract(age),
    );
    for (final (int variantId, String q) in lines) {
      _addLine(
        order,
        <String, Object?>{'variant_id': variantUuid(variantId), 'quantity': q},
        1,
        3,
      );
    }
    for (final Map<String, Object?> i in _items(order)) {
      i['added_at'] = _iso(now().subtract(age));
    }
    if (sent) {
      for (final Map<String, Object?> i in _items(order)) {
        i['kitchen_status'] = 'sent';
        i['sent_at'] = _iso(now().subtract(age));
      }
      order['status'] = 'preparing';
    }
    order['rounds'] = 1;
    _recompute(order);
    return order['uuid']! as String;
  }

  /// Mavjud buyurtmaga bosqich qo'shadi (skrinshot va testlar uchun): [fireMode] `hold` — ushlab turilgan,
  /// [sent] — oshxonaga yuborilgan, [ready] — oshxona "tayyor" degan.
  void seedRound(
    String uuid,
    List<(int variantId, String quantity)> lines, {
    bool sent = true,
    bool ready = false,
    String fireMode = 'now',
    Duration age = Duration.zero,
    int by = 4,
    String? note,
  }) {
    final Map<String, Object?> order = orders[uuid]!;
    final int round = (order['rounds']! as int) + 1;
    final int before = _items(order).length;
    for (final (int variantId, String q) in lines) {
      _addLine(
        order,
        <String, Object?>{
          'variant_id': variantUuid(variantId),
          'quantity': q,
          'fire_mode': fireMode,
          'note': ?note,
        },
        round,
        by,
      );
    }
    for (final Map<String, Object?> i in _items(order).skip(before)) {
      i['added_at'] = _iso(now().subtract(age));
      if (sent) {
        i['kitchen_status'] = ready ? 'ready' : 'sent';
        i['sent_at'] = _iso(now().subtract(age));
      }
    }
    order['rounds'] = round;
    order['version'] = (order['version']! as int) + 1;
    _recompute(order);
  }

  /// Yangi smena ochadi.
  void openShift({String openingCash = '100000.00'}) {
    shift = <String, Object?>{
      'uuid': '33333333-3333-4333-8333-333333333333',
      'status': 'open',
      'branch_id': branchId,
      'device': <String, Object?>{'uuid': deviceUuid, 'name': deviceName},
      'business_date': _date(now()),
      'opened_at': _iso(now()),
      'opened_by': <String, Object?>{'id': 3, 'name': 'Kamola Karimova'},
      'opening_cash': openingCash,
      'closed_at': null,
      'closed_by': null,
      'expected_cash': null,
      'counted_cash': null,
      'counted_breakdown': null,
      'difference': null,
      'z_number': null,
      'force_closed': false,
      'close_note': null,
    };
  }

  void emit(String event, Map<String, Object?> data) =>
      onEvent?.call(event, data);

  // --- so'rov ---------------------------------------------------------------------------------------------------------

  /// Bitta so'rovga javob. [token] — `Authorization: Bearer` qiymati.
  FakeResponse handle(
    String method,
    String path,
    Map<String, String> query,
    Object? body,
    String? token, {
    String? idempotencyKey,
  }) {
    log.add('$method $path');
    if (method != 'GET') idempotencyKeys.add((path, idempotencyKey));
    for (final MapEntry<String, int> f in failPaths.entries) {
      if (path.contains(f.key)) {
        return FakeResponse(f.value, <String, Object?>{
          'message': 'Server Error',
        });
      }
    }
    final Map<String, Object?> data = _decode(body);
    final String p = path.replaceFirst(RegExp(r'^/api'), '');

    // Ulash va terminal
    if (method == 'POST' && p == '/v1/devices/pair') return _pair(data);
    if (p == '/broadcasting/auth') return _channelAuth(token, data);
    if (p.startsWith('/v1/pos/sync') && token == terminalToken) {
      if (!paired) return _unauthorized();
      final FakeResponse? sync = terminalRoutes?.call(method, p, query, data);
      if (sync != null) return sync;
    }
    if (p.startsWith('/v1/terminal') || p.contains('/staff-session')) {
      if (!paired || token != terminalToken) return _unauthorized();
      if (method == 'GET' && p == '/v1/terminal/staff') {
        return _terminalStaff(query['role']);
      }
      if (method == 'DELETE' && p == '/v1/terminal') {
        paired = false;
        _staffTokens.clear();
        return _ok(null);
      }
      if (method == 'POST' && p == '/v1/devices/$deviceUuid/staff-session') {
        return _signIn(data);
      }
      if (method == 'DELETE' && p == '/v1/devices/$deviceUuid/staff-session') {
        _staffTokens.clear();
        return _ok(null);
      }
      return _notFound();
    }

    final Map<String, Object?>? member = staffOf(token);
    if (member == null) return _unauthorized();
    final FakeResponse? extra = extraRoutes?.call(
      FakeRequest(
        method: method,
        path: p,
        query: query,
        data: data,
        member: member,
        idempotencyKey: idempotencyKey,
      ),
    );
    if (extra != null) return extra;
    final FakeResponse? sync = syncRoutes?.call(
      FakeRequest(
        method: method,
        path: p,
        query: query,
        data: data,
        member: member,
        idempotencyKey: idempotencyKey,
      ),
    );
    if (sync != null) return sync;

    if (method == 'GET' && p == '/v1/pos/bootstrap') {
      return _ok(_bootstrap(member));
    }
    if (method == 'GET' && p == '/v1/catalog') {
      return _catalog(query['channel']);
    }
    if (method == 'GET' && p == '/v1/catalog/availability') {
      return _ok(<String, Object?>{
        'store_id': storeId,
        'branches': <Object?>[availability],
        'generated_at': _iso(now()),
      });
    }
    if (method == 'GET' && p == '/v1/pos/floor') return _ok(_floor());
    if (method == 'POST' && p == '/v1/pos/approvals') return _approve(data);
    if (method == 'GET' && p == '/v1/pos/shifts/current') {
      return _ok(<String, Object?>{
        'shift': shift,
        'report': shift == null ? null : _xReport(),
      });
    }
    if (method == 'POST' && p == '/v1/pos/shifts') {
      if (shift != null) {
        return _fail(
          409,
          'shift_already_open',
          'Bu terminalda smena allaqachon ochiq.',
        );
      }
      openShift(openingCash: '${data['opening_cash'] ?? '0.00'}');
      emit('shift.changed', <String, Object?>{
        'shift': <String, Object?>{
          'uuid': shift!['uuid'],
          'status': 'open',
          'store_id': storeId,
          'branch_id': branchId,
          'device_uuid': deviceUuid,
          'business_date': shift!['business_date'],
          'z_number': null,
        },
        'change': 'opened',
        'changed_at': _iso(now()),
      });
      return _ok(shift, status: 201, message: 'Smena ochildi.');
    }
    if (method == 'GET' && p == '/v1/pos/orders') {
      return _ok(<Object?>[
        for (final Map<String, Object?> o in _openOrders()) _queueRow(o),
      ]);
    }
    if (method == 'POST' && p == '/v1/pos/orders') return _open(data, member);
    final RegExpMatch? m = RegExp(r'^/v1/pos/orders/([^/]+)(?:/(.+))?$')
        .firstMatch(p);
    if (m != null) {
      return _orderAction(method, m.group(1)!, m.group(2), data, member);
    }

    // P3-22 bo'limlari: bo'sh javoblar
    if (method == 'GET' && p == '/v1/pos/customer-accounts') {
      return _ok(<String, Object?>{
        'accounts': <Object?>[],
        'meta': <String, Object?>{
          'current_page': 1,
          'last_page': 1,
          'per_page': 30,
          'total': 0,
        },
      });
    }
    if (method == 'GET' && p == '/v1/pos/customer-accounts/debtors') {
      return _ok(<String, Object?>{
        'total_debt': '0.00',
        'count': 0,
        'accounts': <Object?>[],
      });
    }
    if (method == 'GET' && p == '/v1/pos/bookings/calendar') {
      return _ok(<String, Object?>{
        'branch_id': branchId,
        'from': query['from'],
        'to': query['to'],
        'timezone': 'Asia/Tashkent',
        'rooms': <Object?>[],
        'unassigned': <Object?>[],
      });
    }
    if (method == 'GET' && p == '/v1/pos/bookings') {
      return _ok(<String, Object?>{'bookings': <Object?>[]});
    }
    return _notFound();
  }

  // --- ulash va PIN ------------------------------------------------------------------------------------------------

  FakeResponse _pair(Map<String, Object?> data) {
    if (rejectPairing) {
      return _fail(
        422,
        'pairing_invalid',
        "Kod noto'g'ri yoki muddati o'tgan.",
      );
    }
    paired = true;
    return _ok(<String, Object?>{
      'token': terminalToken,
      'device': <String, Object?>{
        'uuid': deviceUuid,
        'type': 'pos',
        'name': data['name'] ?? deviceName,
        'platform': data['platform'],
        'app_version': data['app_version'],
        'store_id': storeId,
        'branch_id': branchId,
      },
      'store': <String, Object?>{'id': storeId, 'name': 'Rohat choyxonasi'},
      'branch': <String, Object?>{'id': branchId, 'name': 'Chilonzor'},
    });
  }

  FakeResponse _terminalStaff(String? roles) {
    final Set<String>? only = roles?.split(',').toSet();
    return _ok(<Object?>[
      for (final Map<String, Object?> s in staff)
        if (pins.containsKey(s['id']) &&
            (only == null || only.contains(s['role'])))
          s,
    ]);
  }

  FakeResponse _signIn(Map<String, Object?> data) {
    final Map<String, Object?>? member = _member(data);
    final FakeResponse? rejected = _checkPin(member, data['pin']);
    if (rejected != null) return rejected;
    final String token = 'staff-${member!['id']}-${++_seq}';
    _staffTokens
      ..clear()
      ..[token] = member['id']! as int;
    return _ok(<String, Object?>{
      'token': token,
      'expires_at': _iso(now().add(const Duration(hours: 12))),
      'user': <String, Object?>{
        'id': member['id'],
        'name': member['name'],
        'role': member['role'],
        'branch_id': branchId,
      },
      'permissions': permissionsOf(member),
    }, message: 'Xush kelibsiz!');
  }

  Map<String, Object?>? _member(Map<String, Object?> data) {
    for (final Map<String, Object?> s in staff) {
      if (s['id'] == data['user_id'] ||
          (data['staff_code'] != null &&
              s['staff_code'] == data['staff_code'])) {
        return s;
      }
    }
    return null;
  }

  FakeResponse? _checkPin(Map<String, Object?>? member, Object? pin) {
    final int? id = member?['id'] as int?;
    if (id != null && (_wrongPins[id] ?? 0) >= 3) {
      return const FakeResponse(
        429,
        <String, Object?>{
          'success': false,
          'message': "PIN ko'p marta noto'g'ri terildi.",
          'data': <String, Object?>{'reason': 'pin_locked', 'retry_after': 60},
        },
        headers: <String, String>{'Retry-After': '60'},
      );
    }
    if (member == null || pins[id] != pin) {
      if (id != null) _wrongPins[id] = (_wrongPins[id] ?? 0) + 1;
      return _fail(422, 'pin_invalid', "Xodim yoki PIN noto'g'ri.");
    }
    _wrongPins.remove(id);
    return null;
  }

  FakeResponse _channelAuth(String? token, Map<String, Object?> data) {
    if (!paired || token != terminalToken) return _unauthorized();
    final String channel = '${data['channel_name']}';
    if (channel != 'private-pos-branch.$branchId') {
      return const FakeResponse(403, <String, Object?>{'message': 'Forbidden'});
    }
    return FakeResponse(200, <String, Object?>{
      'auth': '$realtimeKey:signature-${data['socket_id']}',
    });
  }

  // --- tasdiq --------------------------------------------------------------------------------------------------------

  FakeResponse _approve(Map<String, Object?> data) {
    final Map<String, Object?>? member = _member(data);
    final FakeResponse? rejected = _checkPin(member, data['pin']);
    if (rejected != null) return rejected;
    final String permission = '${data['permission']}';
    if (!permissionsOf(member!).contains(permission)) {
      return _fail(
        403,
        'approver_not_allowed',
        'Bu xodim bu amalni tasdiqlay olmaydi.',
      );
    }
    final String token = 'approval-${++_seq}';
    final DateTime expires = now().add(const Duration(seconds: 60));
    _approvals[token] = (permission: permission, used: false, expires: expires);
    return _ok(
      <String, Object?>{
        'token': token,
        'permission': permission,
        'expires_at': _iso(expires),
        'approved_by': <String, Object?>{
          'id': member['id'],
          'name': member['name'],
          'role': member['role'],
        },
      },
      status: 201,
      message: 'Tasdiqlandi.',
    );
  }

  /// Ruxsat yoki menejer tasdig'i ([extraRoutes] uchun): `null` — ruxsat bor yoki tasdiq yaroqli (ishlatildi);
  /// aks holda 403 javobi (`approval_required` ...).
  FakeResponse? authorize(
    Map<String, Object?> member,
    String permission,
    Object? approvalToken,
  ) => _authorize(member, permission, approvalToken);

  /// `null` — ruxsat bor yoki tasdiq yaroqli; aks holda rad javobi.
  FakeResponse? _authorize(
    Map<String, Object?> member,
    String permission,
    Object? approvalToken,
  ) {
    if (permissionsOf(member).contains(permission)) return null;
    if (approvalToken == null) {
      return FakeResponse(403, <String, Object?>{
        'success': false,
        'message': "Bu amal uchun menejer tasdig'i kerak.",
        'data': <String, Object?>{
          'reason': 'approval_required',
          'permission': permission,
        },
      });
    }
    final ({String permission, bool used, DateTime expires})? a =
        _approvals['$approvalToken'];
    if (a == null) return _fail(403, 'approval_invalid', 'Tasdiq yaroqsiz.');
    if (a.used) {
      return _fail(403, 'approval_used', 'Tasdiq allaqachon ishlatilgan.');
    }
    if (!a.expires.isAfter(now())) {
      return _fail(403, 'approval_expired', "Tasdiq muddati o'tdi.");
    }
    if (a.permission != permission) {
      return _fail(
        403,
        'approval_wrong_permission',
        'Tasdiq boshqa amal uchun.',
      );
    }
    _approvals['$approvalToken'] = (
      permission: a.permission,
      used: true,
      expires: a.expires,
    );
    return null;
  }

  // --- bootstrap, menyu, zal ------------------------------------------------------------------------------------------

  Map<String, Object?> _bootstrap(Map<String, Object?> member) =>
      <String, Object?>{
        'server_time': _iso(now()),
        'menu_version': menuVersion,
        'store': <String, Object?>{
          'id': storeId,
          'name': 'Rohat choyxonasi',
          'timezone': 'Asia/Tashkent',
        },
        'branch': <String, Object?>{
          'id': branchId,
          'name': 'Chilonzor',
          'address': null,
          'phone': null,
          'timezone': 'Asia/Tashkent',
          'business_day_starts_at': '05:00',
          'business_date': _date(now()),
          'pos_settings': <String, Object?>{
            'mode': 'hybrid',
            'printing_device_id': null,
            'screen_lock_minutes': screenLockMinutes,
            'queue_numbers': false,
            'alerts': <String, Object?>{
              'open_room_hours': 6,
              'void_amount': '200000.00',
              'discount_percent': '10.00',
              'unaccepted_online_minutes': 3,
            },
            'holidays': <Object?>[],
            'booking_slot_minutes': null,
          },
        },
        'device': <String, Object?>{
          'uuid': deviceUuid,
          'type': 'pos',
          'name': deviceName,
          'is_printing_device': true,
        },
        'user': <String, Object?>{
          'id': member['id'],
          'name': member['name'],
          'role': member['role'],
          'permissions': permissionsOf(member),
        },
        'stations': <Object?>[
          <String, Object?>{
            'id': 1,
            'branch_id': branchId,
            'name': 'Osh',
            'kind': 'osh',
            'kind_label': 'Osh va qozon',
            'sort_order': 0,
            'is_active': true,
            'show_on_kds': true,
          },
        ],
        'station_routes': <String, Object?>{
          'categories': <Object?>[],
          'products': <Object?>[
            <String, Object?>{'product_id': 3, 'station_id': 1},
          ],
        },
        'printers': printers,
        'staff': <Object?>[
          for (final Map<String, Object?> s in staff)
            <String, Object?>{
              'id': s['id'],
              'name': s['name'],
              'role': s['role'],
              'photo_url': null,
            },
        ],
        'halls': halls,
        'tables': tables,
        'shift': shift == null
            ? null
            : <String, Object?>{
                'uuid': shift!['uuid'],
                'status': 'open',
                'business_date': shift!['business_date'],
                'opened_at': shift!['opened_at'],
                'opened_by': 3,
                'opening_cash': shift!['opening_cash'],
                'device_id': deviceUuid,
              },
        'quick_items': quickItems,
        // P3-31: what the till keeps for the limited mode (the fake's own rules: 10% in the hall, rooms 300 000)
        'offline': <String, Object?>{
          'device_prefix': 'K1',
          'next_local_number': 1,
          'business_date': _date(now()),
          'day_type': 'weekday',
          'fees': <Object?>[
            <String, Object?>{
              'hall_id': null,
              'channel': 'pos_hall',
              'service_charge_percent': '10.00',
              'place_fee_amount': '0.00',
              'place_fee_mode': 'per_person',
            },
            <String, Object?>{
              'hall_id': null,
              'channel': 'pos_takeaway',
              'service_charge_percent': '0.00',
              'place_fee_amount': '0.00',
              'place_fee_mode': 'per_person',
            },
          ],
          'places': <Object?>[
            for (final Map<String, Object?> t in tables)
              <String, Object?>{
                'table_id': t['id'],
                'hall_id': t['hall_id'],
                'room_fee': t['kind'] == 'room' ? '300000.00' : '0.00',
                'min_spend': '0.00',
                'min_spend_mode': 'per_table',
              },
          ],
          'guest_items': <Object?>[],
          'limits': <String, Object?>{
            'batch_size': 50,
            'warn_after_minutes': 15,
            'alert_after_minutes': 120,
            'max_queued_orders': 500,
            'menu_max_age_hours': 24,
            'contact_max_age_hours': 24,
            'fiscal_warn_hours': <int>[24, 40],
            'fiscal_limit_hours': 48,
            'clock_skew_warn_seconds': 300,
          },
        },
      };

  FakeResponse _catalog(String? channel) {
    if (channel != 'pos_hall' && channel != 'pos_takeaway') {
      return _fail(403, null, 'Ruxsat etilmagan');
    }
    return _ok(<String, Object?>{
      'channel': channel,
      'menu_version': menuVersion,
      'branch': <String, Object?>{'id': branchId, 'name': 'Chilonzor'},
      'categories': categories,
      'products': products,
      'availability': availability,
    });
  }

  Map<String, Object?> _floor() => <String, Object?>{
    'branch_id': branchId,
    'generated_at': _iso(now()),
    'overdue_after_hours': 6,
    'halls': <Object?>[
      for (final Map<String, Object?> h in halls)
        <String, Object?>{
          ...h,
          'is_active': true,
          'tables': <Object?>[
            for (final Map<String, Object?> t in tables)
              if (t['hall_id'] == h['id']) _floorTable(t),
          ],
        },
    ],
    'untabled_orders': <Object?>[
      for (final Map<String, Object?> o in _openOrders())
        if (o['table'] == null) _floorOrder(o),
    ],
  };

  Map<String, Object?> _floorTable(Map<String, Object?> t) {
    final List<Map<String, Object?>> open = <Map<String, Object?>>[
      for (final Map<String, Object?> o in _openOrders())
        if ((o['table'] as Map<String, Object?>?)?['id'] == t['id']) o,
    ];
    final Map<String, Object?>? reservation = reservations[t['id']];
    final String status = open.isEmpty
        ? (reservation == null ? 'free' : 'reserved_soon')
        : open.any((Map<String, Object?> o) => billPrinted.contains(o['uuid']))
        ? 'bill_printed'
        : (open.any((Map<String, Object?> o) => _openMinutes(o) >= 360)
              ? 'overdue'
              : 'occupied');
    return <String, Object?>{
      'id': t['id'],
      'name': t['name'],
      'kind': t['kind'],
      'seats': t['seats'],
      'layout': t['layout'],
      'is_active': true,
      'min_spend': t['min_spend'],
      'min_spend_mode': t['min_spend_mode'],
      'status': status,
      'bill_requested': open.any(
        (Map<String, Object?> o) =>
            o['bill_requested_at'] != null && !billPrinted.contains(o['uuid']),
      ),
      'reservation': open.isEmpty ? reservation : null,
      'orders': <Object?>[
        for (final Map<String, Object?> o in open) _floorOrder(o),
      ],
    };
  }

  int _openMinutes(Map<String, Object?> o) =>
      now().difference(DateTime.parse('${o['opened_at']}')).inMinutes;

  Map<String, Object?> _floorOrder(Map<String, Object?> o) => <String, Object?>{
    'uuid': o['uuid'],
    'short_code': o['short_code'],
    'status': o['status'],
    'channel': o['channel'],
    'version': o['version'],
    'opened_at': o['opened_at'],
    'open_minutes': _openMinutes(o),
    'overdue': _openMinutes(o) >= 360,
    'bill_printed': billPrinted.contains(o['uuid']),
    'bill_requested': o['bill_requested_at'] != null,
    'bill_requested_at': o['bill_requested_at'],
    'guests_count': o['guests_count'],
    'waiter': o['waiter'],
    'total_price': o['total_price'],
    'items_count': _items(o)
        .where((Map<String, Object?> i) => i['kitchen_status'] != 'void')
        .length,
    'unsent_count': _items(o)
        .where((Map<String, Object?> i) => i['kitchen_status'] == 'pending')
        .length,
    'ready_count': _items(o)
        .where((Map<String, Object?> i) => i['kitchen_status'] == 'ready')
        .length,
  };

  Map<String, Object?> _queueRow(Map<String, Object?> o) => <String, Object?>{
    'uuid': o['uuid'],
    'short_code': o['short_code'],
    'status': o['status'],
    'channel': o['channel'],
    'type': o['type'],
    'branch_id': branchId,
    'version': o['version'],
    'hall': o['hall'],
    'table': o['table'],
    'hall_ref': o['hall_ref'],
    'table_ref': o['table_ref'],
    'guests_count': o['guests_count'],
    'waiter': o['waiter'],
    'items_count': _items(o)
        .where((Map<String, Object?> i) => i['kitchen_status'] != 'void')
        .length,
    'subtotal': o['subtotal'],
    'total_price': o['total_price'],
    'payment_status': 'pending',
    'bill_locked': o['bill_locked'],
    'bill_requested': o['bill_requested_at'] != null,
    'fiscal_pending': false,
    'booking_id': null,
    'opened_at': o['opened_at'],
    'updated_at': o['updated_at'],
  };

  /// `POST /pos/orders/{uuid}/request-bill` (P3-26): bir marta belgilaydi, `order.bill_requested` yuboradi; qaytadan —
  /// o'zgarishsiz. `version` ixtiyoriy.
  FakeResponse _requestBill(
    Map<String, Object?> order,
    Map<String, Object?> data,
    Map<String, Object?> member,
  ) {
    final int? version = (data['version'] as num?)?.toInt();
    if (version != null && version != order['version']) {
      return FakeResponse(409, <String, Object?>{
        'success': false,
        'message': "Buyurtma boshqa qurilmada o'zgartirildi. Uni qayta yuklang va amalni takrorlang.",
        'data': <String, Object?>{
          'reason': 'version_conflict',
          'version': order['version'],
        },
      });
    }
    if (order['bill_requested_at'] != null) return _ok(order);
    order
      ..['bill_requested_at'] = _iso(now())
      ..['version'] = (order['version']! as int) + 1
      ..['updated_at'] = _iso(now());
    emit('order.bill_requested', <String, Object?>{
      'order': <String, Object?>{
        'uuid': order['uuid'],
        'status': order['status'],
        'store_id': storeId,
        'branch_id': branchId,
        'courier_id': null,
        'updated_at': order['updated_at'],
      },
      'version': order['version'],
      'table_id': (order['table'] as Map<String, Object?>?)?['id'],
      'bill_requested_at': order['bill_requested_at'],
      'requested_by': member['id'],
    });
    return _ok(order, message: "Schyot so'raldi.");
  }

  Iterable<Map<String, Object?>> _openOrders() => orders.values.where(
    (Map<String, Object?> o) =>
        o['status'] != 'delivered' && o['status'] != 'canceled',
  );

  // --- buyurtmalar -------------------------------------------------------------------------------------------------

  Map<String, Object?> _newOrder({
    required String channel,
    int? tableId,
    int? guests,
    required int openedBy,
    int? waiterId,
    String? note,
    DateTime? openedAt,
  }) {
    final int n = ++_seq;
    final String uuid =
        '0000${n.toString().padLeft(4, '0')}-0000-4000-8000-${n.toString().padLeft(12, '0')}';
    Map<String, Object?>? table;
    Map<String, Object?>? hall;
    for (final Map<String, Object?> t in tables) {
      if (t['id'] == tableId) {
        table = <String, Object?>{
          'id': t['id'],
          'name': t['name'],
          'kind': t['kind'],
        };
        final Map<String, Object?> h = halls.firstWhere(
          (Map<String, Object?> h) => h['id'] == t['hall_id'],
        );
        hall = <String, Object?>{'id': h['id'], 'name': h['name']};
      }
    }
    Map<String, Object?>? waiter;
    for (final Map<String, Object?> s in staff) {
      if (s['id'] == (waiterId ?? openedBy) &&
          (waiterId != null || s['role'] == 'waiter')) {
        waiter = <String, Object?>{'id': s['id'], 'name': s['name']};
      }
    }
    final String opened = _iso(openedAt ?? now());
    final Map<String, Object?> order = <String, Object?>{
      'uuid': uuid,
      'short_code': 'POS$n',
      'status': 'accepted',
      'channel': channel,
      'type': channel == 'pos_hall' ? 'dine_in' : 'pickup',
      'store_id': storeId,
      'branch_id': branchId,
      'version': 1,
      'hall': hall,
      'table': table,
      'hall_ref': hall?['name'],
      'table_ref': table?['name'],
      'guests_count': guests ?? 0,
      'waiter': waiter,
      'opened_by': openedBy,
      'device_id': 1,
      'booking_id': null,
      'booking_uuid': null,
      'deposit_available': '0.00',
      'bill_locked': false,
      'bill_requested_at': null,
      'notes': note,
      'opened_at': opened,
      'updated_at': opened,
      'subtotal': '0.00',
      'discount': '0.00',
      'service_charge_amount': '0.00',
      'place_fee_amount': '0.00',
      'room_fee_amount': '0.00',
      'min_spend_topup_amount': '0.00',
      'total_price': '0.00',
      'charges': <String, Object?>{},
      'rounds': 0,
      'items': <Map<String, Object?>>[],
    };
    orders[uuid] = order;
    return order;
  }

  List<Map<String, Object?>> _items(Map<String, Object?> o) =>
      (o['items']! as List<Object?>).cast<Map<String, Object?>>();

  /// `null` — qator qo'shildi; aks holda 422.
  FakeResponse? _addLine(
    Map<String, Object?> order,
    Map<String, Object?> line,
    int round,
    int by, {
    String? fire,
  }) {
    final Map<String, Object?>? v = _variant('${line['variant_id']}');
    if (v == null) return _fail(422, 'variant_unavailable', 'Taom topilmadi.');
    final Map<String, Object?> product = v['product']! as Map<String, Object?>;
    final double qty = double.parse('${line['quantity']}');
    final double min = double.tryParse('${v['min_quantity']}') ?? 0;
    if (qty < min) {
      return FakeResponse(422, <String, Object?>{
        'message':
            "'${product['name']} (${v['name']})' kamida ${v['min_quantity']} kg buyurtma qilinadi.",
        'errors': <String, Object?>{
          'items.0.quantity': <String>[
            "'${product['name']} (${v['name']})' kamida 0.5 kg buyurtma qilinadi.",
          ],
        },
      });
    }
    final int price = _tiyin(v['price']);
    var modsTotal = 0;
    final List<Map<String, Object?>> mods = <Map<String, Object?>>[];
    for (final Object? m
        in (line['modifiers'] as List<Object?>?) ?? const <Object?>[]) {
      final Map<String, Object?> choice = (m! as Map<Object?, Object?>)
          .cast<String, Object?>();
      for (final Object? g in product['modifier_groups']! as List<Object?>) {
        for (final Object? mod
            in (g! as Map<Object?, Object?>)['modifiers']! as List<Object?>) {
          final Map<Object?, Object?> modifier = mod! as Map<Object?, Object?>;
          if (modifier['uuid'] == choice['id']) {
            final int q = (choice['quantity'] as num?)?.toInt() ?? 1;
            modsTotal += _tiyin(modifier['price_delta']) * q;
            mods.add(<String, Object?>{
              'id': modifier['id'],
              'name': modifier['name'],
              'group_name': (g as Map<Object?, Object?>)['name'],
              'quantity': q,
              'price_delta': modifier['price_delta'],
            });
          }
        }
      }
    }
    final int total = ((price + modsTotal) * qty).round();
    final String unit = '${v['unit']}';
    final String qtyLabel = _qtyLabel(qty);
    _items(order).add(<String, Object?>{
      'id': ++_itemSeq,
      'round_no': round,
      'product_variant_id': v['id'],
      'product_name': product['name'],
      'variant_name': v['name'],
      'quantity': qty,
      'unit': unit,
      'quantity_label': unit == 'piece'
          ? qtyLabel
          : '$qtyLabel ${v['unit_label'] ?? unit}',
      'unit_price': _money(price),
      'modifiers': mods,
      'modifiers_total': _money(modsTotal),
      'total_price': _money(total),
      'note': line['note'],
      'seat_no': line['seat_no'],
      'fire_mode': line['fire_mode'] ?? fire ?? 'now',
      'due_at': line['due_at'],
      'kitchen_status': 'pending',
      'station_id': product['station_id'],
      'sent_at': null,
      'ready_at': null,
      'added_by': <String, Object?>{'id': by, 'name': _nameOf(by)},
      'added_at': _iso(now()),
      'voided_at': null,
      'voided_by': null,
      'void_reason': null,
    });
    return null;
  }

  String? _nameOf(int id) {
    for (final Map<String, Object?> s in staff) {
      if (s['id'] == id) return s['name'] as String?;
    }
    return null;
  }

  void _recompute(Map<String, Object?> order) {
    var subtotal = 0;
    for (final Map<String, Object?> i in _items(order)) {
      if (i['kitchen_status'] != 'void') subtotal += _tiyin(i['total_price']);
    }
    final Map<String, Object?> charges =
        (order['charges'] as Map<String, Object?>?) ?? <String, Object?>{};
    final List<String> waived =
        ((charges['waived'] as List<Object?>?) ?? const <Object?>[])
            .cast<String>();
    final bool hall = order['channel'] == 'pos_hall';
    final int service = hall && !waived.contains('service_charge')
        ? (subtotal * 10 / 100).round() ~/ 100 * 100
        : 0;
    final bool room =
        (order['table'] as Map<String, Object?>?)?['kind'] == 'room';
    final int roomFee = room && !waived.contains('room_fee') ? 30000000 : 0;
    final int total = subtotal + service + roomFee;
    order
      ..['subtotal'] = _money(subtotal)
      ..['service_charge_amount'] = _money(service)
      ..['room_fee_amount'] = _money(roomFee)
      ..['total_price'] = _money(total)
      ..['charges'] = <String, Object?>{
        'items_subtotal': _money(subtotal),
        'discount': '0.00',
        'service_charge_percent': hall ? '10.00' : '0.00',
        'service_charge_amount': _money(service),
        'place_fee_amount': '0.00',
        'room_fee_amount': _money(roomFee),
        'room_tariff_id': room ? 1 : null,
        'business_date': _date(now()),
        'day_type': 'weekday',
        'min_spend': '0.00',
        'min_spend_topup_amount': '0.00',
        'waived': waived,
        'total': _money(total),
        'waivers': charges['waivers'] ?? <Object?>[],
      };
  }

  void _touch(
    Map<String, Object?> order,
    String change, {
    List<int> itemIds = const <int>[],
  }) {
    order['version'] = (order['version']! as int) + 1;
    order['updated_at'] = _iso(now());
    _recompute(order);
    emit('order.items_changed', <String, Object?>{
      'order': _eventOrder(order),
      'version': order['version'],
      'table_id': (order['table'] as Map<String, Object?>?)?['id'],
      'change': change,
      'item_ids': itemIds,
    });
  }

  Map<String, Object?> _eventOrder(Map<String, Object?> o) => <String, Object?>{
    'uuid': o['uuid'],
    'status': o['status'],
    'store_id': storeId,
    'branch_id': branchId,
    'courier_id': null,
    'updated_at': o['updated_at'],
  };

  FakeResponse _open(Map<String, Object?> data, Map<String, Object?> member) {
    final String channel = '${data['channel']}';
    // The device's own id: a repeat returns the order already opened (as the backend does)
    final Object? external = data['external_id'];
    if (external is String) {
      for (final Map<String, Object?> o in orders.values) {
        if (o['external_id'] == external) return _ok(o);
      }
    }
    final int? tableId = (data['table_id'] as num?)?.toInt();
    if (tableId != null) {
      final Map<String, Object?>? t = tables
          .cast<Map<String, Object?>?>()
          .firstWhere(
            (Map<String, Object?>? t) => t!['id'] == tableId,
            orElse: () => null,
          );
      if (t == null) {
        return _fail(422, 'table_inactive', 'Bu joy yoki zona hozir yopiq.');
      }
      final bool busy = _openOrders().any(
        (Map<String, Object?> o) =>
            (o['table'] as Map<String, Object?>?)?['id'] == tableId,
      );
      if (busy && t['kind'] == 'room') {
        return _fail(
          422,
          'table_occupied',
          'Xona band: unda ochiq buyurtma bor.',
        );
      }
    }
    final Map<String, Object?> order = _newOrder(
      channel: channel,
      tableId: tableId,
      guests: (data['guests_count'] as num?)?.toInt(),
      openedBy: member['id']! as int,
      waiterId: (data['waiter_id'] as num?)?.toInt(),
      note: data['note'] as String?,
    );
    if (external is String) order['external_id'] = external;
    final List<Object?> items =
        (data['items'] as List<Object?>?) ?? const <Object?>[];
    for (final Object? i in items) {
      final FakeResponse? bad = _addLine(
        order,
        (i! as Map<Object?, Object?>).cast<String, Object?>(),
        1,
        member['id']! as int,
      );
      if (bad != null) {
        orders.remove(order['uuid']);
        return bad;
      }
    }
    if (items.isNotEmpty) order['rounds'] = 1;
    if (data['send'] == true) _sendLines(order, null);
    _recompute(order);
    emit('order.created', <String, Object?>{'order': _eventOrder(order)});
    return _ok(order, status: 201, message: 'Buyurtma ochildi.');
  }

  List<int> _sendLines(Map<String, Object?> order, List<int>? ids) {
    final List<int> sent = <int>[];
    for (final Map<String, Object?> i in _items(order)) {
      if (i['kitchen_status'] != 'pending') continue;
      final bool chosen = ids == null
          ? i['fire_mode'] != 'hold'
          : ids.contains(i['id']);
      if (!chosen) continue;
      i
        ..['kitchen_status'] = 'sent'
        ..['sent_at'] = _iso(now());
      sent.add(i['id']! as int);
    }
    if (sent.isNotEmpty && order['status'] == 'accepted') {
      order['status'] = 'preparing';
    }
    return sent;
  }

  FakeResponse _orderAction(
    String method,
    String uuid,
    String? action,
    Map<String, Object?> data,
    Map<String, Object?> member,
  ) {
    final Map<String, Object?>? order = orders[uuid];
    if (order == null) return _fail(404, null, 'Buyurtma topilmadi.');
    if (method == 'GET' && action == null) return _ok(order);
    if (method == 'GET' && action == 'bills') {
      return _ok(<String, Object?>{
        'order': <String, Object?>{
          'uuid': uuid,
          'status': order['status'],
          'payment_status': 'pending',
          'version': order['version'],
          'queue_number': null,
          'business_date': _date(now()),
          'bill_locked_at': null,
          'closed_at': null,
          'total_price': order['total_price'],
          'billed_amount': '0.00',
          'paid_amount': '0.00',
          'unbilled_amount': order['total_price'],
          'fiscal_pending': false,
          'bills_out_of_date': false,
        },
        'bills': <Object?>[],
        'refunds': <Object?>[],
      });
    }
    if (method != 'POST' || action == null) return _notFound();
    if (order['status'] == 'delivered' || order['status'] == 'canceled') {
      return _fail(
        422,
        'order_closed',
        "Buyurtma yopilgan yoki bekor qilingan: uni o'zgartirib bo'lmaydi.",
      );
    }
    final FakeResponse? manage = _authorize(member, 'orders.manage', null);
    if (manage != null && action != 'cancel') return manage;
    if (action == 'request-bill') return _requestBill(order, data, member);
    final int version = (data['version'] as num?)?.toInt() ?? -1;
    if (version != order['version']) {
      return FakeResponse(409, <String, Object?>{
        'success': false,
        'message': "Buyurtma boshqa qurilmada o'zgartirildi. Uni qayta yuklang va amalni takrorlang.",
        'data': <String, Object?>{
          'reason': 'version_conflict',
          'version': order['version'],
        },
      });
    }
    final int by = member['id']! as int;
    switch (action.split('/')) {
      case <String>['rounds']:
        final int round = (order['rounds']! as int) + 1;
        final int before = _items(order).length;
        for (final Object? i
            in (data['items'] as List<Object?>?) ?? const <Object?>[]) {
          final FakeResponse? bad = _addLine(
            order,
            (i! as Map<Object?, Object?>).cast<String, Object?>(),
            round,
            by,
            fire: data['fire_mode'] as String?,
          );
          if (bad != null) {
            _items(order).removeRange(before, _items(order).length);
            return bad;
          }
        }
        order['rounds'] = round;
        if (data['send'] == true) _sendLines(order, null);
        _touch(order, 'round_added');
        return _ok(order, message: "Taomlar qo'shildi.");
      case <String>['send']:
        final List<int> sent = _sendLines(
          order,
          (data['item_ids'] as List<Object?>?)?.cast<int>(),
        );
        if (sent.isEmpty) {
          return _fail(
            422,
            'nothing_to_send',
            "Oshxonaga yuboriladigan yangi taom yo'q.",
          );
        }
        _touch(order, 'sent', itemIds: sent);
        return _ok(order, message: 'Oshxonaga yuborildi.');
      case <String>['items', final String id, 'void']:
        final Map<String, Object?>? item = _item(order, int.parse(id));
        if (item == null) return _notFound();
        if (item['kitchen_status'] == 'void') {
          return _fail(422, 'already_void', "Bu taom allaqachon o'chirilgan.");
        }
        if (item['kitchen_status'] != 'pending') {
          final FakeResponse? denied = _authorize(
            member,
            'orders.void_item',
            data['approval_token'],
          );
          if (denied != null) return denied;
        }
        item
          ..['kitchen_status'] = 'void'
          ..['voided_at'] = _iso(now())
          ..['voided_by'] = <String, Object?>{'id': by, 'name': member['name']}
          ..['void_reason'] = data['reason'];
        _touch(order, 'voided', itemIds: <int>[item['id']! as int]);
        return _ok(order, message: "Taom o'chirildi.");
      case <String>['items', final String id, 'served']:
        final Map<String, Object?>? item = _item(order, int.parse(id));
        if (item == null) return _notFound();
        if (item['kitchen_status'] != 'ready' &&
            item['kitchen_status'] != 'sent') {
          return _fail(422, 'item_not_ready', 'Taom hali tayyor emas.');
        }
        item['kitchen_status'] = 'served';
        _touch(order, 'served', itemIds: <int>[item['id']! as int]);
        return _ok(order, message: 'Berildi.');
      case <String>['guests']:
        order['guests_count'] = data['guests_count'];
        _touch(order, 'guests_changed');
        return _ok(order, message: 'Mehmonlar soni saqlandi.');
      case <String>['move']:
        final int tableId = (data['table_id']! as num).toInt();
        if ((order['table'] as Map<String, Object?>?)?['id'] == tableId) {
          return _fail(422, 'same_table', 'Buyurtma allaqachon shu joyda.');
        }
        final Map<String, Object?> t = tables.firstWhere(
          (Map<String, Object?> t) => t['id'] == tableId,
        );
        order['table'] = <String, Object?>{
          'id': t['id'],
          'name': t['name'],
          'kind': t['kind'],
        };
        order['table_ref'] = t['name'];
        _touch(order, 'moved');
        return _ok(order, message: "Buyurtma ko'chirildi.");
      case <String>['merge']:
        final Map<String, Object?>? source = orders['${data['source_uuid']}'];
        if (source == null ||
            source == order ||
            source['channel'] != 'pos_hall' ||
            order['channel'] != 'pos_hall' ||
            source['status'] == 'delivered' ||
            source['status'] == 'canceled') {
          return _fail(
            422,
            'merge_not_allowed',
            "Bu buyurtmalarni birlashtirib bo'lmaydi.",
          );
        }
        if ((data['source_version'] as num?)?.toInt() != source['version']) {
          return FakeResponse(409, <String, Object?>{
            'success': false,
            'message': "Buyurtma boshqa qurilmada o'zgartirildi.",
            'data': <String, Object?>{
              'reason': 'version_conflict',
              'version': source['version'],
            },
          });
        }
        final int offset = order['rounds']! as int;
        for (final Map<String, Object?> i in _items(source)) {
          _items(order).add(<String, Object?>{
            ...i,
            'round_no': ((i['round_no'] as int?) ?? 1) + offset,
          });
        }
        source['items'] = <Map<String, Object?>>[];
        order['rounds'] = offset + (source['rounds']! as int);
        order['guests_count'] =
            ((order['guests_count'] as int?) ?? 0) +
            ((source['guests_count'] as int?) ?? 0);
        source['status'] = 'canceled';
        source['version'] = (source['version']! as int) + 1;
        _touch(order, 'merged');
        emit('order.status_changed', <String, Object?>{
          'order': _eventOrder(source),
        });
        return _ok(order, message: 'Buyurtmalar birlashtirildi.');
      case <String>['cancel']:
        final FakeResponse? denied = _authorize(
          member,
          'orders.cancel',
          data['approval_token'],
        );
        if (denied != null) return denied;
        order['status'] = 'canceled';
        for (final Map<String, Object?> i in _items(order)) {
          i['kitchen_status'] = 'void';
        }
        _touch(order, 'cancelled');
        emit('order.status_changed', <String, Object?>{
          'order': _eventOrder(order),
        });
        return _ok(order, message: 'Buyurtma bekor qilindi.');
      case <String>['charges', final String charge, final String op]:
        final Map<String, Object?> charges =
            order['charges']! as Map<String, Object?>;
        final List<String> waived =
            ((charges['waived'] as List<Object?>?) ?? const <Object?>[])
                .cast<String>()
                .toList();
        if (op == 'waive') {
          final FakeResponse? denied = _authorize(
            member,
            'orders.discount',
            data['approval_token'],
          );
          if (denied != null) return denied;
          waived.add(charge);
        } else {
          waived.remove(charge);
        }
        charges['waived'] = waived;
        _touch(order, 'charges_changed');
        return _ok(
          order,
          message: op == 'waive'
              ? 'Qator olib tashlandi.'
              : 'Qator qaytarildi.',
        );
    }
    return _notFound();
  }

  Map<String, Object?>? _item(Map<String, Object?> order, int id) {
    for (final Map<String, Object?> i in _items(order)) {
      if (i['id'] == id) return i;
    }
    return null;
  }

  Map<String, Object?> _xReport() => <String, Object?>{
    'shift_uuid': shift?['uuid'],
    'generated_at': _iso(now()),
    'sales': <String, Object?>{
      'bills': 0,
      'received': '0.00',
      'refunded': '0.00',
      'net': '0.00',
    },
    'by_tender': <Object?>[],
    'cash': <String, Object?>{
      'opening': shift?['opening_cash'],
      'expected': shift?['opening_cash'],
    },
    'movements': <Object?>[],
  };

  // --- javoblar ----------------------------------------------------------------------------------------------------

  static Map<String, Object?> _decode(Object? body) {
    if (body is Map) return body.cast<String, Object?>();
    if (body is String && body.isNotEmpty) {
      try {
        final Object? decoded = jsonDecode(body);
        if (decoded is Map) return decoded.cast<String, Object?>();
      } on FormatException {
        // form-urlencoded (broadcasting/auth)
        return Uri.splitQueryString(body);
      }
    }
    return <String, Object?>{};
  }

  static FakeResponse _ok(
    Object? data, {
    int status = 200,
    String message = '',
  }) => FakeResponse(status, <String, Object?>{
    'success': true,
    'message': message,
    'data': data,
  });

  static FakeResponse _fail(int status, String? reason, String message) =>
      FakeResponse(status, <String, Object?>{
        'success': false,
        'message': message,
        'data': <String, Object?>{'reason': ?reason},
      });

  static FakeResponse _unauthorized() =>
      const FakeResponse(401, <String, Object?>{'message': 'Unauthenticated.'});

  static FakeResponse _notFound() => const FakeResponse(404, <String, Object?>{
    'success': false,
    'message': 'Topilmadi.',
  });

  static String _iso(DateTime d) => d.toUtc().toIso8601String();

  static String _date(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  static int _tiyin(Object? v) => (double.parse('$v') * 100).round();

  static String _money(int tiyin) =>
      '${tiyin ~/ 100}.${(tiyin % 100).toString().padLeft(2, '0')}';

  static String _qtyLabel(double q) => q == q.roundToDouble()
      ? '${q.toInt()}'
      : q.toString().replaceFirst(RegExp(r'0+$'), '');
}
