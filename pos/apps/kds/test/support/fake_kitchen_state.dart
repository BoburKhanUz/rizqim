/// Soxta backend holati (Flutter'siz): widget testlar ([FakeKds] Dio adapteri) va Linux'da ilovani suratga olish uchun
/// `test/fake_server/fake_kds_server.dart` shu sinfni ishlatadi. Javob shakllari backend'dagidek (docs/API.md 9.1,
/// docs/AUTH.md 4–5).
library;

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

class FakeKitchenState {
  FakeKitchenState({DateTime Function()? clock, this.onEvent})
    : now = clock ?? DateTime.now;

  static const String terminalToken = 'terminal-token';
  static const String deviceUuid = '11111111-1111-4111-8111-111111111111';
  static const int branchId = 3;

  DateTime Function() now;
  FakeEventSink? onEvent;

  /// PIN o'rnatilgan xodimlar.
  List<Map<String, Object?>> staff = <Map<String, Object?>>[
    <String, Object?>{
      'id': 5,
      'name': 'Aziz Karimov',
      'role': 'cook',
      'staff_code': '1001',
    },
    <String, Object?>{
      'id': 6,
      'name': 'Dilnoza Rahimova',
      'role': 'cook',
      'staff_code': '1002',
    },
    <String, Object?>{
      'id': 7,
      'name': 'Bobur Aliyev',
      'role': 'manager',
      'staff_code': '1003',
    },
  ];

  /// Hammaning PIN kodi.
  String pin = '2468';

  /// Shuncha noto'g'ri urinishdan keyin 429 `pin_locked`.
  int maxWrong = 3;
  int lockSeconds = 60;
  final Map<int, int> _wrong = <int, int>{};
  final Map<int, DateTime> _lockedUntil = <int, DateTime>{};

  /// Kirgan xodimning ruxsatlari.
  List<String> permissions = <String>['orders.view', 'kitchen.manage'];

  /// Rolga qo'shimcha ruxsatlar (backend `RolePermissions` dagidek: menejer qurilmalarni boshqaradi).
  Map<String, List<String>> rolePermissions = <String, List<String>>{
    'manager': <String>['devices.manage'],
  };

  String? _staffToken;

  /// Buyurtmalar (chipta JSON'i, `accepted_at` DateTime sifatida).
  final List<Map<String, Object?>> orders = <Map<String, Object?>>[];
  int _seq = 100;
  int _itemSeq = 1000;

  /// So'rovlar jurnali: `METHOD /path`.
  final List<String> log = <String>[];

  // --- ma'lumot qo'shish -------------------------------------------------------------------------------------------

  /// Yangi buyurtma (holati `accepted`). [items]: `(nom, miqdor yorlig'i, birlik, modifikatorlar)`.
  String addOrder({
    String type = 'delivery',
    String channel = 'telegram',
    String? notes,
    String? table,
    Duration age = Duration.zero,
    List<(String, String, String, List<String>)> items =
        const <(String, String, String, List<String>)>[
          ('Osh', '1', 'pcs', <String>[]),
        ],
    String status = 'accepted',
  }) {
    final int n = ++_seq;
    final String uuid =
        '${n.toRadixString(16).padLeft(8, '0')}-0000-4000-8000-${n.toString().padLeft(12, '0')}';
    final DateTime accepted = now().subtract(age);
    orders.add(<String, Object?>{
      'uuid': uuid,
      'short_code': 'K$n',
      'status': status,
      'type': type,
      'channel': channel,
      'branch_id': branchId,
      'hall_ref': table != null ? 'Asosiy zal' : null,
      'table_ref': table,
      'guests_count': table != null ? 2 : null,
      'notes': notes,
      'created_at': accepted.subtract(const Duration(minutes: 1)),
      'accepted_at': accepted,
      'preparing_at': null,
      'ready_at': null,
      'items': <Map<String, Object?>>[
        for (final (String name, String label, String unit, List<String> mods)
            in items)
          <String, Object?>{
            'id': ++_itemSeq,
            'product_name': name,
            'variant_name': null,
            'quantity': num.tryParse(label.split(' ').first) ?? 1,
            'unit': unit,
            'quantity_label': label,
            'modifiers': <Map<String, Object?>>[
              for (final String m in mods)
                <String, Object?>{
                  'name': m,
                  'group_name': null,
                  'quantity': 1,
                  'price_delta': null,
                },
            ],
            'kitchen_status': 'pending',
            'ready_at': null,
          },
      ],
    });
    _event('order.created', uuid);
    return uuid;
  }

  /// Namunaviy oshxona: har xil tur, vazn, modifikator, izoh va kutish vaqti.
  void seedDemo() {
    addOrder(
      type: 'dine_in',
      channel: 'pos_hall',
      table: '7',
      age: const Duration(minutes: 23, seconds: 10),
      items: const <(String, String, String, List<String>)>[
        ('Osh', '1.5 kg', 'kg', <String>["Qazi qo'shilsin"]),
        ('Achchiq-chuchuk', '2', 'pcs', <String>[]),
        ('Non', '3', 'pcs', <String>[]),
      ],
    );
    addOrder(
      type: 'pickup',
      channel: 'pos_takeaway',
      age: const Duration(minutes: 12, seconds: 40),
      notes: "Piyozsiz bo'lsin",
      items: const <(String, String, String, List<String>)>[
        ("Lag'mon", '2', 'pcs', <String>['Achchiq', "Qo'shimcha go'sht"]),
        ('Kompot', '1 l', 'l', <String>[]),
      ],
    );
    addOrder(
      age: const Duration(minutes: 6, seconds: 5),
      notes: 'Eshik kodi 45, tel +99890*****67',
      items: const <(String, String, String, List<String>)>[
        ('Somsa', '4', 'pcs', <String>["Go'shtli"]),
        ('Manti', '0.8 kg', 'kg', <String>['Qatiq bilan']),
        ('Choy', '1', 'pcs', <String>['Limon']),
      ],
    );
    addOrder(
      channel: 'mobile',
      age: const Duration(minutes: 2, seconds: 30),
      items: const <(String, String, String, List<String>)>[
        ('Shashlik', '5', 'pcs', <String>['Piyoz bilan']),
        ('Salat "Toshkent"', '1', 'pcs', <String>[]),
      ],
    );
    addOrder(
      type: 'dine_in',
      channel: 'pos_hall',
      table: '3',
      age: const Duration(seconds: 40),
      items: const <(String, String, String, List<String>)>[
        ("Sho'rva", '2', 'pcs', <String>[]),
      ],
    );
    // bitta qatori tayyor
    final Map<String, Object?> first = orders.first;
    final Map<String, Object?> item =
        (first['items']! as List<Object?>)[1]! as Map<String, Object?>;
    item['kitchen_status'] = 'ready';
    item['ready_at'] = now();
    first['status'] = 'preparing';
  }

  // --- HTTP ---------------------------------------------------------------------------------------------------------

  FakeResponse handle(
    String method,
    String path,
    Map<String, String> query,
    Object? body,
    String? bearer,
  ) {
    log.add('$method $path');
    final String p = path.replaceFirst(RegExp(r'^/api'), '');
    final Map<String, Object?> data = body is Map
        ? body.cast<String, Object?>()
        : <String, Object?>{};

    if (method == 'POST' && p == '/v1/devices/pair') {
      return _ok(<String, Object?>{
        'token': terminalToken,
        'device': <String, Object?>{
          'uuid': deviceUuid,
          'type': 'kds',
          'name': 'Oshxona ekrani',
          'platform': 'linux',
          'app_version': '0.2.0',
          'store_id': 7,
          'branch_id': branchId,
        },
        'store': <String, Object?>{'id': 7, 'name': 'Rizqim Osh Markazi'},
        'branch': <String, Object?>{'id': branchId, 'name': 'Chilonzor'},
      }, status: 201);
    }

    if (p == '/broadcasting/auth') {
      if (bearer != terminalToken) return _unauthenticated();
      return FakeResponse(200, <String, Object?>{
        'auth': 'kds-key:${data['socket_id']}:${data['channel_name']}',
      });
    }

    if (p == '/v1/terminal' && method == 'DELETE') {
      if (bearer != terminalToken) return _unauthenticated();
      // Self-revoke: the terminal's staff sessions stop with it
      _staffToken = null;
      return _ok(null);
    }

    if (p == '/v1/terminal/staff' ||
        p == '/v1/devices/$deviceUuid/staff-session') {
      if (bearer != terminalToken) return _unauthenticated();
      if (p == '/v1/terminal/staff') return _ok(staff);
      if (method == 'DELETE') {
        _staffToken = null;
        return _ok(null);
      }
      return _signIn(data);
    }

    if (p.startsWith('/v1/staff/')) {
      if (bearer == null || bearer != _staffToken) return _unauthenticated();
      if (method == 'GET' && p == '/v1/staff/kitchen/tickets') {
        return FakeResponse(200, <String, Object?>{
          'success': true,
          'message': '',
          'data': <Object?>[
            for (final Map<String, Object?> o in orders)
              if (o['status'] == 'accepted' || o['status'] == 'preparing')
                _ticket(o),
          ],
          'meta': <String, Object?>{'truncated': false},
        });
      }
      final RegExpMatch? line = RegExp(
        r'^/v1/staff/orders/([^/]+)/items/(\d+)/(ready|recall)$',
      ).firstMatch(p);
      if (method == 'POST' && line != null) {
        return _kitchenChange(
          line[1]!,
          int.parse(line[2]!),
          line[3] == 'ready',
        );
      }
      final RegExpMatch? status = RegExp(r'^/v1/staff/orders/([^/]+)/status$')
          .firstMatch(p);
      if (method == 'POST' && status != null) {
        return _status(status[1]!, '${data['status']}');
      }
    }
    return FakeResponse(404, <String, Object?>{
      'success': false,
      'message': 'Topilmadi: $method $path',
    });
  }

  FakeResponse _signIn(Map<String, Object?> data) {
    final int? userId = (data['user_id'] as num?)?.toInt();
    final DateTime? until = userId == null ? null : _lockedUntil[userId];
    if (until != null && until.isAfter(now())) {
      final int left = until.difference(now()).inSeconds + 1;
      return FakeResponse(
        429,
        <String, Object?>{
          'success': false,
          'message': "Juda ko'p urinish. Keyinroq qayta urinib ko'ring.",
          'data': <String, Object?>{
            'reason': 'pin_locked',
            'retry_after': left,
          },
        },
        headers: <String, String>{'Retry-After': '$left'},
      );
    }
    final Map<String, Object?>? member = staff
        .where((Map<String, Object?> s) => s['id'] == userId)
        .firstOrNull;
    if (member == null || data['pin'] != pin) {
      if (userId != null) {
        final int wrong = (_wrong[userId] ?? 0) + 1;
        _wrong[userId] = wrong;
        if (wrong >= maxWrong) {
          _lockedUntil[userId] = now().add(Duration(seconds: lockSeconds));
          return FakeResponse(
            429,
            <String, Object?>{
              'success': false,
              'message': "Juda ko'p urinish. Keyinroq qayta urinib ko'ring.",
              'data': <String, Object?>{
                'reason': 'pin_locked',
                'retry_after': lockSeconds,
              },
            },
            headers: <String, String>{'Retry-After': '$lockSeconds'},
          );
        }
      }
      return const FakeResponse(422, <String, Object?>{
        'success': false,
        'message': "Xodim yoki PIN noto'g'ri.",
        'data': <String, Object?>{'reason': 'pin_invalid'},
      });
    }
    _wrong.remove(userId);
    _staffToken = 'staff-token-$userId';
    return _ok(<String, Object?>{
      'token': _staffToken,
      'expires_at': now()
          .add(const Duration(hours: 12))
          .toUtc()
          .toIso8601String(),
      'user': <String, Object?>{
        'id': userId,
        'name': member['name'],
        'role': member['role'],
        'branch_id': branchId,
      },
      'permissions': <String>[
        ...permissions,
        ...?rolePermissions[member['role']],
      ],
    }, status: 201);
  }

  FakeResponse _kitchenChange(String uuid, int itemId, bool ready) {
    final Map<String, Object?>? order = _order(uuid);
    if (order == null) return _notFound();
    final List<Map<String, Object?>> items = _items(order);
    final Map<String, Object?>? item = items
        .where((Map<String, Object?> i) => i['id'] == itemId)
        .firstOrNull;
    if (item == null) return _notFound('Taom topilmadi.');
    if (order['status'] != 'accepted' && order['status'] != 'preparing') {
      return const FakeResponse(422, <String, Object?>{
        'success': false,
        'message': "Buyurtma oshxonada emas: taomni o'zgartirib bo'lmaydi.",
        'data': <String, Object?>{'reason': 'kitchen_status'},
      });
    }
    item['kitchen_status'] = ready ? 'ready' : 'pending';
    item['ready_at'] = ready ? now() : null;
    if (ready && order['status'] == 'accepted') {
      order['status'] = 'preparing';
      order['preparing_at'] = now();
    }
    if (ready &&
        order['type'] != 'dine_in' &&
        items.every(
          (Map<String, Object?> i) => i['kitchen_status'] == 'ready',
        )) {
      order['status'] = 'ready';
      order['ready_at'] = now();
    }
    _event('kitchen.item_status_changed', uuid);
    return _ok(_ticket(order));
  }

  FakeResponse _status(String uuid, String status) {
    final Map<String, Object?>? order = _order(uuid);
    if (order == null) return _notFound();
    if (status != 'ready' || order['status'] != 'preparing') {
      return const FakeResponse(422, <String, Object?>{
        'success': false,
        'message': "Buyurtmani bu holatga o'tkazib bo'lmaydi.",
      });
    }
    order['status'] = 'ready';
    order['ready_at'] = now();
    for (final Map<String, Object?> i in _items(order)) {
      i['kitchen_status'] = 'ready';
    }
    _event('order.status_changed', uuid);
    return _ok(<String, Object?>{'uuid': uuid, 'status': 'ready'});
  }

  Map<String, Object?> _ticket(Map<String, Object?> o) {
    final List<Map<String, Object?>> items = _items(o);
    final DateTime start = (o['accepted_at'] ?? o['created_at'])! as DateTime;
    final DateTime end = o['status'] == 'ready'
        ? o['ready_at']! as DateTime
        : now();
    String? iso(Object? v) =>
        v is DateTime ? v.toUtc().toIso8601String() : null;
    return <String, Object?>{
      ...o,
      'items_total': items.length,
      'items_ready': items
          .where((Map<String, Object?> i) => i['kitchen_status'] == 'ready')
          .length,
      'items': <Map<String, Object?>>[
        for (final Map<String, Object?> i in items)
          <String, Object?>{...i, 'ready_at': iso(i['ready_at'])},
      ],
      'created_at': iso(o['created_at']),
      'accepted_at': iso(o['accepted_at']),
      'preparing_at': iso(o['preparing_at']),
      'ready_at': iso(o['ready_at']),
      'waiting_seconds': end.difference(start).inSeconds.clamp(0, 1 << 31),
    };
  }

  Map<String, Object?>? _order(String uuid) =>
      orders.where((Map<String, Object?> o) => o['uuid'] == uuid).firstOrNull;

  static List<Map<String, Object?>> _items(Map<String, Object?> o) =>
      (o['items']! as List<Object?>).cast<Map<String, Object?>>();

  void _event(String event, String uuid) {
    final Map<String, Object?>? o = _order(uuid);
    onEvent?.call(event, <String, Object?>{
      'order': <String, Object?>{
        'uuid': uuid,
        'status': o?['status'],
        'store_id': 7,
        'branch_id': branchId,
        'courier_id': null,
        'updated_at': now().toUtc().toIso8601String(),
      },
    });
  }

  static FakeResponse _ok(Object? data, {int status = 200}) => FakeResponse(
    status,
    <String, Object?>{'success': true, 'message': 'OK', 'data': data},
  );

  static FakeResponse _unauthenticated() =>
      const FakeResponse(401, <String, Object?>{'message': 'Unauthenticated.'});

  static FakeResponse _notFound([String message = 'Buyurtma topilmadi.']) =>
      FakeResponse(404, <String, Object?>{
        'success': false,
        'message': message,
      });
}
