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

/// POS buyurtmasining bitta qatori (P3-12): bosqich, sex, `fire_mode`, muddat va holat bilan.
class PosLine {
  const PosLine(
    this.name,
    this.qty, {
    this.unit = 'piece',
    this.station,
    this.round = 1,
    this.status = 'sent',
    this.fire = 'now',
    this.dueIn,
    this.sentAgo = const Duration(minutes: 1),
    this.note,
    this.mods = const <String>[],
    this.voidReason,
    this.product,
    this.category,
  });

  final String name;
  final String qty;
  final String unit;
  final int? station;
  final int round;

  /// `sent`, `ready`, `served`, `void` yoki `pending` (ushlab turilgan).
  final String status;

  /// `now` yoki `hold`.
  final String fire;

  /// Oldindan buyurtma muddati (hozirdan): faqat `hold` va yuborilgan oldindan buyurtmada.
  final Duration? dueIn;

  /// Yuborilganiga qancha bo'ldi; ushlab turilgan qatorda e'tiborga olinmaydi.
  final Duration sentAgo;
  final String? note;
  final List<String> mods;
  final String? voidReason;

  /// Taom va kategoriya id (`product_id`, `category_id`): sexsiz ushlab turilgan qator sexi yo'naltirishdan topiladi.
  final int? product;
  final int? category;
}

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

  /// Hammaning PIN kodi. Serverdagi qoidaga mos (backend `StaffPinService::weakness`: 1111, 1234, 2468, 1357 kabi
  /// oddiy kodlar o'rnatilmaydi; pos_data soxta serveridagi Aziz PIN'i bilan bir xil).
  String pin = '7305';

  /// Shuncha noto'g'ri urinishdan keyin 429 `pin_locked`.
  int maxWrong = 3;
  int lockSeconds = 60;
  final Map<int, int> _wrong = <int, int>{};
  final Map<int, DateTime> _lockedUntil = <int, DateTime>{};

  /// Kirgan xodimning ruxsatlari.
  List<String> permissions = <String>['orders.view', 'kitchen.manage'];

  /// P4-21: `GET /owner/billing/entitlements` javobi (`data`); `null` — eski server (404).
  Map<String, Object?>? entitlements;

  /// `GET /pos/bootstrap` dagi sexlar. `null` — endpoint yo'q (fazа 2 serveri): 404.
  List<Map<String, Object?>>? stations;

  /// Chiptalar javobida HTTP `Date` sarlavhasi (server soati) bor.
  bool sendDate = true;

  /// `GET /pos/bootstrap` `station_routes`: taom id -> sex id va kategoriya id -> sex id.
  final Map<int, int> productRoutes = <int, int>{};
  final Map<int, int> categoryRoutes = <int, int>{};

  /// Qozonlar (`/owner/batches`) va qozon bo'yicha sotiladigan taomlar (`GET /catalog`).
  final List<Map<String, Object?>> batches = <Map<String, Object?>>[];
  List<Map<String, Object?>> catalogProducts = <Map<String, Object?>>[];
  int _batchSeq = 0;

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

  /// P6-14: tezkor kassa buyurtmasi navbat raqami bilan. [paid] — avval to'langan: buyurtma yopilgan (`delivered`), raqami
  /// `preparing` ekan oshxona chiptasi doskada turadi.
  String addQueueOrder({
    required int number,
    bool paid = true,
    Duration age = const Duration(minutes: 3),
    List<(String, String, String, List<String>)> items =
        const <(String, String, String, List<String>)>[
          ("Lag'mon", '2', 'piece', <String>[]),
        ],
  }) {
    final String uuid = addOrder(
      type: 'pickup',
      channel: 'pos_takeaway',
      age: age,
      items: items,
      status: paid ? 'delivered' : 'accepted',
    );
    final Map<String, Object?> order = _order(uuid)!;
    order['queue_number'] = number;
    order['pickup_status'] = 'preparing';
    for (final Map<String, Object?> i in _items(order)) {
      i['fire_mode'] = 'now';
      i['sent_at'] = now().subtract(age);
      i['kitchen_status'] = 'sent';
    }
    return uuid;
  }

  /// Yangi sex (`kind`: osh, mangal, ...); `stations` ro'yxatiga qo'shiladi.
  void addStation(int id, String name, String kind, {bool showOnKds = true}) {
    (stations ??= <Map<String, Object?>>[]).add(<String, Object?>{
      'id': id,
      'branch_id': branchId,
      'name': name,
      'kind': kind,
      'kind_label': name,
      'sort_order': id,
      'is_active': true,
      'show_on_kds': showOnKds,
    });
  }

  /// POS buyurtmasi (zal, `accepted`): qatorlar sex, bosqich va holat bilan. Qaytaradi: buyurtma uuid.
  String addPosOrder({
    String table = 'Xona 3',
    Duration age = const Duration(minutes: 2),
    String status = 'accepted',
    List<PosLine> lines = const <PosLine>[],
    String? notes,
  }) {
    final int n = ++_seq;
    final String uuid =
        '${n.toRadixString(16).padLeft(8, '0')}-0000-4000-8000-${n.toString().padLeft(12, '0')}';
    final DateTime accepted = now().subtract(age);
    orders.add(<String, Object?>{
      'uuid': uuid,
      'short_code': 'P$n',
      'status': status,
      'type': 'dine_in',
      'channel': 'pos_hall',
      'branch_id': branchId,
      'hall_ref': 'Xonalar',
      'table_ref': table,
      'guests_count': 6,
      'notes': notes,
      'created_at': accepted.subtract(const Duration(minutes: 1)),
      'accepted_at': accepted,
      'preparing_at': null,
      'ready_at': null,
      'items': <Map<String, Object?>>[
        for (final PosLine l in lines) _posItem(l),
      ],
    });
    _event('order.created', uuid);
    return uuid;
  }

  Map<String, Object?> _posItem(PosLine l) {
    final bool held = l.status == 'pending' && l.fire == 'hold';
    return <String, Object?>{
      'id': ++_itemSeq,
      'product_name': l.name,
      'variant_name': null,
      'quantity': num.tryParse(l.qty.split(' ').first) ?? 1,
      'unit': l.unit,
      'quantity_label': l.qty,
      'modifiers': <Map<String, Object?>>[
        for (final String m in l.mods)
          <String, Object?>{'name': m, 'group_name': null, 'quantity': 1},
      ],
      'kitchen_status': l.status,
      'ready_at': l.status == 'ready' ? now() : null,
      'round_no': l.round,
      'fire_mode': l.fire,
      'station_id': l.station,
      'sent_at': held ? null : now().subtract(l.sentAgo),
      'due_at': l.dueIn == null ? null : now().add(l.dueIn!),
      'seat_no': null,
      'note': l.note,
      'voided_at': l.status == 'void' ? now() : null,
      'void_reason': l.voidReason,
      'product_id': l.product,
      'category_id': l.category,
    };
  }

  /// Backend'dagidek: qator sexi taomning yo'naltirishidan, bo'lmasa kategoriyasinikidan.
  int? _routeOf(Map<String, Object?> item) =>
      productRoutes[item['product_id']] ?? categoryRoutes[item['category_id']];

  /// Ochiq stolga yangi qatorlar (masalan, keyingi bosqich) qo'shildi: buyurtma o'sha, qatorlar yangi.
  void addLines(String uuid, List<PosLine> lines) {
    _items(_order(uuid)!).addAll(<Map<String, Object?>>[
      for (final PosLine l in lines) _posItem(l),
    ]);
    _event('kitchen.item_status_changed', uuid);
  }

  /// Ofitsiant qatorni o'chirdi ("O'CHIRILDI"): hodisa yuboriladi.
  void voidLine(String uuid, int itemId, {String reason = 'Mehmon xohlamadi'}) {
    final Map<String, Object?> item = _items(_order(uuid)!)
        .firstWhere((Map<String, Object?> i) => i['id'] == itemId);
    item['kitchen_status'] = 'void';
    item['voided_at'] = now();
    item['void_reason'] = reason;
    _event('kitchen.item_status_changed', uuid);
  }

  /// Ofitsiant qatorni mehmonga berdi (`ready` → `served`).
  void serveLine(String uuid, int itemId) {
    final Map<String, Object?> item = _items(_order(uuid)!)
        .firstWhere((Map<String, Object?> i) => i['id'] == itemId);
    item['kitchen_status'] = 'served';
    _event('kitchen.item_status_changed', uuid);
  }

  /// Ushlab turilgan qator oshxonaga yuborildi (`pending` → `sent`).
  void sendLine(String uuid, int itemId) {
    final Map<String, Object?> item = _items(_order(uuid)!)
        .firstWhere((Map<String, Object?> i) => i['id'] == itemId);
    item['kitchen_status'] = 'sent';
    item['sent_at'] = now();
    // Yuborishda server sexni yozadi (ushlab turilgan qatorda hali yo'q bo'lishi mumkin)
    item['station_id'] ??= _routeOf(item);
    _event('kitchen.item_status_changed', uuid);
  }

  /// Butun buyurtma bekor qilindi: pishmagan qatorlari `void`, buyurtma chiptalardan ketadi.
  void cancelOrder(String uuid, {String reason = 'Mehmonlar ketdi'}) {
    final Map<String, Object?> order = _order(uuid)!;
    for (final Map<String, Object?> i in _items(order)) {
      if (i['kitchen_status'] == 'ready' || i['kitchen_status'] == 'served') {
        continue;
      }
      i['kitchen_status'] = 'void';
      i['voided_at'] = now();
      i['void_reason'] = reason;
    }
    order['status'] = 'canceled';
    order['canceled_at'] = now();
    _event('order.status_changed', uuid);
  }

  /// Ochiq qozon.
  int addBatch(
    String product,
    num produced, {
    num? left,
    int productId = 1,
    String? label,
    bool open = true,
  }) {
    final int id = ++_batchSeq;
    batches.add(<String, Object?>{
      'id': id,
      'uuid': 'b0000000-0000-4000-8000-${id.toString().padLeft(12, '0')}',
      'branch_id': branchId,
      'product_id': productId,
      'product_name': product,
      'product_variant_id': null,
      'variant_name': null,
      'unit': 'piece',
      'label': label,
      'quantity_produced': produced,
      'quantity_left': left ?? produced,
      'is_open': open,
      'started_at': now().toUtc().toIso8601String(),
      'sold_out_at': null,
      'closed_at': open ? null : now().toUtc().toIso8601String(),
    });
    return id;
  }

  /// Choyxona namunasi (P3-24): sexlar, mangal va oldindan buyurtmalar, o'chirilgan va berilgan qator, qozonlar.
  /// Oshpazga `batches.manage` beriladi.
  void seedPosDemo() {
    if (!permissions.contains('batches.manage')) {
      permissions.add('batches.manage');
    }
    addStation(1, 'Mangal', 'mangal');
    addStation(2, 'Osh', 'osh');
    addStation(3, 'Salat', 'salad_bar');
    addStation(4, 'Choy', 'tea');
    addPosOrder(
      table: 'Xona 3',
      age: const Duration(minutes: 14),
      lines: const <PosLine>[
        PosLine(
          "Qo'y shashlik",
          '6',
          station: 1,
          sentAgo: Duration(minutes: 12),
          note: 'Yaxshi pishgan',
        ),
        PosLine(
          'Tovuq shashlik',
          '4',
          station: 1,
          sentAgo: Duration(minutes: 12),
        ),
        PosLine(
          "Lula kabob",
          '4',
          station: 1,
          round: 2,
          sentAgo: Duration(minutes: 2),
        ),
        PosLine(
          "Qo'y shashlik",
          '4',
          station: 1,
          round: 2,
          sentAgo: Duration(minutes: 2),
        ),
        PosLine('Osh', '1.5 kg', unit: 'kg', station: 2, status: 'ready'),
        PosLine('Achichuk', '2', station: 3, status: 'served'),
      ],
    );
    final String tapchan = addPosOrder(
      table: 'Tapchan 2',
      age: const Duration(minutes: 5),
      lines: const <PosLine>[
        PosLine(
          "Qo'y shashlik",
          '10',
          station: 1,
          sentAgo: Duration(minutes: 4),
        ),
        PosLine(
          'Jigar shashlik',
          '6',
          station: 1,
          sentAgo: Duration(minutes: 4),
        ),
        PosLine('Choy', '2', station: 4, mods: <String>['Ko\'k']),
      ],
    );
    // Ofitsiant "Jigar"ni o'chirdi
    final List<Map<String, Object?>> tapchanItems = _items(_order(tapchan)!);
    voidLine(
      tapchan,
      tapchanItems[1]['id']! as int,
      reason: 'Mehmon xohlamadi',
    );
    // Ushlab turilgan oldindan buyurtmalarda sex hali yozilmagan: oshxona uni yo'naltirishdan topadi (osh kategoriyasi
    // osh sexiga, shashlik taomi mangalga)
    categoryRoutes[20] = 2;
    productRoutes[31] = 1;
    addPosOrder(
      table: 'Xona 5',
      age: const Duration(minutes: 1),
      lines: const <PosLine>[
        PosLine(
          'Toy oshi',
          '8 kg',
          unit: 'kg',
          category: 20,
          status: 'pending',
          fire: 'hold',
          dueIn: Duration(hours: 2, minutes: 35),
          note: 'Kechki 19:00 ga',
        ),
        PosLine(
          'Shashlik assorti',
          '20',
          product: 31,
          category: 30,
          status: 'pending',
          fire: 'hold',
          dueIn: Duration(minutes: 55),
        ),
        PosLine(
          'Qozon kabob',
          '3 kg',
          unit: 'kg',
          category: 20,
          status: 'pending',
          fire: 'hold',
          dueIn: Duration(minutes: 70),
        ),
        PosLine(
          'Osh',
          '2 kg',
          unit: 'kg',
          category: 20,
          status: 'pending',
          fire: 'hold',
          dueIn: Duration(minutes: 20),
        ),
      ],
    );
    addPosOrder(
      table: 'Xona 6',
      age: const Duration(minutes: 40),
      lines: const <PosLine>[
        PosLine("Sho'rva", '3', station: 2, sentAgo: Duration(minutes: 22)),
      ],
    );
    catalogProducts = <Map<String, Object?>>[
      <String, Object?>{
        'id': 21,
        'name': 'Qozon kabob',
        'batch_tracked': true,
        'variants': <Map<String, Object?>>[],
      },
      <String, Object?>{
        'id': 22,
        'name': 'Palov (devzira)',
        'batch_tracked': true,
        'variants': <Map<String, Object?>>[
          <String, Object?>{'id': 221, 'name': 'Oddiy'},
          <String, Object?>{'id': 222, 'name': 'Sergo\'sht'},
        ],
      },
    ];
    addBatch('Osh', 40, left: 14, label: '1-qozon', productId: 11);
    addBatch("Sho'rva", 30, left: 4, label: 'Tushlik', productId: 12);
    addBatch('Mastava', 25, left: 0, productId: 13);
    addBatch('Dimlama', 20, left: 3, productId: 14, open: false);
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

    // P4-21: the plan (`entitlements`); `null` — an old server without the endpoint (404)
    if (p == '/v1/owner/billing/entitlements') {
      if (bearer == null || bearer != _staffToken) return _unauthenticated();
      final Map<String, Object?>? plan = entitlements;
      return plan == null ? _notFound('Topilmadi.') : _ok(plan);
    }

    if (p == '/v1/pos/bootstrap' ||
        p == '/v1/catalog' ||
        p.startsWith('/v1/owner/batches')) {
      if (bearer == null || bearer != _staffToken) return _unauthenticated();
      return _setup(method, p, data, query);
    }

    final RegExpMatch? queueReady = RegExp(
      r'^/v1/pos/orders/([^/]+)/ready$',
    ).firstMatch(p);
    if (method == 'POST' && queueReady != null) {
      if (bearer == null || bearer != _staffToken) return _unauthenticated();
      return _queueReady(queueReady[1]!);
    }

    if (p.startsWith('/v1/staff/')) {
      if (bearer == null || bearer != _staffToken) return _unauthenticated();
      final RegExpMatch? detail = RegExp(r'^/v1/staff/orders/([^/]+)$')
          .firstMatch(p);
      if (method == 'GET' && detail != null) {
        final Map<String, Object?>? o = _order(detail[1]!);
        return o == null ? _notFound() : _ok(_ticket(o));
      }
      if (method == 'GET' && p == '/v1/staff/kitchen/tickets') {
        // P3-26: `canceled_within` (daqiqa) — shu vaqt ichida bekor qilinganlar ham, `canceled_at` bilan
        final int? within = int.tryParse(query['canceled_within'] ?? '');
        return FakeResponse(
          200,
          <String, Object?>{
            'success': true,
            'message': '',
            'data': <Object?>[
              for (final Map<String, Object?> o in orders)
                if (o['status'] == 'accepted' ||
                    o['status'] == 'preparing' ||
                    // P6-14: avval to'langan kassa buyurtmasi raqami tayyor bo'lguncha
                    (o['status'] == 'delivered' &&
                        o['pickup_status'] == 'preparing'))
                  _ticket(o),
              if (within != null)
                for (final Map<String, Object?> o in orders)
                  if (o['status'] == 'canceled' &&
                      o['canceled_at'] is DateTime &&
                      now().difference(o['canceled_at']! as DateTime) <
                          Duration(minutes: within))
                    _ticket(o),
            ],
            'meta': <String, Object?>{'truncated': false},
          },
          headers: <String, String>{if (sendDate) 'Date': _httpDate(now())},
        );
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

  /// `/pos/bootstrap`, `/catalog` va `/owner/batches*`.
  FakeResponse _setup(
    String method,
    String p,
    Map<String, Object?> data,
    Map<String, String> query,
  ) {
    if (p == '/v1/pos/bootstrap') {
      final List<Map<String, Object?>>? list = stations;
      if (list == null) return _notFound('Topilmadi.');
      return _ok(<String, Object?>{
        'server_time': now().toUtc().toIso8601String(),
        'stations': list,
        'station_routes': <String, Object?>{
          'categories': <Object?>[
            for (final MapEntry<int, int> r in categoryRoutes.entries)
              <String, Object?>{'category_id': r.key, 'station_id': r.value},
          ],
          'products': <Object?>[
            for (final MapEntry<int, int> r in productRoutes.entries)
              <String, Object?>{'product_id': r.key, 'station_id': r.value},
          ],
        },
      });
    }
    if (p == '/v1/catalog') {
      return _ok(<String, Object?>{'products': catalogProducts});
    }
    if (method == 'GET' && p == '/v1/owner/batches') {
      final bool closed = query['include_closed'] == '1';
      return _ok(<String, Object?>{
        'branch_id': branchId,
        'batches': <Map<String, Object?>>[
          for (final Map<String, Object?> b in batches)
            if (b['is_open'] == true || closed) b,
        ],
      });
    }
    if (method == 'POST' && p == '/v1/owner/batches') {
      final int productId = (data['product_id']! as num).toInt();
      final int? variantId = (data['product_variant_id'] as num?)?.toInt();
      final bool dup = batches.any(
        (Map<String, Object?> b) =>
            b['is_open'] == true &&
            b['product_id'] == productId &&
            b['product_variant_id'] == variantId,
      );
      if (dup) {
        return const FakeResponse(422, <String, Object?>{
          'success': false,
          'message': 'Bu taom uchun ochiq qozon bor.',
        });
      }
      final Map<String, Object?> product = catalogProducts.firstWhere(
        (Map<String, Object?> pr) => pr['id'] == productId,
        orElse: () => <String, Object?>{'name': 'Taom'},
      );
      final int id = addBatch(
        '${product['name']}',
        data['quantity']! as num,
        productId: productId,
        label: data['label'] as String?,
      );
      return _ok(batches.firstWhere((Map<String, Object?> b) => b['id'] == id));
    }
    final RegExpMatch? m = RegExp(r'^/v1/owner/batches/(\d+)/(adjust|close)$')
        .firstMatch(p);
    if (method == 'POST' && m != null) {
      final Map<String, Object?>? b = batches
          .where((Map<String, Object?> b) => b['id'] == int.parse(m[1]!))
          .firstOrNull;
      if (b == null) return _notFound('Qozon topilmadi.');
      if (m[2] == 'close') {
        b['is_open'] = false;
        b['closed_at'] = now().toUtc().toIso8601String();
      } else if (data['add'] != null) {
        b['quantity_produced'] =
            (b['quantity_produced']! as num) + (data['add']! as num);
        b['quantity_left'] =
            (b['quantity_left']! as num) + (data['add']! as num);
      } else if (data['quantity_left'] != null) {
        b['quantity_left'] = data['quantity_left'];
      }
      _event('menu.availability_changed', '');
      return _ok(b);
    }
    return _notFound('Topilmadi.');
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
    final bool closedPickup =
        order['status'] == 'delivered' && order['pickup_status'] == 'preparing';
    if (order['status'] != 'accepted' &&
        order['status'] != 'preparing' &&
        !closedPickup) {
      return const FakeResponse(422, <String, Object?>{
        'success': false,
        'message': "Buyurtma oshxonada emas: taomni o'zgartirib bo'lmaydi.",
        'data': <String, Object?>{'reason': 'kitchen_status'},
      });
    }
    final bool pos = item['fire_mode'] != null;
    // P3-26: a held pre-order line with a due time may be marked ready before it is sent (ready implies sent)
    final bool early =
        ready &&
        pos &&
        item['kitchen_status'] != 'void' &&
        item['sent_at'] == null &&
        item['fire_mode'] == 'hold' &&
        item['due_at'] != null;
    if (early) item['sent_at'] = now();
    if (pos && (item['kitchen_status'] == 'void' || item['sent_at'] == null)) {
      return FakeResponse(422, <String, Object?>{
        'success': false,
        'message': item['kitchen_status'] == 'void'
            ? "Bu taom o'chirilgan."
            : 'Bu taom hali oshxonaga yuborilmagan.',
        'data': <String, Object?>{'reason': 'kitchen_status'},
      });
    }
    if (pos && item['kitchen_status'] == 'served') {
      return _ok(_ticket(order));
    }
    // A recalled POS line goes back to `sent`, an online one to `pending`
    item['kitchen_status'] = ready ? 'ready' : (pos ? 'sent' : 'pending');
    item['ready_at'] = ready ? now() : null;
    if (ready && order['status'] == 'accepted') {
      order['status'] = 'preparing';
      order['preparing_at'] = now();
    }
    if (ready &&
        closedPickup &&
        items.every(
          (Map<String, Object?> i) =>
              i['kitchen_status'] == 'ready' ||
              i['kitchen_status'] == 'served' ||
              i['kitchen_status'] == 'void',
        )) {
      order['pickup_status'] = 'ready';
    }
    if (ready &&
        !closedPickup &&
        order['type'] != 'dine_in' &&
        items.every(
          (Map<String, Object?> i) =>
              i['kitchen_status'] == 'ready' ||
              i['kitchen_status'] == 'served' ||
              i['kitchen_status'] == 'void',
        )) {
      order['status'] = 'ready';
      order['ready_at'] = now();
    }
    _event('kitchen.item_status_changed', uuid);
    return _ok(_ticket(order));
  }

  /// `POST /pos/orders/{uuid}/ready` (P6-13): raqam tayyor, pishayotgan qatorlar tayyor bo'ladi.
  FakeResponse _queueReady(String uuid) {
    final Map<String, Object?>? order = _order(uuid);
    if (order == null) return _notFound();
    if (order['queue_number'] == null || order['pickup_status'] == null) {
      return const FakeResponse(422, <String, Object?>{
        'success': false,
        'message': 'Bu buyurtma navbat raqami bilan olib ketish navbatida emas.',
        'data': <String, Object?>{'reason': 'not_in_queue'},
      });
    }
    for (final Map<String, Object?> i in _items(order)) {
      if (i['kitchen_status'] == 'sent' || i['kitchen_status'] == 'pending') {
        i['kitchen_status'] = 'ready';
        i['ready_at'] = now();
      }
    }
    order['pickup_status'] = 'ready';
    if (order['status'] == 'accepted' || order['status'] == 'preparing') {
      order['status'] = 'ready';
      order['ready_at'] = now();
    }
    _event('queue.ready', uuid);
    return _ok(<String, Object?>{
      'order_uuid': uuid,
      'number': order['queue_number'],
      'status': 'ready',
    });
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
      'items_total': items
          .where((Map<String, Object?> i) => i['kitchen_status'] != 'void')
          .length,
      'items_ready': items
          .where(
            (Map<String, Object?> i) =>
                i['kitchen_status'] == 'ready' ||
                i['kitchen_status'] == 'served',
          )
          .length,
      'items': <Map<String, Object?>>[
        for (final Map<String, Object?> i in items)
          <String, Object?>{
            ...i,
            'ready_at': iso(i['ready_at']),
            if (i.containsKey('sent_at')) 'sent_at': iso(i['sent_at']),
            if (i.containsKey('due_at')) 'due_at': iso(i['due_at']),
            if (i.containsKey('voided_at')) 'voided_at': iso(i['voided_at']),
          },
      ],
      'created_at': iso(o['created_at']),
      'accepted_at': iso(o['accepted_at']),
      'preparing_at': iso(o['preparing_at']),
      'ready_at': iso(o['ready_at']),
      'canceled_at': iso(o['canceled_at']),
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

const List<String> _weekdays = <String>[
  'Mon',
  'Tue',
  'Wed',
  'Thu',
  'Fri',
  'Sat',
  'Sun',
];
const List<String> _months = <String>[
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'May',
  'Jun',
  'Jul',
  'Aug',
  'Sep',
  'Oct',
  'Nov',
  'Dec',
];

/// HTTP `Date` sarlavhasi (server soati): `Thu, 22 Oct 2026 07:00:00 GMT`.
String _httpDate(DateTime at) {
  final DateTime u = at.toUtc();
  String two(int n) => n.toString().padLeft(2, '0');
  return '${_weekdays[u.weekday - 1]}, ${two(u.day)} ${_months[u.month - 1]} ${u.year} '
      '${two(u.hour)}:${two(u.minute)}:${two(u.second)} GMT';
}
