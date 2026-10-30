import 'dart:math' as math;

import 'fake_pos_state.dart';

/// Soxta backend'ning kassa qismi (P3-22): schyot va bo'lish, to'lov (aralash, avans, nasiya), fiskal chek ("fiskal
/// kutmoqda" va qayta urinish), qaytarish, smena (kassa harakatlari, X, Z), bron (taqvim, to'qnashuv, oldindan
/// buyurtma, avans, "Keldi"), nasiya hisoblari va onlayn buyurtmalar navbati. Javob shakllari docs/API.md (P3-17,
/// P3-18, 9.1) va mahalliy backend'dan olingan namunalar bo'yicha.
///
/// [FakePosState.extraRoutes] ga ulanadi:
///
/// ```dart
/// final backend = FakeCashierBackend(adapter.state)..seedDemo();
/// backend.fiscalMode = FakeFiscalMode.pending;   // keyingi sotuv cheki "kutilmoqda"
/// ```
class FakeCashierBackend {
  FakeCashierBackend(this.state) {
    state.extraRoutes = handle;
  }

  final FakePosState state;

  /// Keyingi cheklar qanday chiqadi.
  FakeFiscalMode fiscalMode = FakeFiscalMode.issued;

  /// `fiscal-retry` necha marta yana "kutilmoqda" qoldiradi.
  int retryFailures = 0;

  /// Cheklar `non_fiscal` (sandbox drayver).
  bool nonFiscal = true;

  /// Buyurtma uuid → schyotlar (bekor qilinganlari ham).
  final Map<String, List<Map<String, Object?>>> bills =
      <String, List<Map<String, Object?>>>{};
  final List<Map<String, Object?>> refunds = <Map<String, Object?>>[];

  /// Chek uuid → `GET /pos/fiscal-receipts/{uuid}` javobi (P3-26; `fiscal.receipt` ham shu).
  final Map<String, Map<String, Object?>> receipts =
      <String, Map<String, Object?>>{};

  /// Ochiq smenaning kassa harakatlari (ochilish va sotuvsiz).
  final List<Map<String, Object?>> movements = <Map<String, Object?>>[];

  /// Yopilgan smenalar (uuid → smena, `summary` bilan).
  final Map<String, Map<String, Object?>> closedShifts =
      <String, Map<String, Object?>>{};
  int zNumber = 0;

  final Map<String, Map<String, Object?>> bookings =
      <String, Map<String, Object?>>{};
  final Map<String, Map<String, Object?>> accounts =
      <String, Map<String, Object?>>{};
  final Map<String, List<Map<String, Object?>>> entries =
      <String, List<Map<String, Object?>>>{};

  /// Onlayn buyurtmalar (`/staff/orders`).
  final List<Map<String, Object?>> onlineOrders = <Map<String, Object?>>[];

  /// P3-38: do'kon Telegram botiga ulangan mijoz telefonlari (`+998...`): tasdiq Telegram'ga ketadi.
  final Set<String> telegramPhones = <String>{};

  /// P3-38: xona puli kun turi bo'yicha (`weekday`, `weekend`, `holiday`; soxta tarif — faqat `room` turidagi joylar).
  final Map<String, String> roomFees = <String, String>{
    'weekday': '300000.00',
    'weekend': '300000.00',
    'holiday': '300000.00',
  };

  /// P6-15: terminaldan hisobdan chiqarilgan partiya porsiyalari (`shift_uuid`, `product_id`, `quantity`, `reason`).
  final List<Map<String, Object?>> batchWriteOffs = <Map<String, Object?>>[];

  /// P6-16: smart-kassada provayderning o'z karta jami (`source: provider`); `null` — Rizqim'dagi karta pulning o'zi.
  int? providerCardTotal;

  /// P6-16: filialning farq chegarasi (so'm). Server uni kassaga yubormaydi (review P6 S#5): faqat yakuniy hisobotda.
  int bankThreshold = 0;

  final Map<String, Map<String, Object?>> _writeOffByKey =
      <String, Map<String, Object?>>{};
  int _writeOffSeq = 0;

  /// P3-38: yuborilgan tasdiqlar (`booking_uuid`, `channel`, `text`, `auto`).
  final List<Map<String, Object?>> sentConfirmations = <Map<String, Object?>>[];

  final Map<String, Map<String, Object?>> _confirmationByKey =
      <String, Map<String, Object?>>{};
  final Map<String, Map<String, Object?>> _depositByKey =
      <String, Map<String, Object?>>{};
  final Map<String, Map<String, Object?>> _repayByKey =
      <String, Map<String, Object?>>{};
  int _seq = 0;
  int _bookingItemSeq = 500;

  // --- yo'llar -------------------------------------------------------------------------------------------------------

  FakeResponse? handle(FakeRequest r) {
    final String p = r.path;
    final String m = r.method;
    RegExpMatch? x;

    // Navbat: ochiq buyurtmalar (fiskal kutmoqda va qabul qilingan onlayn buyurtmalar bilan); P3-26: `incoming`
    // (qabul kutayotgan onlayn) va `closed` (shu ish kunida yopilgan POS buyurtmalari)
    if (m == 'GET' && p == '/v1/pos/orders') return _queue(r);
    if (m == 'POST' &&
        (x = _match(r'^/v1/pos/orders/([^/]+)/(accept|reject)$', p)) != null) {
      return _onlineDecision(x!.group(1)!, x.group(2)!, r);
    }
    if (m == 'GET' && p == '/v1/pos/shifts/current') return _current();
    if (m == 'POST' &&
        (x = _match(r'^/v1/owner/batches/(\d+)/write-off$', p)) != null) {
      return _writeOff(int.parse(x!.group(1)!), r);
    }

    if ((x = _match(r'^/v1/pos/orders/([^/]+)/bills$', p)) != null) {
      final Map<String, Object?>? order = state.orders[x!.group(1)];
      if (order == null) return null;
      if (m == 'GET') return _ok(_billing(order, all: r.query['all'] == '1'));
      if (m == 'POST') return _createBills(order, r);
    }
    if (m == 'POST' &&
        (x = _match(r'^/v1/pos/orders/([^/]+)/close$', p)) != null) {
      final Map<String, Object?>? order = state.orders[x!.group(1)];
      if (order == null) return null;
      return _closeOrder(order);
    }
    if (m == 'POST' &&
        (x = _match(
              r'^/v1/pos/bills/([^/]+)/(print|payments|fiscal-retry)$',
              p,
            )) !=
            null) {
      final (Map<String, Object?>, Map<String, Object?>)? found = _bill(
        x!.group(1)!,
      );
      if (found == null) return _fail(404, null, 'Schyot topilmadi.');
      return switch (x.group(2)) {
        'print' => _print(found.$1, found.$2, r),
        'payments' => _pay(found.$1, found.$2, r),
        _ => _fiscalRetry(found.$1, found.$2),
      };
    }
    if (m == 'POST' && p == '/v1/pos/refunds') return _refund(r);
    if (m == 'POST' &&
        (x = _match(
              r'^/v1/pos/orders/([^/]+)/(cancel|merge|discount|items/(\d+)/void|charges/([a-z_]+)/waive)$',
              p,
            )) !=
            null) {
      final Map<String, Object?>? order = state.orders[x!.group(1)];
      if (order != null) {
        if (x.group(2) == 'cancel' && order['status'] == 'delivered') {
          return _cancelRefunded(order, r);
        }
        final FakeResponse? refused = _guardOrderChange(order, x, r);
        if (refused != null) return refused;
      }
    }
    if (m == 'GET' &&
        (x = _match(r'^/v1/pos/fiscal-receipts/([^/]+)$', p)) != null) {
      final List<String> perms = state.permissionsOf(r.member);
      if (!perms.contains('pos.take_payment')) {
        return _fail(403, null, "Bu amal uchun ruxsat yo'q.");
      }
      final Map<String, Object?>? receipt = receipts[x!.group(1)];
      return receipt == null
          ? _fail(404, null, 'Chek topilmadi.')
          : _ok(receipt);
    }

    // Smena
    if ((x = _match(r'^/v1/pos/shifts/([^/]+)(?:/(.+))?$', p)) != null &&
        x!.group(1) != 'current') {
      return _shiftAction(m, x.group(1)!, x.group(2), r);
    }

    // P4-21: a new booking, a new deposit and a new nasiya account need the plan's feature (403 plan_feature_missing);
    // nothing else here asks the plan: payments, bills, receipts, shifts and refunds always go through
    final FakeResponse? planRefusal = _planRefusal(m, p);
    if (planRefusal != null) return planRefusal;
    // P6-11: and the branch's module (`pos.module:<key>`, 403 module_disabled)
    final FakeResponse? moduleRefusal = _moduleRefusal(m, p);
    if (moduleRefusal != null) return moduleRefusal;

    // Bron
    if (p.startsWith('/v1/pos/bookings')) return _bookings(r);

    // Nasiya
    if (p.startsWith('/v1/pos/customer-accounts')) return _accounts(r);

    // Onlayn buyurtmalar
    if (m == 'GET' && p == '/v1/staff/orders') return _staffOrders(r);
    if (m == 'POST' &&
        (x = _match(r'^/v1/staff/orders/([^/]+)/status$', p)) != null) {
      return _staffStatus(x!.group(1)!, r);
    }
    return null;
  }

  /// Filial formati o'chirgan modulning yangi yozuvi (real backend'dagi `pos.module:<key>` middleware). `format`
  /// bloki yo'q (eski server) — hech narsa rad etilmaydi.
  FakeResponse? _moduleRefusal(String m, String p) {
    if (m != 'POST') return null;
    final String? module = switch (p) {
      '/v1/pos/bookings' => 'bookings',
      '/v1/pos/customer-accounts' => 'customer_accounts',
      _ => null,
    };
    final Map<String, Object?>? format = state.format;
    if (module == null || format == null) return null;
    final Object? modules = format['modules'];
    if (modules is List && modules.contains(module)) return null;
    return FakeResponse(403, <String, Object?>{
      'success': false,
      'message': "Bu modul filialda o'chirilgan.",
      'code': 'module_disabled',
      'module': module,
      'data': <String, Object?>{
        'reason': 'module_disabled',
        'module': module,
        'profile': format['profile'],
      },
    });
  }

  /// Tarifda yo'q funksiyaning yangi yozuvi (real backend'dagi `plan.feature:<feature>` middleware).
  FakeResponse? _planRefusal(String m, String p) {
    if (m != 'POST') return null;
    final String? feature = switch (p) {
      '/v1/pos/bookings' => 'bookings',
      '/v1/pos/customer-accounts' => 'customer_accounts',
      _ when RegExp(r'^/v1/pos/bookings/[^/]+/deposits$').hasMatch(p) =>
        'deposits',
      _ => null,
    };
    if (feature == null) return null;
    final Map<String, Object?>? plan = state.entitlements;
    if (plan == null || plan['legacy'] == true) return null;
    final Object? features = plan['features'];
    if (features is! Map || features[feature] != false) return null;
    final Object? required = plan['required_plans'] is Map
        ? (plan['required_plans']! as Map)[feature]
        : null;
    final String? name = required is String && required.isNotEmpty
        ? required[0].toUpperCase() + required.substring(1)
        : null;
    return FakeResponse(403, <String, Object?>{
      'success': false,
      'message': name == null
          ? 'Bu imkoniyat tarifingizda yo\'q.'
          : 'Bu imkoniyat ($feature) $name tarifida bor.',
      'code': 'plan_feature_missing',
      'feature': feature,
      'required_plan': required,
      'data': <String, Object?>{
        'reason': 'plan_feature_missing',
        'feature': feature,
        'required_plan': required,
        'required_plan_name': name,
      },
    });
  }

  static RegExpMatch? _match(String pattern, String path) =>
      RegExp(pattern).firstMatch(path);

  // --- navbat ----------------------------------------------------------------------------------------------------------

  Map<String, Object?> _posRow(Map<String, Object?> o) => <String, Object?>{
    'uuid': o['uuid'],
    'short_code': o['short_code'],
    'status': o['status'],
    'channel': o['channel'],
    'type': o['type'],
    'branch_id': FakePosState.branchId,
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
    'payment_status': _allRefunded(o)
        ? 'refunded'
        : (o['status'] == 'delivered'
              ? 'paid'
              : (_paidOf(o) > 0 ? 'partial' : 'pending')),
    'bill_locked': o['bill_locked'],
    'bill_requested': o['bill_requested_at'] != null,
    'fiscal_pending': _fiscalPending(o),
    'booking_id': o['booking_id'],
    'business_date': _date(state.now()),
    'opened_at': o['opened_at'],
    'closed_at': o['closed_at'],
    'updated_at': o['updated_at'],
    'online': null,
  };

  Map<String, Object?> _onlineRow(Map<String, Object?> o) => <String, Object?>{
    'uuid': o['uuid'],
    'short_code': o['short_code'],
    'status': o['status'],
    'channel': o['channel'],
    'type': o['type'],
    'branch_id': FakePosState.branchId,
    'version': 1,
    'hall': null,
    'table': null,
    'hall_ref': null,
    'table_ref': null,
    'guests_count': null,
    'waiter': null,
    'items_count': (o['items']! as List<Object?>).length,
    'subtotal': o['subtotal'],
    'total_price': o['total_price'],
    'payment_status': o['payment_status'],
    'bill_locked': false,
    'bill_requested': false,
    'fiscal_pending': false,
    'booking_id': null,
    'business_date': _date(state.now()),
    'opened_at': o['created_at'],
    'closed_at': null,
    'updated_at': o['updated_at'],
    'online': o,
  };

  FakeResponse _queue(FakeRequest r) {
    final String scope = r.query['scope'] ?? 'open';
    final List<Map<String, Object?>> rows = <Map<String, Object?>>[];
    if (scope == 'incoming') {
      for (final Map<String, Object?> o in onlineOrders) {
        if (o['status'] == 'new') rows.add(_onlineRow(o));
      }
    } else if (scope == 'closed') {
      for (final Map<String, Object?> o in state.orders.values) {
        if (o['closed_at'] != null) rows.add(_posRow(o));
      }
      rows.sort(
        (Map<String, Object?> a, Map<String, Object?> b) =>
            '${b['closed_at']}'.compareTo('${a['closed_at']}'),
      );
      return _ok(rows);
    } else {
      for (final Map<String, Object?> o in state.orders.values) {
        if (o['status'] == 'delivered' || o['status'] == 'canceled') continue;
        rows.add(_posRow(o));
      }
      for (final Map<String, Object?> o in onlineOrders) {
        if (o['status'] != 'accepted' && o['status'] != 'preparing') continue;
        rows.add(_onlineRow(o));
      }
    }
    rows.sort(
      (Map<String, Object?> a, Map<String, Object?> b) =>
          '${a['opened_at']}'.compareTo('${b['opened_at']}'),
    );
    return _ok(rows);
  }

  /// `POST /pos/orders/{uuid}/accept|reject` (P3-26): yangi onlayn buyurtma. Rad etish — `orders.cancel` yoki
  /// menejer tasdig'i, `reason` majburiy.
  FakeResponse _onlineDecision(String uuid, String action, FakeRequest r) {
    Map<String, Object?>? order;
    for (final Map<String, Object?> o in onlineOrders) {
      if (o['uuid'] == uuid) order = o;
    }
    if (order == null) {
      return state.orders.containsKey(uuid)
          ? _fail(
              422,
              'not_online_order',
              'Bu kassa (POS) buyurtmasi: onlayn buyurtma emas.',
            )
          : _fail(404, null, 'Buyurtma topilmadi.');
    }
    final String status = action == 'accept' ? 'accepted' : 'rejected';
    if (action == 'reject') {
      if ('${r.data['reason'] ?? ''}'.trim().length < 2) {
        return _validation('reason', 'Rad etish sababini yozing.');
      }
      final FakeResponse? denied = state.authorize(
        r.member,
        'orders.cancel',
        r.data['approval_token'],
      );
      if (denied != null) return denied;
    } else if (!state.permissionsOf(r.member).contains('orders.manage')) {
      return _fail(403, null, "Bu amal uchun ruxsat yo'q.");
    }
    if (order['status'] == status) return _ok(_onlineRow(order));
    if (order['status'] != 'new') {
      return _fail(
        422,
        'order_not_new',
        "Bu buyurtma endi yangi emas: uni qabul qilib yoki rad etib bo'lmaydi.",
      );
    }
    order
      ..['status'] = status
      ..['status_label'] = status == 'accepted' ? 'Qabul qilindi' : 'Rad etildi'
      ..['cancel_reason'] = action == 'reject'
          ? (r.data['cancel_reason'] ?? 'other')
          : null
      ..['cancel_note'] = action == 'reject' ? r.data['reason'] : null
      ..['updated_at'] = _iso(state.now());
    state.emit('order.status_changed', <String, Object?>{
      'order': <String, Object?>{
        'uuid': uuid,
        'status': status,
        'store_id': FakePosState.storeId,
        'branch_id': FakePosState.branchId,
        'courier_id': null,
        'updated_at': order['updated_at'],
      },
    });
    return _ok(
      _onlineRow(order),
      message: status == 'accepted'
          ? 'Buyurtma qabul qilindi.'
          : 'Buyurtma rad etildi.',
    );
  }

  // --- schyot ----------------------------------------------------------------------------------------------------------

  static List<Map<String, Object?>> _items(Map<String, Object?> o) =>
      (o['items']! as List<Object?>).cast<Map<String, Object?>>();

  List<Map<String, Object?>> _billsOf(Map<String, Object?> order) =>
      bills.putIfAbsent('${order['uuid']}', () => <Map<String, Object?>>[]);

  Iterable<Map<String, Object?>> _live(Map<String, Object?> order) =>
      _billsOf(order).where((Map<String, Object?> b) => b['status'] != 'void');

  /// Taken, as the server counts it (BillableOrder): paid, or a payment is on it. The server has no `paying` status:
  /// a bill being paid stays `open`/`printed` until its payments cover it and the sale receipt is issued.
  static bool _settled(Map<String, Object?> b) =>
      b['status'] != 'void' &&
      (b['status'] == 'paid' || _t(b['paid_amount']) > 0);

  /// Covered by its payments, the sale receipt not issued yet.
  static bool _awaitingReceipt(Map<String, Object?> b) =>
      b['status'] != 'paid' &&
      b['status'] != 'void' &&
      _t(b['paid_amount']) > 0 &&
      _t(b['due_amount']) == 0;

  int _paidOf(Map<String, Object?> order) =>
      _live(order)
          .fold(0, (int s, Map<String, Object?> b) => s + _t(b['paid_amount']));

  bool _fiscalPending(Map<String, Object?> order) =>
      _live(order).any(_awaitingReceipt);

  int _billedOf(Map<String, Object?> order) =>
      _live(order)
          .fold(0, (int s, Map<String, Object?> b) => s + _t(b['total']));

  Map<String, Object?> _orderState(Map<String, Object?> order) {
    final int total = _t(order['total_price']);
    final int billed = _billedOf(order);
    final int paid = _paidOf(order);
    final bool closed = order['status'] == 'delivered';
    return <String, Object?>{
      'uuid': order['uuid'],
      'status': order['status'],
      'payment_status': _allRefunded(order)
          ? 'refunded'
          : (closed ? 'paid' : (paid > 0 ? 'partial' : 'pending')),
      'version': order['version'],
      'queue_number': null,
      'business_date': _date(state.now()),
      'bill_locked_at': order['bill_locked'] == true
          ? order['updated_at']
          : null,
      'closed_at': order['closed_at'],
      'total_price': _m(total),
      'billed_amount': _m(billed),
      'paid_amount': _m(paid),
      'unbilled_amount': _m(math.max(0, total - billed)),
      'fiscal_pending': _fiscalPending(order),
      'booking_uuid': _bookingOf(order)?['uuid'],
      'deposit_available': _bookingOf(order)?['deposit_available'] ?? '0.00',
      'bills_out_of_date':
          _live(order).isNotEmpty &&
          order['bills_version'] != null &&
          order['bills_version'] != order['version'] &&
          _live(order).any((Map<String, Object?> b) => !_settled(b)),
    };
  }

  /// Buyurtma ochilgan bron (`order_uuid` bo'yicha).
  Map<String, Object?>? _bookingOf(Map<String, Object?> order) {
    for (final Map<String, Object?> b in bookings.values) {
      if (b['order_uuid'] == order['uuid']) return b;
    }
    return null;
  }

  Map<String, Object?> _billing(
    Map<String, Object?> order, {
    bool all = false,
  }) => <String, Object?>{
    'order': _orderState(order),
    'bills': <Object?>[
      for (final Map<String, Object?> b in all ? _billsOf(order) : _live(order))
        b,
    ],
    'refunds': <Object?>[
      for (final Map<String, Object?> r in refunds)
        if (r['order_uuid'] == order['uuid']) r,
    ],
  };

  (Map<String, Object?>, Map<String, Object?>)? _bill(String uuid) {
    for (final MapEntry<String, List<Map<String, Object?>>> e
        in bills.entries) {
      for (final Map<String, Object?> b in e.value) {
        if (b['uuid'] == uuid) return (b, state.orders[e.key]!);
      }
    }
    return null;
  }

  /// Qatorning hali schyotga tushmagan miqdori (milli).
  Map<int, int> _remaining(Map<String, Object?> order) {
    final Map<int, int> left = <int, int>{
      for (final Map<String, Object?> i in _items(order))
        if (i['kitchen_status'] != 'void')
          i['id']! as int: _milli(i['quantity']),
    };
    for (final Map<String, Object?> b in _live(order).where(_settled)) {
      for (final Map<String, Object?> i
          in (b['items']! as List<Object?>).cast<Map<String, Object?>>()) {
        final int id = i['order_item_id']! as int;
        left[id] = (left[id] ?? 0) - _milli(i['quantity']);
      }
    }
    left.removeWhere((int _, int q) => q <= 0);
    return left;
  }

  FakeResponse _createBills(Map<String, Object?> order, FakeRequest r) {
    final String mode = '${r.data['mode'] ?? 'single'}';
    final FakeResponse? denied = state.authorize(
      r.member,
      mode == 'single' &&
              state.permissionsOf(r.member).contains('orders.manage')
          ? 'orders.manage'
          : 'pos.take_payment',
      r.data['approval_token'],
    );
    if (denied != null) return denied;
    if (order['status'] == 'delivered' || order['status'] == 'canceled') {
      return _fail(422, 'order_closed', 'Buyurtma yopilgan.');
    }
    if ((r.data['version'] as num?)?.toInt() != order['version']) {
      return FakeResponse(409, <String, Object?>{
        'success': false,
        'message': "Buyurtma boshqa qurilmada o'zgartirildi. Uni qayta yuklang va amalni takrorlang.",
        'data': <String, Object?>{
          'reason': 'version_conflict',
          'version': order['version'],
        },
      });
    }
    final Map<int, int> left = _remaining(order);
    if (left.isEmpty) {
      return _fail(422, 'nothing_to_bill', "Schyotga tushadigan taom qolmadi.");
    }
    // Ochiq va chiqarilgan schyotlar qayta tuziladi
    for (final Map<String, Object?> b in _billsOf(order)) {
      if (!_settled(b) && b['status'] != 'void') b['status'] = 'void';
    }
    final List<Map<int, int>> parts;
    final List<int?> guests;
    switch (mode) {
      case 'equal':
        final int n = (r.data['parts'] as num?)?.toInt() ?? 2;
        if (n < 2 || n > 30) {
          return _fail(
            422,
            'invalid_parts',
            "Ulushlar soni 2 dan 30 gacha bo'ladi.",
          );
        }
        parts = <Map<int, int>>[
          for (var k = 0; k < n; k++)
            <int, int>{
              for (final MapEntry<int, int> e in left.entries)
                e.key:
                    e.value ~/ n + (k == 0 ? e.value - (e.value ~/ n) * n : 0),
            },
        ];
        guests = List<int?>.filled(n, null);
      case 'items':
        final List<Object?> raw =
            (r.data['bills'] as List<Object?>?) ?? <Object?>[];
        parts = <Map<int, int>>[];
        guests = <int?>[];
        final Map<int, int> covered = <int, int>{};
        for (final Object? b in raw) {
          final Map<String, Object?> bill = (b! as Map<Object?, Object?>)
              .cast<String, Object?>();
          final Map<int, int> part = <int, int>{};
          for (final Object? i
              in (bill['items'] as List<Object?>?) ?? <Object?>[]) {
            final Map<String, Object?> line = (i! as Map<Object?, Object?>)
                .cast<String, Object?>();
            final int id = (line['order_item_id']! as num).toInt();
            final int q = _milli(line['quantity']);
            part[id] = (part[id] ?? 0) + q;
            covered[id] = (covered[id] ?? 0) + q;
          }
          parts.add(part);
          guests.add((bill['guests'] as num?)?.toInt());
        }
        for (final MapEntry<int, int> e in left.entries) {
          if (covered[e.key] != e.value) {
            return _fail(
              422,
              'items_not_covered',
              "Hamma taom schyotlarga to'liq taqsimlanishi kerak.",
            );
          }
        }
      default:
        parts = <Map<int, int>>[left];
        guests = <int?>[order['guests_count'] as int?];
    }
    _build(order, parts, guests, mode);
    order['bills_version'] = order['version'];
    _billEvent(order, null, 'created');
    return _ok(_billing(order), status: 201, message: 'Schyot tuzildi.');
  }

  void _build(
    Map<String, Object?> order,
    List<Map<int, int>> parts,
    List<int?> guests,
    String mode,
  ) {
    final Map<int, Map<String, Object?>> byId = <int, Map<String, Object?>>{
      for (final Map<String, Object?> i in _items(order)) i['id']! as int: i,
    };
    int amountOf(int id, int q) {
      final Map<String, Object?> i = byId[id]!;
      final int full = _milli(i['quantity']);
      return full == 0 ? 0 : (_t(i['total_price']) * q / full).round();
    }

    // Xizmatlar qoldig'i (to'langan schyotlardagisidan tashqari)
    int chargeLeft(String key) =>
        _t(order[key]) -
        _live(order)
            .where(_settled)
            .fold(0, (int s, Map<String, Object?> b) => s + _t(b[key]));
    const List<String> chargeKeys = <String>[
      'room_fee_amount',
      'service_charge_amount',
      'place_fee_amount',
      'min_spend_topup_amount',
      'discount',
    ];
    final Map<String, int> charges = <String, int>{
      for (final String k in chargeKeys) k: chargeLeft(k),
    };
    final List<int> subtotals = <int>[
      for (final Map<int, int> part in parts)
        part.entries.fold(
          0,
          (int s, MapEntry<int, int> e) => s + amountOf(e.key, e.value),
        ),
    ];
    final int allSub = subtotals.fold(0, (int a, int b) => a + b);
    final Map<String, int> given = <String, int>{
      for (final String k in chargeKeys) k: 0,
    };
    final int start = _billsOf(order).length;
    for (var k = 0; k < parts.length; k++) {
      final bool last = k == parts.length - 1;
      final Map<String, int> share = <String, int>{
        for (final String key in chargeKeys)
          key: last
              ? charges[key]! - given[key]!
              : (allSub == 0
                    ? charges[key]! ~/ parts.length
                    : (charges[key]! * subtotals[k] / allSub).round()),
      };
      share.forEach((String key, int v) => given[key] = given[key]! + v);
      final int total =
          subtotals[k] +
          share['room_fee_amount']! +
          share['service_charge_amount']! +
          share['place_fee_amount']! +
          share['min_spend_topup_amount']! -
          share['discount']!;
      final int? g = guests[k];
      _billsOf(order).add(<String, Object?>{
        'uuid': _uuid('b'),
        'order_uuid': order['uuid'],
        'number': start + k + 1,
        'status': 'open',
        'split_mode': mode,
        'subtotal': _m(subtotals[k]),
        'discount': _m(share['discount']!),
        'room_fee_amount': _m(share['room_fee_amount']!),
        'min_spend_topup_amount': _m(share['min_spend_topup_amount']!),
        'service_charge_amount': _m(share['service_charge_amount']!),
        'place_fee_amount': _m(share['place_fee_amount']!),
        'total': _m(total),
        'paid_amount': '0.00',
        'due_amount': _m(total),
        'guests_share': g != null && g > 1 ? g : null,
        'per_guest_amount': g != null && g > 1
            ? _m(((total / g) / 100).ceil() * 100)
            : null,
        'printed_count': 0,
        'printed_at': null,
        'paid_at': null,
        'fiscal': <String, Object?>{
          'status': null,
          'receipt_uuid': null,
          'error': null,
        },
        'items': _billLines(
          parts[k],
          byId,
          amountOf,
          share['discount']!,
          subtotals[k],
        ),
        'payments': <Object?>[],
        'created_at': _iso(state.now()),
      });
    }
  }

  /// Schyot qatorlari; schyotning chegirmasi qatorlarga summasi bo'yicha taqsimlanadi (backend `BillSplitter`
  /// kabi: qatorning `discount_amount`, oxirgisi qoldiqni oladi).
  static List<Object?> _billLines(
    Map<int, int> part,
    Map<int, Map<String, Object?>> byId,
    int Function(int id, int q) amountOf,
    int discount,
    int subtotal,
  ) {
    final List<MapEntry<int, int>> lines = <MapEntry<int, int>>[
      for (final MapEntry<int, int> e in part.entries)
        if (e.value > 0) e,
    ];
    var given = 0;
    return <Object?>[
      for (var n = 0; n < lines.length; n++)
        () {
          final MapEntry<int, int> e = lines[n];
          final int amount = amountOf(e.key, e.value);
          final int share = n == lines.length - 1
              ? discount - given
              : (subtotal == 0 ? 0 : (discount * amount / subtotal).round());
          given += share;
          return <String, Object?>{
            'order_item_id': e.key,
            'product_name': byId[e.key]!['product_name'],
            'variant_name': byId[e.key]!['variant_name'],
            'unit': byId[e.key]!['unit'],
            'quantity': e.value / 1000,
            'amount': _m(amount),
            'discount_amount': _m(share),
          };
        }(),
    ];
  }

  FakeResponse _print(
    Map<String, Object?> bill,
    Map<String, Object?> order,
    FakeRequest r,
  ) {
    final bool copy = (bill['printed_count']! as int) > 0;
    final FakeResponse? denied = state.authorize(
      r.member,
      copy ? 'pos.reprint_bill' : 'pos.take_payment',
      r.data['approval_token'],
    );
    if (denied != null) return denied;
    if (_outOfDate(order, bill)) return _orderChanged();
    bill
      ..['printed_count'] = (bill['printed_count']! as int) + 1
      ..['printed_at'] = _iso(state.now());
    if (bill['status'] == 'open') bill['status'] = 'printed';
    order['bill_locked'] = true;
    _billEvent(order, bill, 'printed');
    return _ok(<String, Object?>{
      'bill': bill,
      'order': _orderState(order),
      'copy': copy,
    }, message: copy ? 'Nusxa chiqarildi.' : 'Schyot chiqarildi.');
  }

  bool _outOfDate(Map<String, Object?> order, Map<String, Object?> bill) =>
      !_settled(bill) &&
      order['bills_version'] != null &&
      order['bills_version'] != order['version'];

  FakeResponse _orderChanged() => _fail(
    409,
    'order_changed',
    "Schyotdan keyin buyurtma o'zgardi: schyotni qayta tuzing.",
  );

  FakeResponse _pay(
    Map<String, Object?> bill,
    Map<String, Object?> order,
    FakeRequest r,
  ) {
    final FakeResponse? denied = state.authorize(
      r.member,
      'pos.take_payment',
      null,
    );
    if (denied != null) return denied;
    if (state.shift == null) {
      return _fail(
        409,
        'shift_not_open',
        "Smena ochilmagan: to'lov qabul qilish uchun avval smenani oching.",
      );
    }
    if (_outOfDate(order, bill)) return _orderChanged();
    final List<Map<String, Object?>> tenders = <Map<String, Object?>>[
      for (final Object? t
          in (r.data['tenders'] as List<Object?>?) ?? <Object?>[])
        (t! as Map<Object?, Object?>).cast<String, Object?>(),
    ];
    if (tenders.isEmpty) {
      return _fail(422, 'tenders_required', "To'lov turi tanlanmagan.");
    }
    final int due = _t(bill['due_amount']);
    final int sum = tenders.fold(
      0,
      (int s, Map<String, Object?> t) => s + _t(t['amount']),
    );
    if (sum > due) {
      return FakeResponse(422, <String, Object?>{
        'success': false,
        'message': "To'lov schyot qoldig'idan ko'p: qoldiq ${_m(due)} so'm.",
        'data': <String, Object?>{'reason': 'overpayment', 'due': _m(due)},
      });
    }
    Map<String, Object?>? account;
    Map<String, Object?>? booking;
    for (final Map<String, Object?> t in tenders) {
      switch (t['tender']) {
        case 'card':
          if ('${t['card_type'] ?? ''}'.isEmpty) {
            return _fail(422, 'card_type_required', 'Karta turini tanlang.');
          }
          if ('${t['transaction_id'] ?? ''}'.trim().isEmpty) {
            return _fail(
              422,
              'transaction_id_required',
              'Terminal tranzaksiya raqamini kiriting.',
            );
          }
          // P6-16: the last four digits are optional, but not four digits is refused
          final Object? last4 = t['card_last4'];
          if (last4 != null &&
              '$last4'.isNotEmpty &&
              !RegExp(r'^\d{4}$').hasMatch('$last4')) {
            return _fail(
              422,
              'card_last4_invalid',
              "Kartaning oxirgi 4 raqamini kiriting (yoki bo'sh qoldiring).",
            );
          }
        case 'prepayment':
          booking = _bookingOfOrder(order);
          final int available = booking == null
              ? 0
              : _t(booking['deposit_available']);
          if (_t(t['amount']) > available) {
            return FakeResponse(422, <String, Object?>{
              'success': false,
              'message': "Avans yetarli emas: mavjud ${_m(available)} so'm.",
              'data': <String, Object?>{
                'reason': 'advance_insufficient',
                'available': _m(available),
              },
            });
          }
        case 'account':
          final FakeResponse? needs = state.authorize(
            r.member,
            'customer_accounts.manage',
            r.data['approval_token'],
          );
          if (needs != null) return needs;
          account = accounts['${t['account_uuid']}'];
          if (account == null) {
            return _fail(422, 'account_not_found', 'Nasiya hisobi topilmadi.');
          }
          if (account['is_blocked'] == true) {
            return _fail(
              422,
              'account_blocked',
              "Bu hisobga yangi nasiya to'xtatilgan.",
            );
          }
          final Object? limit = account['credit_limit'];
          if (limit != null &&
              _t(account['balance']) + _t(t['amount']) > _t(limit)) {
            return _fail(
              422,
              'credit_limit_exceeded',
              'Nasiya limiti yetmaydi.',
            );
          }
      }
    }
    final List<Object?> payments = bill['payments']! as List<Object?>;
    for (final Map<String, Object?> t in tenders) {
      final int amount = _t(t['amount']);
      final int? tendered = t['tendered_amount'] == null
          ? null
          : _t(t['tendered_amount']);
      payments.add(<String, Object?>{
        'uuid': _uuid('p'),
        'tender': t['tender'],
        'amount': _m(amount),
        'tendered_amount': tendered == null ? null : _m(tendered),
        'change_amount': tendered == null
            ? null
            : _m(math.max(0, tendered - amount)),
        'card_type': t['card_type'],
        'transaction_id': t['transaction_id'],
        'card_last4': t['card_last4'],
        'reference': t['reference'],
        'status': 'paid',
        'paid_at': _iso(state.now()),
        'shift_uuid': state.shift!['uuid'],
        'refunded_amount': '0.00',
        if (t['tender'] == 'account') 'account_uuid': t['account_uuid'],
      });
      if (t['tender'] == 'account' && account != null) {
        _entry(
          account,
          'charge',
          amount,
          billUuid: '${bill['uuid']}',
          user: r.member,
        );
      }
      if (t['tender'] == 'prepayment' && booking != null) {
        _applyDeposit(booking, amount);
      }
    }
    bill
      ..['paid_amount'] = _m(_t(bill['paid_amount']) + sum)
      ..['due_amount'] = _m(due - sum);
    if (due - sum == 0) _issueSale(bill, order);
    _billEvent(order, bill, 'paid');
    return _ok(<String, Object?>{
      'bill': bill,
      'order': _orderState(order),
      'copy': false,
    }, message: "To'landi.");
  }

  void _issueSale(Map<String, Object?> bill, Map<String, Object?> order) {
    final String uuid = _uuid('r');
    final bool issued = fiscalMode == FakeFiscalMode.issued;
    bill['fiscal'] = <String, Object?>{
      'status': issued ? 'issued' : 'pending',
      'receipt_uuid': uuid,
      'error': issued ? null : "Provayder javob bermadi.",
    };
    receipts[uuid] = _receipt(uuid, 'sale', bill, issued);
    // P3-26: the bill's `fiscal.receipt` is the receipt itself (updated when it is issued)
    (bill['fiscal']! as Map<String, Object?>)['receipt'] = receipts[uuid];
    if (issued) _finishBill(bill, order);
  }

  void _finishBill(Map<String, Object?> bill, Map<String, Object?> order) {
    bill
      ..['status'] = 'paid'
      ..['paid_at'] = _iso(state.now());
    (receipts['${(bill['fiscal']! as Map<String, Object?>)['receipt_uuid']}'] ??
            <String, Object?>{})
        .addAll(<String, Object?>{
          'status': 'issued',
          'fiscal_sign': '${100000000 + _seq * 7919}',
          'qr_url': 'https://ofd.soliq.uz/check?t=RZ$_seq&r=${bill['number']}',
          'issued_at': _iso(state.now()),
        });
    final bool all =
        _live(order).every((Map<String, Object?> b) => b['status'] == 'paid') &&
        _t(order['total_price']) - _billedOf(order) <= 0;
    if (all) _close(order);
  }

  void _close(Map<String, Object?> order) {
    order
      ..['status'] = 'delivered'
      ..['closed_at'] = _iso(state.now())
      ..['version'] = (order['version']! as int) + 1
      ..['updated_at'] = _iso(state.now());
    state.emit('order.status_changed', <String, Object?>{
      'order': <String, Object?>{
        'uuid': order['uuid'],
        'status': 'delivered',
        'store_id': FakePosState.storeId,
        'branch_id': FakePosState.branchId,
        'courier_id': null,
        'updated_at': order['updated_at'],
      },
    });
    for (final Map<String, Object?> b in bookings.values) {
      if (b['order_uuid'] == order['uuid']) {
        b['status'] = 'completed';
        _touchBooking(b);
      }
    }
  }

  Map<String, Object?> _receipt(
    String uuid,
    String type,
    Map<String, Object?> bill,
    bool issued,
  ) {
    final List<Object?> lines = <Object?>[
      for (final Map<String, Object?> i
          in (bill['items']! as List<Object?>).cast<Map<String, Object?>>())
        <String, Object?>{
          'name':
              '${i['product_name']}${i['variant_name'] == null ? '' : ' (${i['variant_name']})'}',
          'mxik_code': '10202001001000000',
          'package_code': null,
          'unit': i['unit'] == 'kg' ? 'kg' : 'dona',
          'quantity': '${i['quantity']}',
          // The receipt line is the share after its discount (BillFiscalizer)
          'unit_price': _m(
            _milli(i['quantity']) == 0
                ? 0
                : (_net(i) * 1000 / _milli(i['quantity'])).round(),
          ),
          'amount': _m(_net(i)),
          'vat_rate': '12',
          'vat_amount': _m((_net(i) * 12 / 112).round()),
          'no_vat': false,
        },
      for (final (String key, String name) in <(String, String)>[
        ('room_fee_amount', 'Xona puli'),
        ('service_charge_amount', 'Xizmat haqi'),
        ('place_fee_amount', 'Joy puli'),
        ('min_spend_topup_amount', "Minimal summaga qo'shimcha"),
      ])
        if (_t(bill[key]) > 0)
          <String, Object?>{
            'name': name,
            'mxik_code': '10202002001000000',
            'package_code': null,
            'unit': 'xizmat',
            'quantity': '1',
            'unit_price': bill[key],
            'amount': bill[key],
            'vat_rate': '12',
            'vat_amount': _m((_t(bill[key]) * 12 / 112).round()),
            'no_vat': false,
          },
    ];
    return <String, Object?>{
      'uuid': uuid,
      'type': type,
      'status': issued ? 'issued' : 'pending',
      'payment_form': 'cash',
      'driver': 'sandbox',
      'provider': 'sandbox',
      'non_fiscal': nonFiscal,
      'tax_regime': 'vat',
      'total': bill['total'],
      'vat_total': '0.00',
      'fiscal_sign': issued ? '${100000000 + _seq * 7919}' : null,
      'receipt_number': '${1000 + _seq}',
      'qr_url': issued ? 'https://ofd.soliq.uz/check?t=RZ$_seq' : null,
      'terminal_id': 'EP000000000123',
      'error': null,
      'issued_at': issued ? _iso(state.now()) : null,
      'created_at': _iso(state.now()),
      'lines': lines,
      'payments': <Object?>[
        for (final Map<String, Object?> p
            in (bill['payments']! as List<Object?>)
                .cast<Map<String, Object?>>())
          <String, Object?>{
            'form': p['tender'] == 'card' || p['tender'] == 'qr'
                ? p['tender']
                : 'cash',
            'amount': p['amount'],
            'prepayment': p['tender'] == 'prepayment',
            'credit': p['tender'] == 'account',
            'card_type': p['card_type'],
            'transaction_id': p['transaction_id'],
            'card_last4': p['card_last4'],
          },
      ],
    };
  }

  static int _net(Map<String, Object?> line) =>
      _t(line['amount']) - _t(line['discount_amount']);

  /// Bitta qatorli chek (avans, kredit).
  Map<String, Object?> _simpleReceipt(
    String uuid,
    String type,
    int amount,
    String name,
    String tender, {
    required bool prepayment,
  }) => <String, Object?>{
    'uuid': uuid,
    'type': type,
    'status': 'issued',
    'payment_form': tender == 'card' || tender == 'qr' ? tender : 'cash',
    'non_fiscal': nonFiscal,
    'total': _m(amount),
    'vat_total': _m((amount * 12 / 112).round()),
    // Avans va kredit chekida fiskal belgi va QR yo'q (VM 943)
    'fiscal_sign': null,
    'receipt_number': '${2000 + _seq}',
    'qr_url': null,
    'terminal_id': 'EP000000000123',
    'error': null,
    'issued_at': _iso(state.now()),
    'created_at': _iso(state.now()),
    'original_receipt_uuid': null,
    'lines': <Object?>[
      <String, Object?>{
        'name': name,
        'mxik_code': '10202002001000003',
        'package_code': null,
        'unit': 'xizmat',
        'quantity': '1',
        'unit_price': _m(amount),
        'amount': _m(amount),
        'vat_rate': '12',
        'vat_amount': _m((amount * 12 / 112).round()),
        'no_vat': false,
      },
    ],
    'payments': <Object?>[
      <String, Object?>{
        'form': tender == 'card' || tender == 'qr' ? tender : 'cash',
        'amount': _m(amount),
        'prepayment': prepayment,
        'credit': false,
        'card_type': null,
        'transaction_id': null,
      },
    ],
  };

  /// `fiscal` obyekti (P3-26): holat va chekning o'zi.
  Map<String, Object?> _fiscalOf(String? receiptUuid) {
    final Map<String, Object?>? receipt = receipts[receiptUuid];
    return <String, Object?>{
      'status': receipt?['status'],
      'receipt_uuid': receiptUuid,
      'error': null,
      'receipt': receipt,
    };
  }

  FakeResponse _fiscalRetry(
    Map<String, Object?> bill,
    Map<String, Object?> order,
  ) {
    final Map<String, Object?> fiscal = bill['fiscal']! as Map<String, Object?>;
    if (fiscal['status'] == 'issued') {
      return _ok(<String, Object?>{
        'bill': bill,
        'order': _orderState(order),
        'copy': false,
      });
    }
    if (fiscal['status'] == null) {
      return _fail(
        422,
        'nothing_to_retry',
        "Bu schyotda kutilayotgan chek yo'q.",
      );
    }
    if (retryFailures > 0) {
      retryFailures--;
      fiscal['status'] = 'pending';
    } else {
      fiscal
        ..['status'] = 'issued'
        ..['error'] = null;
      _finishBill(bill, order);
    }
    _billEvent(order, bill, 'fiscal');
    return _ok(
      <String, Object?>{
        'bill': bill,
        'order': _orderState(order),
        'copy': false,
      },
      message: fiscal['status'] == 'issued'
          ? 'Chek chiqdi.'
          : "Chek hali kutilmoqda.",
    );
  }

  /// Kutilayotgan hamma cheklarni chiqaradi (provayder javob berdi: `fiscal.receipt_updated`).
  void issuePending() {
    for (final MapEntry<String, List<Map<String, Object?>>> e
        in bills.entries) {
      for (final Map<String, Object?> b in e.value) {
        final Map<String, Object?> f = b['fiscal']! as Map<String, Object?>;
        if (_awaitingReceipt(b) &&
            f['status'] != null &&
            f['status'] != 'issued') {
          f['status'] = 'issued';
          _finishBill(b, state.orders[e.key]!);
          state.emit('fiscal.receipt_updated', <String, Object?>{
            'receipt': <String, Object?>{
              'uuid': f['receipt_uuid'],
              'type': 'sale',
              'status': 'issued',
              'store_id': FakePosState.storeId,
              'branch_id': FakePosState.branchId,
              'subject_type': 'order_bill',
              'subject_id': 1,
            },
          });
        }
      }
    }
  }

  FakeResponse _closeOrder(Map<String, Object?> order) {
    if (order['status'] == 'delivered') return _ok(_billing(order));
    final bool settled =
        _live(order).isNotEmpty &&
        _live(order).every((Map<String, Object?> b) => b['status'] == 'paid') &&
        _t(order['total_price']) - _billedOf(order) <= 0;
    if (!settled) {
      return _fail(409, 'order_not_settled', "Buyurtma to'liq to'lanmagan.");
    }
    _close(order);
    return _ok(_billing(order), message: 'Buyurtma yopildi.');
  }

  void _billEvent(
    Map<String, Object?> order,
    Map<String, Object?>? bill,
    String change,
  ) => state.emit('order.bill_changed', <String, Object?>{
    'order': <String, Object?>{
      'uuid': order['uuid'],
      'status': order['status'],
      'store_id': FakePosState.storeId,
      'branch_id': FakePosState.branchId,
      'courier_id': null,
      'updated_at': order['updated_at'],
    },
    'bill': bill == null
        ? null
        : <String, Object?>{
            'uuid': bill['uuid'],
            'status': bill['status'],
            'fiscal_status':
                (bill['fiscal']! as Map<String, Object?>)['status'],
          },
    'change': change,
  });

  FakeResponse _refund(FakeRequest r) {
    final FakeResponse? denied = state.authorize(
      r.member,
      'pos.refund',
      r.data['approval_token'],
    );
    if (denied != null) return denied;
    final String reason = '${r.data['reason'] ?? ''}'.trim();
    if (reason.isEmpty) {
      return _fail(422, 'reason_required', 'Qaytarish sababini yozing.');
    }
    for (final MapEntry<String, List<Map<String, Object?>>> e
        in bills.entries) {
      for (final Map<String, Object?> b in e.value) {
        for (final Map<String, Object?> p
            in (b['payments']! as List<Object?>).cast<Map<String, Object?>>()) {
          if (p['uuid'] != r.data['payment_uuid']) continue;
          // P3-35: deposit (prepayment) and nasiya (account) parts too, with no cash movement
          if (!<Object?>[
            'cash',
            'card',
            'qr',
            'prepayment',
            'account',
          ].contains(p['tender'])) {
            return _fail(
              422,
              'refund_tender_invalid',
              "Bu to'lovni kassada qaytarib bo'lmaydi.",
            );
          }
          if (p['shift_uuid'] != state.shift?['uuid']) {
            return _fail(
              409,
              'refund_outside_shift',
              "Faqat shu smenada olingan to'lov qaytariladi.",
            );
          }
          final int amount = _t(r.data['amount']);
          final int left = _t(p['amount']) - _t(p['refunded_amount']);
          if (amount <= 0 || amount > left) {
            return _fail(
              422,
              'refund_exceeds_payment',
              "Qaytarish to'lovdan ko'p bo'lmaydi.",
            );
          }
          if (p['tender'] == 'cash' &&
              amount >
                  _t(
                    (_report(state.shift!)['cash']!
                        as Map<String, Object?>)['expected'],
                  )) {
            return _fail(
              409,
              'not_enough_cash',
              "Kassada buncha naqd pul yo'q.",
            );
          }
          final Map<String, Object?> order = state.orders[e.key]!;
          if (p['tender'] == 'prepayment') {
            final FakeResponse? refused = _reverseDeposit(order, amount);
            if (refused != null) return refused;
          }
          final String refundUuid = _uuid('f');
          if (p['tender'] == 'account') {
            final FakeResponse? refused = _reverseCharge(
              p,
              b,
              amount,
              refundUuid,
              r.member,
            );
            if (refused != null) return refused;
          }
          p['refunded_amount'] = _m(_t(p['refunded_amount']) + amount);
          if (_t(p['refunded_amount']) >= _t(p['amount'])) {
            p['status'] = 'refunded';
          }
          final String receipt = _uuid('r');
          receipts[receipt] = <String, Object?>{
            ..._receipt(receipt, 'refund', b, true),
            'total': _m(amount),
            'original_receipt_uuid':
                (b['fiscal']! as Map<String, Object?>)['receipt_uuid'],
            'lines': <Object?>[
              <String, Object?>{
                'name': 'Qaytarish',
                'mxik_code': '10202001001000000',
                'unit': 'xizmat',
                'quantity': '1',
                'unit_price': _m(amount),
                'amount': _m(amount),
                'vat_rate': '12',
                'vat_amount': _m((amount * 12 / 112).round()),
              },
            ],
          };
          final Map<String, Object?> refund = <String, Object?>{
            'uuid': refundUuid,
            'order_uuid': e.key,
            'bill_uuid': b['uuid'],
            'payment_uuid': p['uuid'],
            'amount': _m(amount),
            'method': p['tender'],
            'reason': reason,
            'approved_by': null,
            'fiscal': <String, Object?>{
              'status': 'issued',
              'receipt_uuid': receipt,
              'error': null,
              'receipt': receipts[receipt],
            },
            'created_at': _iso(state.now()),
            'shift_uuid': state.shift?['uuid'],
          };
          refunds.add(refund);
          return _ok(refund, status: 201, message: 'Pul qaytarildi.');
        }
      }
    }
    return _fail(404, null, "To'lov topilmadi.");
  }

  /// P3-35: the order's every payment is refunded (`payment_status = refunded`; it may be cancelled then).
  bool _allRefunded(Map<String, Object?> order) {
    final List<Map<String, Object?>> pays = <Map<String, Object?>>[
      for (final Map<String, Object?> b in _live(order))
        ...(b['payments']! as List<Object?>).cast<Map<String, Object?>>(),
    ];
    return pays.isNotEmpty &&
        pays.every((Map<String, Object?> p) => p['status'] == 'refunded');
  }

  static List<Map<String, Object?>> _paymentsOf(Map<String, Object?> b) =>
      (b['payments']! as List<Object?>).cast<Map<String, Object?>>();

  /// Like the server (P3-35): an order with a payment still taken is not cancelled (409 `has_payments`); a change that
  /// takes away what a bill being paid carries (a line on it voided, its charge waived) — 409 `paid_bill_affected`;
  /// two orders of different bookings are not merged (422 `merge_bookings_conflict`). `null` — the usual answer.
  FakeResponse? _guardOrderChange(
    Map<String, Object?> order,
    RegExpMatch x,
    FakeRequest r,
  ) {
    final List<Map<String, Object?>> taken = <Map<String, Object?>>[
      for (final Map<String, Object?> b in _live(order))
        if (_paymentsOf(b).isNotEmpty) b,
    ];
    final String action = x.group(2)!;
    if (action == 'discount') {
      // P3-37: a closed order is the state's 422; a bill with money on it is not discounted afterwards
      if (order['status'] == 'delivered' || order['status'] == 'canceled') {
        return null;
      }
      if (taken.isEmpty) return null;
      return const FakeResponse(409, <String, Object?>{
        'success': false,
        'message': "Schyotga to'lov olingan: chegirma berish uchun avval to'lovni qaytaring.",
        'data': <String, Object?>{
          'reason': 'paid_bill_affected',
          'bills_reason': 'bills_out_of_date',
        },
      });
    }
    if (action == 'cancel') {
      final List<Map<String, Object?>> paid = <Map<String, Object?>>[
        for (final Map<String, Object?> b in taken)
          for (final Map<String, Object?> p in _paymentsOf(b))
            if (p['status'] == 'paid') p,
      ];
      if (paid.isEmpty) return null;
      return FakeResponse(409, <String, Object?>{
        'success': false,
        'message': "Buyurtmada to'lov bor: avval to'lovni qaytaring (qaytarish cheki bilan), keyin bekor qiling.",
        'data': <String, Object?>{
          'reason': 'has_payments',
          'paid_amount': _m(
            paid.fold(
              0,
              (int s, Map<String, Object?> p) => s + _t(p['amount']),
            ),
          ),
        },
      });
    }
    if (action == 'merge') {
      final Map<String, Object?>? source =
          state.orders['${r.data['source_uuid']}'];
      if (source == null) return null;
      final Map<String, Object?>? a = _bookingOf(order);
      final Map<String, Object?>? b = _bookingOf(source);
      if (a != null && b != null && a['uuid'] != b['uuid']) {
        return _fail(
          422,
          'merge_bookings_conflict',
          "Ikkala buyurtma ham boshqa-boshqa bronga tegishli: ularni birlashtirib bo'lmaydi.",
        );
      }
      return null;
    }
    final String? line = x.group(3);
    final String? charge = x.group(4);
    final bool affected = taken.any(
      (Map<String, Object?> b) => line != null
          ? (b['items']! as List<Object?>).cast<Map<String, Object?>>().any(
              (Map<String, Object?> i) => '${i['order_item_id']}' == line,
            )
          : _t(b['${charge}_amount']) > 0,
    );
    if (!affected) return null;
    return const FakeResponse(409, <String, Object?>{
      'success': false,
      'message': "Bu o'zgarish to'lovi olingan schyotdagi taom yoki xizmat haqiga tegadi: avval o'sha to'lovni qaytaring, keyin o'zgartiring.",
      'data': <String, Object?>{
        'reason': 'paid_bill_affected',
        'bills_reason': 'bills_out_of_date',
      },
    });
  }

  /// P3-37 (API contract 4): a CLOSED order whose every payment is refunded may be cancelled — the same permission
  /// (`orders.cancel` or a manager), status `canceled`, `closed_at` kept, no new receipt. Any other closed order stays
  /// 422 `order_closed`.
  FakeResponse _cancelRefunded(Map<String, Object?> order, FakeRequest r) {
    if (!_allRefunded(order)) {
      return _fail(
        422,
        'order_closed',
        "Buyurtma yopilgan yoki bekor qilingan: uni o'zgartirib bo'lmaydi.",
      );
    }
    final FakeResponse? denied = state.authorize(
      r.member,
      'orders.cancel',
      r.data['approval_token'],
    );
    if (denied != null) return denied;
    if ((r.data['version'] as num?)?.toInt() != order['version']) {
      return FakeResponse(409, <String, Object?>{
        'success': false,
        'message': "Buyurtma boshqa qurilmada o'zgartirildi. Uni qayta yuklang va amalni takrorlang.",
        'data': <String, Object?>{
          'reason': 'version_conflict',
          'version': order['version'],
        },
      });
    }
    order
      ..['status'] = 'canceled'
      ..['cancel_reason'] = r.data['reason']
      ..['version'] = (order['version']! as int) + 1
      ..['updated_at'] = _iso(state.now());
    state.emit('order.status_changed', <String, Object?>{
      'order': <String, Object?>{
        'uuid': order['uuid'],
        'status': 'canceled',
        'store_id': FakePosState.storeId,
        'branch_id': FakePosState.branchId,
        'courier_id': null,
        'updated_at': order['updated_at'],
      },
    });
    state.emit('order.items_changed', <String, Object?>{
      'order': <String, Object?>{
        'uuid': order['uuid'],
        'status': 'canceled',
        'store_id': FakePosState.storeId,
        'branch_id': FakePosState.branchId,
        'courier_id': null,
        'updated_at': order['updated_at'],
      },
      'version': order['version'],
      'table_id': (order['table'] as Map<String, Object?>?)?['id'],
      'change': 'cancelled',
      'item_ids': <int>[],
    });
    return _ok(order, message: 'Buyurtma bekor qilindi.');
  }

  /// P3-35 (`AdvanceLedger::reverse`): the bill's deposit part back to the booking's deposits, newest first; a deposit
  /// that holds money again is `taken`.
  FakeResponse? _reverseDeposit(Map<String, Object?> order, int amount) {
    final Map<String, Object?>? booking = _bookingOfOrder(order);
    final List<Map<String, Object?>> deposits = booking == null
        ? const <Map<String, Object?>>[]
        : _paymentsOf(booking).reversed.toList();
    final int applied = deposits.fold(
      0,
      (int s, Map<String, Object?> d) => s + _t(d['applied_amount']),
    );
    if (amount > applied) {
      return FakeResponse(409, <String, Object?>{
        'success': false,
        'message':
            "Bu schyotga avansdan ${_m(applied)} so'm ayirilgan: undan ko'pini qaytarib bo'lmaydi.",
        'data': <String, Object?>{
          'reason': 'reverse_exceeds_applied',
          'applied': _m(applied),
        },
      });
    }
    int left = amount;
    for (final Map<String, Object?> d in deposits) {
      final int part = math.min(left, _t(d['applied_amount']));
      if (part <= 0) continue;
      d
        ..['applied_amount'] = _m(_t(d['applied_amount']) - part)
        ..['remaining'] = _m(_t(d['remaining']) + part)
        ..['status'] = 'taken';
      left -= part;
      if (left == 0) break;
    }
    _recountDeposits(booking!);
    booking['version'] = ((booking['version'] as int?) ?? 0) + 1;
    return null;
  }

  /// P3-35 (`CustomerAccountLedger::reverse`): the bill's nasiya part off the customer's debt (an adjustment); never
  /// more than the bill was charged (409 `reverse_exceeds_charge`) nor than the debt left (409 `more_than_debt`).
  FakeResponse? _reverseCharge(
    Map<String, Object?> payment,
    Map<String, Object?> bill,
    int amount,
    String refundUuid,
    Map<String, Object?> member,
  ) {
    final Map<String, Object?>? account =
        accounts['${payment['account_uuid'] ?? ''}'];
    if (account == null) {
      return _fail(
        409,
        'account_unknown',
        "Nasiya to'lovining hisobi topilmadi: qarzni menejer qo'lda tuzatsin.",
      );
    }
    final List<Map<String, Object?>> list =
        entries['${account['uuid']}'] ?? const <Map<String, Object?>>[];
    final int charged = list
        .where(
          (Map<String, Object?> e) =>
              e['type'] == 'charge' && e['bill_uuid'] == bill['uuid'],
        )
        .fold(0, (int s, Map<String, Object?> e) => s + _t(e['amount']));
    final int reversed = list
        .where(
          (Map<String, Object?> e) =>
              e['type'] == 'adjustment' && e['bill_uuid'] == bill['uuid'],
        )
        .fold(0, (int s, Map<String, Object?> e) => s - _t(e['amount']));
    final int left = charged - reversed;
    if (amount > left) {
      return FakeResponse(409, <String, Object?>{
        'success': false,
        'message':
            "Bu schyot nasiyaga ${_m(left)} so'm yozilgan: undan ko'pini qaytarib bo'lmaydi.",
        'data': <String, Object?>{
          'reason': 'reverse_exceeds_charge',
          'left': _m(left),
        },
      });
    }
    if (amount > _t(account['balance'])) {
      return FakeResponse(409, <String, Object?>{
        'success': false,
        'message':
            "Mijoz qarzining bir qismini to'lagan: qolgan qarz ${account['balance']} so'm. Farqni menejer qo'lda hal qilsin.",
        'data': <String, Object?>{
          'reason': 'more_than_debt',
          'balance': account['balance'],
        },
      });
    }
    _entry(
      account,
      'adjustment',
      -amount,
      billUuid: '${bill['uuid']}',
      user: member,
      note: 'Kassada qaytarildi: schyotning nasiya qismi',
    );
    return null;
  }

  // --- smena -----------------------------------------------------------------------------------------------------------

  FakeResponse _current() => _ok(<String, Object?>{
    'shift': state.shift,
    'report': state.shift == null ? null : _report(state.shift!),
  });

  List<Map<String, Object?>> _shiftPayments(
    String shiftUuid,
  ) => <Map<String, Object?>>[
    for (final List<Map<String, Object?>> list in bills.values)
      for (final Map<String, Object?> b in list)
        for (final Map<String, Object?> p
            in (b['payments']! as List<Object?>).cast<Map<String, Object?>>())
          if (p['shift_uuid'] == shiftUuid) <String, Object?>{...p, '_bill': b},
  ];

  Map<String, Object?> _report(Map<String, Object?> shift) {
    final String id = '${shift['uuid']}';
    final List<Map<String, Object?>> pays = _shiftPayments(id);
    int sumOf(Iterable<Map<String, Object?>> xs, [String key = 'amount']) =>
        xs.fold(0, (int s, Map<String, Object?> x) => s + _t(x[key]));
    final Set<Map<String, Object?>> billSet = <Map<String, Object?>>{
      for (final Map<String, Object?> p in pays)
        p['_bill']! as Map<String, Object?>,
    };
    int billsSum(String key) => sumOf(billSet, key);
    final List<Map<String, Object?>> refundsIn = <Map<String, Object?>>[
      for (final Map<String, Object?> f in refunds)
        if (f['shift_uuid'] == id) f,
    ];
    final List<Map<String, Object?>> deposits = <Map<String, Object?>>[
      for (final Map<String, Object?> b in bookings.values)
        for (final Map<String, Object?> p
            in (b['payments']! as List<Object?>).cast<Map<String, Object?>>())
          if (p['shift_uuid'] == id) p,
    ];
    final List<Map<String, Object?>> refundedDeposits = <Map<String, Object?>>[
      for (final Map<String, Object?> b in bookings.values)
        for (final Map<String, Object?> p
            in (b['payments']! as List<Object?>).cast<Map<String, Object?>>())
          if (p['refunded_shift_uuid'] == id) p,
    ];
    final List<Map<String, Object?>> repayments = <Map<String, Object?>>[
      for (final List<Map<String, Object?>> list in entries.values)
        for (final Map<String, Object?> e in list)
          if (e['type'] == 'repayment' && e['shift_uuid'] == id) e,
    ];
    final List<Map<String, Object?>> moves = <Map<String, Object?>>[
      for (final Map<String, Object?> mv in movements)
        if (mv['shift_uuid'] == id) mv,
    ];
    int moveSum(String type) =>
        sumOf(moves.where((Map<String, Object?> x) => x['type'] == type));
    Iterable<Map<String, Object?>> tender(String t) =>
        pays.where((Map<String, Object?> p) => p['tender'] == t);
    final int cashSales = sumOf(tender('cash'));
    final int cashRefunds = sumOf(
      refundsIn.where((Map<String, Object?> f) => f['method'] == 'cash'),
    );
    final int cashDeposits = sumOf(
      deposits.where((Map<String, Object?> p) => p['tender'] == 'cash'),
    );
    final int cashDepositRefunds = refundedDeposits
        .where((Map<String, Object?> p) => p['tender'] == 'cash')
        .fold(
          0,
          (int s, Map<String, Object?> p) => s + _t(p['refunded_amount']),
        );
    final int cashRepay = repayments
        .where((Map<String, Object?> e) => e['tender'] == 'cash')
        .fold(0, (int s, Map<String, Object?> e) => s - _t(e['amount']));
    final int opening = _t(shift['opening_cash']);
    final int expected =
        opening +
        cashSales +
        moveSum('cash_in') +
        cashDeposits +
        cashRepay -
        cashRefunds -
        cashDepositRefunds -
        moveSum('cash_out') -
        moveSum('wage') -
        moveSum('purchase');
    final int received = sumOf(pays);
    final int refunded = sumOf(refundsIn);
    final Map<String, int> cards = <String, int>{};
    for (final Map<String, Object?> p in tender('card')) {
      cards['${p['card_type']}'] =
          (cards['${p['card_type']}'] ?? 0) + _t(p['amount']);
    }
    return <String, Object?>{
      'shift_uuid': id,
      'generated_at': _iso(state.now()),
      'period': <String, Object?>{
        'from': shift['opened_at'],
        'to': shift['closed_at'],
      },
      'sales': <String, Object?>{
        'bills': billSet.length,
        'received': _m(received),
        'refunded': _m(refunded),
        'net': _m(received - refunded),
        'bills_total': _m(billsSum('total')),
        'subtotal': _m(billsSum('subtotal')),
        'discount': _m(billsSum('discount')),
        'room_fee': _m(billsSum('room_fee_amount')),
        'min_spend_topup': _m(billsSum('min_spend_topup_amount')),
        'service_charge': _m(billsSum('service_charge_amount')),
        'place_fee': _m(billsSum('place_fee_amount')),
        'average_bill': _m(
          billSet.isEmpty ? 0 : billsSum('total') ~/ billSet.length,
        ),
      },
      'by_tender': <Object?>[
        for (final String t in <String>[
          'cash',
          'card',
          'qr',
          'prepayment',
          'account',
        ])
          <String, Object?>{
            'tender': t,
            'count': tender(t).length,
            'amount': _m(sumOf(tender(t))),
          },
      ],
      'cards': <Object?>[
        for (final MapEntry<String, int> c in cards.entries)
          <String, Object?>{'card_type': c.key, 'amount': _m(c.value)},
      ],
      'by_channel': <Object?>[
        <String, Object?>{
          'channel': 'pos_hall',
          'orders': billSet.length,
          'amount': _m(received),
        },
      ],
      'by_waiter': <Object?>[],
      'discounts': <String, Object?>{
        'count': billSet
            .where((Map<String, Object?> b) => _t(b['discount']) > 0)
            .length,
        'amount': _m(billsSum('discount')),
      },
      'voids': <String, Object?>{'count': 0, 'amount': '0.00'},
      'refunds': <String, Object?>{
        'count': refundsIn.length,
        'amount': _m(refunded),
        'by_method': <Object?>[],
      },
      'deposits': <String, Object?>{
        'taken': <String, Object?>{
          'count': deposits.length,
          'amount': _m(sumOf(deposits)),
          'cash': _m(cashDeposits),
        },
        'applied': <String, Object?>{
          'count': tender('prepayment').length,
          'amount': _m(sumOf(tender('prepayment'))),
        },
        'refunded': <String, Object?>{
          'count': refundedDeposits.length,
          'amount': _m(
            refundedDeposits.fold(
              0,
              (int s, Map<String, Object?> p) => s + _t(p['refunded_amount']),
            ),
          ),
          'cash': _m(cashDepositRefunds),
        },
      },
      'credit': <String, Object?>{
        'sales': <String, Object?>{
          'count': tender('account').length,
          'amount': _m(sumOf(tender('account'))),
        },
        'repayments': <String, Object?>{
          'count': repayments.length,
          'amount': _m(
            repayments.fold(
              0,
              (int s, Map<String, Object?> e) => s - _t(e['amount']),
            ),
          ),
          'cash': _m(cashRepay),
        },
      },
      'cash': <String, Object?>{
        'opening': _m(opening),
        'sales': _m(cashSales),
        'refunds': _m(cashRefunds),
        'cash_in': _m(moveSum('cash_in')),
        'cash_out': _m(moveSum('cash_out')),
        'wages': _m(moveSum('wage')),
        'purchases': _m(moveSum('purchase')),
        'deposits': _m(cashDeposits),
        'deposit_refunds': _m(cashDepositRefunds),
        'repayments': _m(cashRepay),
        'expected': _m(expected),
      },
      'movements': <Object?>[
        <String, Object?>{
          'uuid': 'opening-$id',
          'type': 'opening',
          'category': null,
          'amount': shift['opening_cash'],
          'note': null,
          'employee': null,
          'user': shift['opened_by'],
          'approved_by': null,
          'created_at': shift['opened_at'],
        },
        ...moves,
      ],
      'batch_writeoffs': _shiftWriteOffs(id),
    };
  }

  /// X/Z hisobotning `batch_writeoffs` bloki (P6-15): taom + sabab bo'yicha yig'indi.
  Map<String, Object?> _shiftWriteOffs(String shiftUuid) {
    final Map<String, Map<String, Object?>> rows =
        <String, Map<String, Object?>>{};
    var count = 0;
    for (final Map<String, Object?> w in batchWriteOffs) {
      if (w['shift_uuid'] != shiftUuid) continue;
      count++;
      final String key = '${w['product_id']}|${w['reason']}';
      final Map<String, Object?> row = rows[key] ??= <String, Object?>{
        'product_id': w['product_id'],
        'product_name': _productName(w['product_id']),
        'unit': 'piece',
        'reason': w['reason'],
        'reason_label': _reasonLabels['${w['reason']}'] ?? '${w['reason']}',
        'quantity': 0,
      };
      row['quantity'] = (row['quantity']! as num) + (w['quantity']! as num);
    }
    return <String, Object?>{'count': count, 'items': rows.values.toList()};
  }

  String? _productName(Object? id) {
    for (final Map<String, Object?> p in state.products) {
      if (p['id'] == id) return p['name'] as String?;
    }
    return null;
  }

  static const Map<String, String> _reasonLabels = <String, String>{
    'unsold': 'Sotilmay qoldi',
    'spoiled': 'Buzildi',
    'staff_meal': 'Xodimlar ovqati',
    'dropped': "To'kildi yoki tushib ketdi",
    'expired': 'Muddati tugadi',
    'other': 'Boshqa sabab',
  };

  FakeResponse _shiftAction(
    String m,
    String uuid,
    String? action,
    FakeRequest r,
  ) {
    final Map<String, Object?>? open =
        state.shift != null && state.shift!['uuid'] == uuid
        ? state.shift
        : null;
    final Map<String, Object?>? shift = open ?? closedShifts[uuid];
    if (shift == null) return _fail(404, null, 'Smena topilmadi.');
    if (m == 'GET' && action == null) {
      return _ok(<String, Object?>{
        ...shift,
        if (open != null) 'summary': null,
      });
    }
    if (m == 'GET' && action == 'x-report') {
      return _ok(<String, Object?>{
        'shift': shift,
        'report': shift['summary'] ?? _report(shift),
      });
    }
    if (m == 'PUT' && action == 'bank-report' && open == null) {
      return _bankReportPut(shift, r);
    }
    if (open == null) {
      return _fail(409, 'shift_closed', 'Smena allaqachon yopilgan.');
    }
    if (m == 'POST' && action == 'cash-movements') return _movement(open, r);
    if (m == 'POST' && action == 'close') return _closeShift(open, r);
    if (m == 'PUT' && action == 'bank-report') return _bankReportPut(open, r);
    return _fail(404, null, 'Topilmadi.');
  }

  FakeResponse _movement(Map<String, Object?> shift, FakeRequest r) {
    final String type = '${r.data['type']}';
    final String category = '${r.data['category'] ?? ''}';
    // Review P6 wave 2 (as the server): `payroll` only from a pay statement; a tip payout needs tips.manage or a
    // manager's approval for it (that approval stands for pos.cash_in_out too)
    if (category == 'payroll') {
      return _fail(
        422,
        'movement_category_reserved',
        "Ish haqi hisob-kitobi kassadan faqat hisob-kitob qatori orqali to'lanadi.",
      );
    }
    final bool tipPayout = type == 'wage' && category == 'tips';
    final FakeResponse? denied = state.authorize(
      r.member,
      tipPayout && !state.permissionsOf(r.member).contains('tips.manage')
          ? 'tips.manage'
          : 'pos.cash_in_out',
      r.data['approval_token'],
    );
    if (denied != null) return denied;
    final String? note = (r.data['note'] as String?)?.trim();
    final int amount = _t(r.data['amount']);
    if (amount <= 0) return _validation('amount', "Summani kiriting.");
    if (type == 'wage' && r.data['employee_id'] == null) {
      return _validation('employee_id', 'Kimga berilganini tanlang.');
    }
    if (category == 'other' && (note == null || note.isEmpty)) {
      return _validation('note', "\"Boshqa\" uchun izoh yozing.");
    }
    if (type != 'cash_in') {
      final int expected = _t(
        (_report(shift)['cash']! as Map<String, Object?>)['expected'],
      );
      if (amount > expected) {
        return FakeResponse(409, <String, Object?>{
          'success': false,
          'message': "Kassada buncha naqd pul yo'q.",
          'data': <String, Object?>{
            'reason': 'not_enough_cash',
            'expected_cash': _m(expected),
          },
        });
      }
    }
    final int? employeeId = (r.data['employee_id'] as num?)?.toInt();
    String? employeeName;
    for (final Map<String, Object?> s in state.staff) {
      if (s['id'] == employeeId) employeeName = s['name'] as String?;
    }
    final Map<String, Object?> movement = <String, Object?>{
      'uuid': _uuid('m'),
      'type': type,
      'category': category,
      'amount': _m(amount),
      'note': note,
      'employee': employeeId == null
          ? null
          : <String, Object?>{'id': employeeId, 'name': employeeName},
      'user': <String, Object?>{'id': r.member['id'], 'name': r.member['name']},
      'approved_by': !tipPayout || r.data['approval_token'] == null
          ? null
          : state.staff
                .where((Map<String, Object?> m) => m['role'] == 'manager')
                .map((Map<String, Object?> m) => m['id']! as int)
                .firstOrNull,
      'created_at': _iso(state.now()),
      'shift_uuid': shift['uuid'],
    };
    movements.add(movement);
    _shiftEvent(shift, 'cash_movement');
    return _ok(
      <String, Object?>{
        'movement': movement,
        'expected_cash':
            (_report(shift)['cash']! as Map<String, Object?>)['expected'],
      },
      status: 201,
      message: 'Yozildi.',
    );
  }

  FakeResponse _closeShift(Map<String, Object?> shift, FakeRequest r) {
    final FakeResponse? denied = state.authorize(
      r.member,
      'pos.close_shift',
      null,
    );
    if (denied != null) return denied;
    final bool force = r.data['force'] == true;
    final List<String> openOrders = <String>[
      for (final Map<String, Object?> o in state.orders.values)
        if (o['status'] != 'delivered' && o['status'] != 'canceled')
          '${o['uuid']}',
    ];
    final List<String> pending = <String>[
      for (final List<Map<String, Object?>> list in bills.values)
        for (final Map<String, Object?> b in list)
          if (_awaitingReceipt(b)) '${b['uuid']}',
    ];
    if (!force && (openOrders.isNotEmpty || pending.isNotEmpty)) {
      return FakeResponse(409, <String, Object?>{
        'success': false,
        'message': openOrders.isNotEmpty
            ? 'Ochiq buyurtmalar bor: avval ularni yoping yoki menejer smenani majburan yopsin.'
            : "Fiskal cheki chiqmagan to'lovlar bor (fiskal kutmoqda). Chekni qayta yuboring yoki menejer smenani majburan yopsin.",
        'data': <String, Object?>{
          'reason': openOrders.isNotEmpty
              ? 'shift_has_open_orders'
              : 'shift_has_pending_receipts',
          if (openOrders.isNotEmpty) 'open_orders': openOrders,
          'pending_bills': pending,
          'pending_refunds': <Object?>[],
        },
      });
    }
    if (force) {
      if ('${r.data['reason'] ?? ''}'.trim().isEmpty) {
        return _fail(
          422,
          'force_reason_required',
          'Majburan yopish sababini yozing.',
        );
      }
      final FakeResponse? needs = state.authorize(
        r.member,
        'orders.cancel',
        r.data['approval_token'],
      );
      if (needs != null) return needs;
    }
    final Map<String, Object?> breakdown =
        ((r.data['counted_breakdown'] as Map<Object?, Object?>?) ??
                <Object?, Object?>{})
            .cast<String, Object?>();
    int counted = 0;
    breakdown.forEach(
      (String k, Object? v) =>
          counted += int.parse(k) * ((v as num?)?.toInt() ?? 0) * 100,
    );
    final Map<String, Object?> summary = _report(shift);
    final int expected = _t(
      (summary['cash']! as Map<String, Object?>)['expected'],
    );
    shift
      ..['status'] = 'closed'
      ..['closed_at'] = _iso(state.now())
      ..['closed_by'] = <String, Object?>{
        'id': r.member['id'],
        'name': r.member['name'],
      }
      ..['expected_cash'] = _m(expected)
      ..['counted_cash'] = _m(counted)
      ..['counted_breakdown'] = breakdown
      ..['difference'] = _m(counted - expected)
      ..['z_number'] = ++zNumber
      ..['force_closed'] = force
      ..['close_note'] = r.data['reason'];
    summary['period'] = <String, Object?>{
      'from': shift['opened_at'],
      'to': shift['closed_at'],
    };
    shift['summary'] = summary;
    closedShifts['${shift['uuid']}'] = shift;
    state.shift = null;
    // P6-16: the bank terminal's day total, compared after the Z is written; it never keeps the shift from closing
    final Object? bank = r.data['bank_terminal'];
    if (bank is Map && state.bootstrapExtras['terminal_flow'] != null) {
      final int? total = bank['total'] == null ? null : _t(bank['total']);
      if (total != null) {
        shift['bank_terminal'] = _bankReport(
          shift,
          source: 'manual',
          bankTotal: total,
          bankCount: (bank['count'] as num?)?.toInt(),
          terminalRef: bank['terminal_ref'] as String?,
          note: bank['note'] as String?,
        );
      }
    } else if (shift['bank_terminal'] is Map) {
      // An entry made before the close, worked out again with the whole shift
      final Map<String, Object?> earlier = (shift['bank_terminal']! as Map)
          .cast<String, Object?>();
      shift['bank_terminal'] = _bankReport(
        shift,
        source: '${earlier['source'] ?? 'manual'}',
        bankTotal: _t(earlier['bank_total']),
        bankCount: (earlier['bank_count'] as num?)?.toInt(),
        terminalRef: earlier['terminal_ref'] as String?,
        note: earlier['note'] as String?,
      );
    }
    _shiftEvent(shift, 'closed');
    return _ok(shift, message: 'Smena yopildi.');
  }

  /// Smenadagi karta pul (Rizqim tomoni): schyotlarning karta to'lovlari.
  int _cardMoney(Map<String, Object?> shift) {
    final List<Map<String, Object?>> pays = _shiftPayments('${shift['uuid']}');
    int sum = 0;
    for (final Map<String, Object?> p in pays) {
      if (p['tender'] == 'card') sum += _t(p['amount']);
    }
    return sum;
  }

  /// `bank_terminal` bloki (BankTerminalPresenter shakli).
  Map<String, Object?> _bankReport(
    Map<String, Object?> shift, {
    required String source,
    required int bankTotal,
    int? bankCount,
    String? terminalRef,
    String? note,
  }) {
    final int threshold = bankThreshold;
    final int rizqim = _cardMoney(shift);
    final int diff = bankTotal - rizqim;
    // Review P6 S#5: while the shift is open the till gets what it typed only
    final bool done = shift['status'] != 'open';
    return <String, Object?>{
      'uuid': _uuid('bt'),
      'shift_uuid': shift['uuid'],
      'z_number': shift['z_number'],
      'branch_id': FakePosState.branchId,
      'business_date': shift['business_date'],
      'source': source,
      'terminal_ref': terminalRef,
      'shared': false,
      'final': done,
      'shifts': <Map<String, Object?>>[
        <String, Object?>{
          'uuid': shift['uuid'],
          'z_number': shift['z_number'],
          'status': shift['status'],
        },
      ],
      'bank_total': _m(bankTotal),
      'bank_count': bankCount,
      'rizqim_total': done ? _m(rizqim) : null,
      'rizqim_count': done
          ? _shiftPayments('${shift['uuid']}')
                .where((Map<String, Object?> p) => p['tender'] == 'card')
                .length
          : null,
      'rizqim_breakdown': null,
      'difference': done ? _m(diff) : null,
      'threshold': done ? _m(threshold) : null,
      'over_threshold': done ? diff.abs() > threshold : null,
      'history': <Object?>[],
      'transactions': null,
      'unmatched': null,
      'note': note,
      'entered_by': <String, Object?>{'id': 3, 'name': 'Kamola Karimova'},
      'alerted_at': null,
      'created_at': _iso(state.now()),
      'updated_at': _iso(state.now()),
    };
  }

  /// `PUT /pos/shifts/{uuid}/bank-report` (P6-16).
  FakeResponse _bankReportPut(Map<String, Object?> shift, FakeRequest r) {
    final FakeResponse? denied = state.authorize(
      r.member,
      'pos.close_shift',
      null,
    );
    if (denied != null) return denied;
    final Map<String, Object?>? flow =
        state.bootstrapExtras['terminal_flow'] as Map<String, Object?>?;
    if (flow == null) {
      return _fail(403, 'module_disabled', "Bu modul filialda o'chirilgan.");
    }
    final String source = '${r.data['source'] ?? 'manual'}';
    int total;
    if (source == 'provider') {
      if (flow['smart_kassa'] != true) {
        return _fail(
          409,
          'provider_report_unavailable',
          "Bu kassa smart-kassa emas: bank terminali hisobotini qo'lda kiriting.",
        );
      }
      total = providerCardTotal ?? _cardMoney(shift);
    } else {
      if (r.data['bank_total'] == null) {
        return _fail(
          422,
          'bank_total_required',
          'Bank terminalining kunlik jami summasini kiriting.',
        );
      }
      total = _t(r.data['bank_total']);
    }
    final Map<String, Object?> report = _bankReport(
      shift,
      source: source,
      bankTotal: total,
      bankCount: (r.data['bank_count'] as num?)?.toInt(),
      terminalRef: r.data['terminal_ref'] as String?,
      note: r.data['note'] as String?,
    );
    shift['bank_terminal'] = report;
    return _ok(report, message: 'Yozildi.');
  }

  /// `POST /owner/batches/{id}/write-off` (P6-15): `batches.manage` + `batch_writeoff` moduli.
  FakeResponse _writeOff(int batchId, FakeRequest r) {
    final Map<String, Object?>? format = state.format;
    if (format != null) {
      final Object? modules = format['modules'];
      if (modules is! List || !modules.contains('batch_writeoff')) {
        return const FakeResponse(403, <String, Object?>{
          'success': false,
          'message': "Bu modul filialda o'chirilgan.",
          'code': 'module_disabled',
          'module': 'batch_writeoff',
          'data': <String, Object?>{
            'reason': 'module_disabled',
            'module': 'batch_writeoff',
          },
        });
      }
    }
    final String? key = r.idempotencyKey;
    if (key != null && _writeOffByKey.containsKey(key)) {
      return _ok(_writeOffByKey[key]!, status: 201);
    }
    final List<Object?> batches =
        state.availability['batches']! as List<Object?>;
    Map<String, Object?>? batch;
    for (final Object? b in batches) {
      if (b is Map<String, Object?> && b['id'] == batchId) batch = b;
    }
    if (batch == null) return _fail(404, null, 'Partiya topilmadi.');
    final double quantity = double.tryParse('${r.data['quantity']}') ?? 0;
    final double left = (batch['quantity_left']! as num).toDouble();
    final String reason = '${r.data['reason']}';
    if (quantity <= 0) return _validation('quantity', 'Miqdorni kiriting.');
    if (quantity > left) {
      return _validation(
        'quantity',
        "Hisobdan chiqariladigan miqdor qoldiqdan ko'p ($left).",
      );
    }
    if (reason == 'other' && '${r.data['note'] ?? ''}'.trim().isEmpty) {
      return _validation('note', 'Boshqa sabab uchun izoh yozing.');
    }
    // P6-19: batches.manage, or a manager's approval on the till (after the checks: a refused one keeps the token)
    final FakeResponse? denied = state.authorize(
      r.member,
      'batches.manage',
      r.data['approval_token'],
    );
    if (denied != null) return denied;
    final int? approvedBy = r.data['approval_token'] == null
        ? null
        : state.staff
              .where((Map<String, Object?> m) => m['role'] == 'manager')
              .map((Map<String, Object?> m) => m['id']! as int)
              .firstOrNull;
    final bool close = r.data['close'] == true;
    batch['quantity_left'] = close ? 0 : left - quantity;
    final int shiftId = state.shift == null ? 0 : 1;
    batchWriteOffs.add(<String, Object?>{
      'shift_uuid': state.shift?['uuid'],
      'product_id': batch['product_id'],
      'quantity': quantity,
      'reason': reason,
    });
    final Map<String, Object?> body = <String, Object?>{
      'write_off': <String, Object?>{
        'uuid': 'wo-${++_writeOffSeq}',
        'quantity': quantity,
        'unit': 'piece',
        'reason': reason,
        'note': r.data['note'],
        'business_date': _localDate(state.now()),
        'shift_id': shiftId == 0 ? null : shiftId,
        'approved_by': ?approvedBy,
      },
      'batch': <String, Object?>{
        'id': batchId,
        'uuid': batch['uuid'],
        'product_id': batch['product_id'],
        'product_name': null,
        'product_variant_id': batch['product_variant_id'],
        'unit': 'piece',
        'quantity_produced': batch['quantity_produced'],
        'quantity_left': batch['quantity_left'],
        'written_off_quantity': quantity,
        'is_open': !close && (batch['quantity_left']! as num) > 0,
      },
    };
    if (key != null) _writeOffByKey[key] = body;
    state.emit('menu.availability_changed', <String, Object?>{
      'branch_id': FakePosState.branchId,
      'reason': 'batch_writeoff',
    });
    return _ok(body, status: 201, message: 'Hisobdan chiqarildi.');
  }

  void _shiftEvent(Map<String, Object?> shift, String change) =>
      state.emit('shift.changed', <String, Object?>{
        'shift': <String, Object?>{
          'uuid': shift['uuid'],
          'status': shift['status'],
          'store_id': FakePosState.storeId,
          'branch_id': FakePosState.branchId,
          'device_uuid': FakePosState.deviceUuid,
          'business_date': shift['business_date'],
          'z_number': shift['z_number'],
        },
        'change': change,
        'changed_at': _iso(state.now()),
      });

  // --- bron ------------------------------------------------------------------------------------------------------------

  /// Do'kon vaqti (Toshkent, UTC+5) bo'yicha sana.
  static String _localDate(DateTime utc) =>
      _date(utc.toUtc().add(const Duration(hours: 5)));

  Map<String, Object?>? _bookingOfOrder(Map<String, Object?> order) {
    for (final Map<String, Object?> b in bookings.values) {
      if (b['order_uuid'] == order['uuid']) return b;
    }
    return null;
  }

  FakeResponse? _bookings(FakeRequest r) {
    final String p = r.path;
    final String m = r.method;
    if (m == 'GET' && p == '/v1/pos/bookings/calendar') {
      return _calendar(r.query);
    }
    if (m == 'GET' && p == '/v1/pos/bookings') return _bookingList(r.query);
    final FakeResponse? denied = state.authorize(
      r.member,
      'bookings.manage',
      null,
    );
    if (denied != null) return denied;
    if (m == 'POST' && p == '/v1/pos/bookings') return _saveBooking(null, r);
    final RegExpMatch? x = RegExp(r'^/v1/pos/bookings/([^/]+)(?:/(.+))?$')
        .firstMatch(p);
    if (x == null) return null;
    final Map<String, Object?>? b = bookings[x.group(1)];
    if (b == null) return _fail(404, null, 'Bron topilmadi.');
    final String? action = x.group(2);
    if (m == 'GET' && action == null) return _ok(b);
    if (m == 'PATCH' && action == null) return _saveBooking(b, r);
    final Object? version = r.data['version'];
    if (version != null && (version as num).toInt() != b['version']) {
      return _fail(
        409,
        'stale_version',
        "Bron boshqa qurilmada o'zgartirildi. Qayta yuklang.",
      );
    }
    final List<String> parts = action?.split('/') ?? <String>[];
    switch ((m, parts)) {
      case ('POST', <String>['confirm']):
        if (b['status'] != 'requested') return _transition();
        b['status'] = 'confirmed';
        _autoSendConfirmation(b);
      case ('GET', <String>['confirmation']):
        return _ok(_confirmation(b));
      case ('POST', <String>['confirmation', 'send']):
        return _sendConfirmation(b, r);
      case ('POST', <String>['cancel']):
        if (b['status'] != 'requested' && b['status'] != 'confirmed') {
          return _transition();
        }
        if ('${r.data['reason'] ?? ''}'.trim().isEmpty) {
          return _validation('reason', 'Sababini yozing.');
        }
        b
          ..['status'] = 'canceled'
          ..['cancel_reason'] = r.data['reason'];
      case ('POST', <String>['no-show']):
        if (b['status'] != 'confirmed' ||
            state.now().isBefore(DateTime.parse('${b['starts_at']}'))) {
          return _transition();
        }
        b['status'] = 'no_show';
      case ('POST', <String>['arrive']):
        return _arrive(b, r);
      case ('POST', <String>['items']):
        return _addBookingItem(b, r);
      case ('PATCH', <String>['items', final String id]):
        final Map<String, Object?>? item = _bookingItem(b, int.parse(id));
        if (item == null) return _fail(404, null, 'Qator topilmadi.');
        if (r.data['quantity'] != null) {
          item['quantity'] = _milli(r.data['quantity']) / 1000;
        }
        if (r.data.containsKey('note')) item['note'] = r.data['note'];
        if (r.data['due_at'] != null) item['due_at'] = r.data['due_at'];
      case ('DELETE', <String>['items', final String id]):
        (b['items']! as List<Object?>).removeWhere(
          (Object? i) => (i! as Map<String, Object?>)['id'] == int.parse(id),
        );
      case ('POST', <String>['deposits']):
        return _takeDeposit(b, r);
      case ('POST', <String>['deposits', final String pay, final String op]):
        return _settleDeposit(b, pay, op, r);
      default:
        return _fail(404, null, 'Topilmadi.');
    }
    _touchBooking(b);
    return _ok(b);
  }

  // --- P3-38: bron tasdig'i ------------------------------------------------------------------------------------------

  static const List<String> _weekdays = <String>[
    'Dushanba',
    'Seshanba',
    'Chorshanba',
    'Payshanba',
    'Juma',
    'Shanba',
    'Yakshanba',
  ];

  static const List<String> _months = <String>[
    'yanvar',
    'fevral',
    'mart',
    'aprel',
    'may',
    'iyun',
    'iyul',
    'avgust',
    'sentabr',
    'oktabr',
    'noyabr',
    'dekabr',
  ];

  static const Map<String, String> _dayTypePrice = <String, String>{
    'weekday': 'hafta ichi narxi',
    'weekend': 'dam olish kuni narxi',
    'holiday': 'bayram kuni narxi',
  };

  /// "300 000 so'm".
  static String _som(int tiyin) {
    final String digits = '${tiyin.abs() ~/ 100}';
    final StringBuffer out = StringBuffer(tiyin < 0 ? '-' : '');
    for (var i = 0; i < digits.length; i++) {
      if (i > 0 && (digits.length - i) % 3 == 0) out.write(' ');
      out.write(digits[i]);
    }
    return "$out so'm";
  }

  static String _hm(DateTime local) =>
      '${local.hour.toString().padLeft(2, '0')}:${local.minute.toString().padLeft(2, '0')}';

  /// The fake's rules, as the order will price the place later: rooms (`kind: room`) pay the day type's room fee
  /// ([roomFees]: holidays from pos_settings, Saturday and Sunday are weekends), the hall takes 10% service charge.
  Map<String, Object?> _confirmation(Map<String, Object?> b) {
    final DateTime starts = DateTime.parse('${b['starts_at']}')
        .toUtc()
        .add(const Duration(hours: 5));
    final DateTime ends = DateTime.parse('${b['ends_at']}')
        .toUtc()
        .add(const Duration(hours: 5));
    final String date = _date(starts);
    final String dayType = state.holidays.contains(date)
        ? 'holiday'
        : (starts.weekday >= DateTime.saturday ? 'weekend' : 'weekday');
    final List<Map<String, Object?>> rooms = <Map<String, Object?>>[
      for (final Object? id in b['table_ids']! as List<Object?>)
        state.tables.firstWhere((Map<String, Object?> t) => t['id'] == id),
    ];
    final int guests = math.max(
      1,
      ((b['guests_planned'] as num?) ?? 1).toInt(),
    );
    final String headline = switch (b['status']) {
      'requested' => 'Bron qabul qilindi',
      'canceled' || 'no_show' => 'Bron bekor qilindi',
      _ => 'Bron tasdiqlandi',
    };
    final List<(String, String, String, String)>
    lines = <(String, String, String, String)>[
      (
        'header',
        '',
        headline,
        headline.contains('tasdiqlandi')
            ? '✅'
            : (headline.contains('bekor') ? '❌' : '📝'),
      ),
      ('place', '', 'Rohat choyxonasi, Chilonzor', ''),
      (
        'time',
        '',
        '${_weekdays[starts.weekday - 1]}, ${starts.day}-${_months[starts.month - 1]}, ${_hm(starts)}–${_hm(ends)}',
        '📅',
      ),
      (
        'room',
        rooms.length > 1 ? 'Xonalar' : 'Xona',
        rooms.isEmpty
            ? 'keyin belgilanadi · $guests kishi'
            : '${rooms.map((Map<String, Object?> t) => '"${t['name']}"').join(', ')}'
                  '${rooms.length == 1 ? ' (${rooms.single['seats']} kishilik)' : ''} · $guests kishi',
        '🚪',
      ),
    ];
    Map<String, Object?>? roomFee;
    final int fee = rooms.any((Map<String, Object?> t) => t['kind'] == 'room')
        ? _t(roomFees[dayType])
        : 0;
    if (fee > 0) {
      roomFee = <String, Object?>{
        'amount': _m(fee),
        'day_type': dayType,
        'tariff_id':
            const <String>['weekday', 'weekend', 'holiday'].indexOf(dayType) +
            1,
      };
      lines.add((
        'room_fee',
        'Xona puli',
        '${_som(fee)} (${_dayTypePrice[dayType]})',
        '💵',
      ));
    }
    final int minSpend = _t(
      b['min_spend'] ?? (rooms.isEmpty ? null : rooms.first['min_spend']),
    );
    if (minSpend > 0) {
      lines.add(('min_spend', 'Minimal buyurtma', _som(minSpend), '💵'));
    }
    if (rooms.isNotEmpty) {
      lines.add(('service_charge', 'Xizmat haqi', '10%', '💵'));
    }
    final List<Object?> items = b['items']! as List<Object?>;
    if (items.isNotEmpty) {
      lines.add((
        'pre_order',
        'Oldindan buyurtma',
        <String>[
          for (final Object? raw in items)
            () {
              final Map<String, Object?> i = raw! as Map<String, Object?>;
              final int milli = _milli(i['quantity']);
              final String q = milli % 1000 == 0
                  ? '${milli ~/ 1000}'
                  : (milli / 1000).toString();
              return '${i['product_name']} $q ${i['unit'] == 'kg' ? 'kg' : 'ta'}';
            }(),
        ].join(', '),
        '🍽',
      ));
    }
    final int deposits = _paymentsOf(b).fold(
      0,
      (int s, Map<String, Object?> p) =>
          s + _t(p['amount']) - _t(p['refunded_amount']),
    );
    if (deposits > 0) {
      lines.add(('deposit', "Bo'nak olindi", _som(deposits), '💳'));
    }
    final Object? policy = state.bookingConfirmation['cancel_policy'];
    if (policy != null && '$policy'.trim().isNotEmpty) {
      lines.add(('cancel_policy', '', '$policy', 'ℹ️'));
    }
    final String code = shortCodeOf('${b['uuid']}');
    lines.add(('contact', '', 'Bron raqami: $code', '📞'));
    final String? phone = b['contact_phone'] as String?;
    return <String, Object?>{
      'text': <String>[
        for (final (String kind, String label, String value, String icon)
            in lines)
          <String>[
            if (kind != 'header' && icon.isNotEmpty) icon,
            label.isEmpty ? value : '$label: $value',
            if (kind == 'header') icon,
          ].join(' '),
      ].join('\n'),
      'lines': <Object?>[
        for (final (String kind, String label, String value, String _) in lines)
          <String, Object?>{'kind': kind, 'label': label, 'value': value},
      ],
      'room_fee': roomFee,
      'channels': <String, Object?>{
        'telegram': phone != null && telegramPhones.contains(phone),
        'sms': phone != null && state.bookingConfirmation['sms'] == true,
      },
    };
  }

  /// `auto`: Telegram if the guest's phone is linked to the bot, else SMS when the branch turned it on; `null` — no
  /// channel.
  String? _channelFor(Map<String, Object?> b, String wanted) {
    final Map<String, Object?> channels =
        _confirmation(b)['channels']! as Map<String, Object?>;
    return switch (wanted) {
      'telegram' => channels['telegram'] == true ? 'telegram' : null,
      'sms' => channels['sms'] == true ? 'sms' : null,
      _ =>
        channels['telegram'] == true
            ? 'telegram'
            : (channels['sms'] == true ? 'sms' : null),
    };
  }

  void _recordSend(
    Map<String, Object?> b,
    String channel, {
    bool auto = false,
  }) {
    b
      ..['confirmation_sent_at'] = _iso(state.now())
      ..['confirmation_channel'] = channel;
    sentConfirmations.add(<String, Object?>{
      'booking_uuid': b['uuid'],
      'channel': channel,
      'text': _confirmation(b)['text'],
      'auto': auto,
    });
  }

  /// The short number the guest quotes: the last six characters of the uuid, upper case.
  static String shortCodeOf(String uuid) {
    final String hex = uuid.replaceAll('-', '');
    return hex.substring(hex.length - 6).toUpperCase();
  }

  FakeResponse _sendConfirmation(Map<String, Object?> b, FakeRequest r) {
    final String? key = r.idempotencyKey;
    final Map<String, Object?>? replay = key == null
        ? null
        : _confirmationByKey[key];
    if (replay != null) return _ok(replay);
    if (b['status'] != 'requested' && b['status'] != 'confirmed') {
      return _fail(
        422,
        'booking_not_editable',
        "Bron \"${b['status_label']}\" holatida: tasdiq yuborilmaydi.",
      );
    }
    final String wanted = '${r.data['channel'] ?? 'auto'}';
    if (!const <String>['auto', 'telegram', 'sms'].contains(wanted)) {
      return _validation('channel', "Kanal noto'g'ri.");
    }
    final String? channel = _channelFor(b, wanted);
    if (channel == null) {
      return _fail(
        422,
        'no_channel',
        "Tasdiqni yuborib bo'lmaydi: mehmon Telegram botga ulanmagan, SMS o'chirilgan.",
      );
    }
    _recordSend(b, channel);
    final Map<String, Object?> result = <String, Object?>{
      'channel': channel,
      'queued': true,
    };
    if (key != null) _confirmationByKey[key] = result;
    return _ok(result, message: 'Tasdiq yuborildi.');
  }

  /// A booking that became confirmed: the confirmation goes by `auto` when the branch wants it (no channel — nothing,
  /// the booking is never blocked).
  void _autoSendConfirmation(Map<String, Object?> b) {
    if (state.bookingConfirmation['auto_send'] != true) return;
    final String? channel = _channelFor(b, 'auto');
    if (channel != null) _recordSend(b, channel, auto: true);
  }

  FakeResponse _transition() => _fail(
    422,
    'invalid_transition',
    "Bron holatini bunday o'zgartirib bo'lmaydi.",
  );

  Map<String, Object?>? _bookingItem(Map<String, Object?> b, int id) {
    for (final Object? i in b['items']! as List<Object?>) {
      if ((i! as Map<String, Object?>)['id'] == id) {
        return i as Map<String, Object?>;
      }
    }
    return null;
  }

  void _touchBooking(Map<String, Object?> b) {
    _recountDeposits(b);
    b
      ..['version'] = (b['version']! as int) + 1
      ..['updated_at'] = _iso(state.now())
      ..['items_count'] = (b['items']! as List<Object?>).length
      ..['status_label'] = _bookingStatusLabels[b['status']];
    state.emit('booking.changed', <String, Object?>{
      'booking': <String, Object?>{
        'uuid': b['uuid'],
        'status': b['status'],
        'branch_id': FakePosState.branchId,
        'version': b['version'],
        'starts_at': b['starts_at'],
        'ends_at': b['ends_at'],
      },
    });
  }

  static const Map<String, String> _bookingStatusLabels = <String, String>{
    'requested': "So'rov",
    'confirmed': 'Tasdiqlangan',
    'arrived': 'Keldi',
    'completed': 'Yakunlangan',
    'canceled': 'Bekor qilingan',
    'no_show': 'Kelmadi',
  };

  static const Map<String, String> _eventLabels = <String, String>{
    'gap': 'Gap',
    'ziyofat': 'Ziyofat',
    'tugilgan_kun': "Tug'ilgan kun",
    'nahor_oshi': 'Nahor oshi',
    'korporativ': 'Korporativ',
    'other': 'Boshqa',
  };

  FakeResponse _calendar(Map<String, String> q) {
    final String from = q['from'] ?? _date(state.now());
    final String to = q['to'] ?? from;
    final bool canceled = q['include_canceled'] == '1';
    bool inRange(Map<String, Object?> b) {
      final String d = _localDate(DateTime.parse('${b['starts_at']}'));
      return d.compareTo(from) >= 0 &&
          d.compareTo(to) <= 0 &&
          (canceled || (b['status'] != 'canceled' && b['status'] != 'no_show'));
    }

    return _ok(<String, Object?>{
      'branch_id': FakePosState.branchId,
      'from': from,
      'to': to,
      'timezone': 'Asia/Tashkent',
      'rooms': <Object?>[
        for (final Map<String, Object?> t in state.tables)
          <String, Object?>{
            'id': t['id'],
            'name': t['name'],
            'kind': t['kind'],
            'seats': t['seats'],
            'hall_id': t['hall_id'],
            'hall_name': state.halls.firstWhere(
              (Map<String, Object?> h) => h['id'] == t['hall_id'],
            )['name'],
            'is_active': true,
            'min_spend': t['min_spend'],
            'bookings': <Object?>[
              for (final Map<String, Object?> b in bookings.values)
                if (inRange(b) &&
                    (b['table_ids']! as List<Object?>).contains(t['id']))
                  b,
            ],
          },
      ],
      'unassigned': <Object?>[
        for (final Map<String, Object?> b in bookings.values)
          if (inRange(b) && (b['table_ids']! as List<Object?>).isEmpty) b,
      ],
    });
  }

  FakeResponse _bookingList(Map<String, String> q) {
    final String? phone = q['phone']?.replaceAll(RegExp(r'\D'), '');
    final List<Map<String, Object?>> list =
        <Map<String, Object?>>[
          for (final Map<String, Object?> b in bookings.values)
            if (phone != null
                ? '${b['contact_phone']}'
                      .replaceAll(RegExp(r'\D'), '')
                      .contains(phone)
                : (q['from'] == null ||
                          _localDate(DateTime.parse('${b['starts_at']}'))
                                  .compareTo(q['from']!) >=
                              0) &&
                      (q['to'] == null ||
                          _localDate(DateTime.parse('${b['starts_at']}'))
                                  .compareTo(q['to']!) <=
                              0) &&
                      (q['status'] == null || b['status'] == q['status']))
              b,
        ]..sort(
          (Map<String, Object?> a, Map<String, Object?> b) =>
              '${b['starts_at']}'.compareTo('${a['starts_at']}'),
        );
    return _ok(<String, Object?>{'bookings': list});
  }

  static String? _phone(Object? raw) {
    if (raw == null) return null;
    final String digits = '$raw'.replaceAll(RegExp(r'\D'), '');
    if (digits.length == 9) return '+998$digits';
    if (digits.length == 12 && digits.startsWith('998')) return '+$digits';
    return null;
  }

  FakeResponse _saveBooking(Map<String, Object?>? existing, FakeRequest r) {
    final Map<String, Object?> d = r.data;
    if (existing != null &&
        d['version'] != null &&
        (d['version']! as num).toInt() != existing['version']) {
      return _fail(
        409,
        'stale_version',
        "Bron boshqa qurilmada o'zgartirildi. Qayta yuklang.",
      );
    }
    final DateTime? starts = d['starts_at'] == null
        ? (existing == null ? null : DateTime.parse('${existing['starts_at']}'))
        : DateTime.parse('${d['starts_at']}');
    if (starts == null) {
      return _validation('starts_at', 'Boshlanish vaqtini tanlang.');
    }
    final DateTime ends = d['ends_at'] != null
        ? DateTime.parse('${d['ends_at']}')
        : (existing != null && d['starts_at'] == null
              ? DateTime.parse('${existing['ends_at']}')
              : starts.add(const Duration(minutes: 300)));
    if (!ends.isAfter(starts)) {
      return _validation(
        'ends_at',
        'Tugash vaqti boshlanishdan keyin bo\'lsin.',
      );
    }
    final int? table =
        (d['dining_table_id'] as num?)?.toInt() ??
        (existing?['dining_table_id'] as int?);
    final List<int> tableIds = <int>[
      ?table,
      ...((d['extra_table_ids'] as List<Object?>?) ??
              (existing == null
                  ? const <Object?>[]
                  : (existing['table_ids']! as List<Object?>).skip(1).toList()))
          .map((Object? e) => (e! as num).toInt()),
    ];
    final String? phone = d.containsKey('contact_phone')
        ? _phone(d['contact_phone'])
        : existing?['contact_phone'] as String?;
    if (d.containsKey('contact_phone') &&
        d['contact_phone'] != null &&
        phone == null) {
      return _validation('contact_phone', "Telefon raqami noto'g'ri.");
    }
    final List<Map<String, Object?>> conflicts = <Map<String, Object?>>[
      for (final Map<String, Object?> o in bookings.values)
        if (o != existing &&
            const <String>[
              'requested',
              'confirmed',
              'arrived',
            ].contains(o['status']) &&
            (o['table_ids']! as List<Object?>).any(tableIds.contains) &&
            DateTime.parse('${o['starts_at']}').isBefore(ends) &&
            starts.isBefore(DateTime.parse('${o['ends_at']}')))
          o,
    ];
    if (conflicts.isNotEmpty) {
      final String busy = <String>{
        for (final Map<String, Object?> o in conflicts)
          for (final Object? t in o['table_ids']! as List<Object?>)
            if (tableIds.contains(t))
              '${state.tables.firstWhere((Map<String, Object?> x) => x['id'] == t)['name']}',
      }.join(', ');
      return FakeResponse(409, <String, Object?>{
        'success': false,
        'message': 'Bu vaqtda band: $busy. Boshqa vaqt yoki xonani tanlang.',
        'data': <String, Object?>{
          'reason': 'booking_conflict',
          'conflicts': <Object?>[
            for (final Map<String, Object?> o in conflicts)
              <String, Object?>{
                'uuid': o['uuid'],
                'status': o['status'],
                'starts_at': o['starts_at'],
                'ends_at': o['ends_at'],
                'table_ids': o['table_ids'],
              },
          ],
        },
      });
    }
    final Map<String, Object?> b =
        existing ??
        <String, Object?>{
          'uuid': _uuid('k'),
          'branch_id': FakePosState.branchId,
          'status': d['confirm'] == true ? 'confirmed' : 'requested',
          'version': 0,
          'order_uuid': null,
          'deposit_total': '0.00',
          'deposit_available': '0.00',
          'deposit_unsettled': false,
          'cancel_reason': null,
          'confirmation_sent_at': null,
          'confirmation_channel': null,
          'created_by': r.member['id'],
          'created_at': _iso(state.now()),
          'items': <Object?>[],
          'payments': <Object?>[],
          'source': 'phone',
        };
    final String? event =
        d['event_type'] as String? ?? b['event_type'] as String?;
    final String? source = d['source'] as String? ?? b['source'] as String?;
    b
      ..['dining_table_id'] = table
      ..['table_ids'] = tableIds
      ..['starts_at'] = _iso(starts)
      ..['ends_at'] = _iso(ends)
      ..['guests_planned'] = d['guests_planned'] ?? b['guests_planned']
      ..['event_type'] = event
      ..['event_type_label'] = _eventLabels[event]
      ..['contact_name'] = d.containsKey('contact_name')
          ? d['contact_name']
          : b['contact_name']
      ..['contact_phone'] = phone
      ..['source'] = source
      ..['source_label'] = const <String, String>{
        'phone': 'Telefon',
        'telegram': 'Telegram',
        'instagram': 'Instagram',
        'walk_in': "O'zi keldi",
        'other': 'Boshqa',
      }[source]
      ..['min_spend'] = d.containsKey('min_spend')
          ? d['min_spend']
          : b['min_spend']
      ..['note'] = d.containsKey('note') ? d['note'] : b['note'];
    bookings['${b['uuid']}'] = b;
    _touchBooking(b);
    if (existing == null && b['status'] == 'confirmed') {
      _autoSendConfirmation(b);
    }
    return _ok(
      b,
      status: existing == null ? 201 : 200,
      message: existing == null ? 'Bron yaratildi.' : 'Bron saqlandi.',
    );
  }

  FakeResponse _arrive(Map<String, Object?> b, FakeRequest r) {
    if (b['order_uuid'] != null) {
      return FakeResponse(409, <String, Object?>{
        'success': false,
        'message': 'Mehmonlar allaqachon kelgan: buyurtma ochiq.',
        'data': <String, Object?>{
          'reason': 'booking_already_arrived',
          'order_uuid': b['order_uuid'],
        },
      });
    }
    if (b['status'] != 'requested' && b['status'] != 'confirmed') {
      return _transition();
    }
    final int? table =
        (r.data['dining_table_id'] as num?)?.toInt() ??
        b['dining_table_id'] as int?;
    final int guests =
        (r.data['guests'] as num?)?.toInt() ??
        (b['guests_planned'] as int?) ??
        0;
    final List<Map<String, Object?>> pre = (b['items']! as List<Object?>)
        .cast<Map<String, Object?>>();
    final String uuid = state.seedOrder(
      tableId: table,
      guests: guests,
      lines: <(int, String)>[
        for (final Map<String, Object?> i in pre)
          (i['product_variant_id']! as int, '${i['quantity']}'),
      ],
      sent: false,
      age: Duration.zero,
    );
    final Map<String, Object?> order = state.orders[uuid]!;
    order['booking_id'] = 1;
    final List<Map<String, Object?>> lines = _items(order);
    for (var k = 0; k < lines.length && k < pre.length; k++) {
      lines[k]
        ..['fire_mode'] = 'hold'
        ..['due_at'] = pre[k]['due_at'];
    }
    if (pre.isEmpty) order['rounds'] = 0;
    b
      ..['status'] = 'arrived'
      ..['order_uuid'] = uuid;
    _touchBooking(b);
    state.emit('order.created', <String, Object?>{
      'order': <String, Object?>{
        'uuid': uuid,
        'status': 'accepted',
        'branch_id': FakePosState.branchId,
      },
    });
    return _ok(<String, Object?>{
      'booking': b,
      'order': <String, Object?>{'uuid': uuid, 'status': order['status']},
    }, message: 'Mehmonlar keldi: buyurtma ochildi.');
  }

  FakeResponse _addBookingItem(Map<String, Object?> b, FakeRequest r) {
    final int id = ((r.data['product_variant_id'] as num?) ?? 0).toInt();
    for (final Map<String, Object?> p in state.products) {
      for (final Object? v in p['variants']! as List<Object?>) {
        final Map<String, Object?> variant = (v! as Map<Object?, Object?>)
            .cast<String, Object?>();
        if (variant['id'] != id) continue;
        final int qty = _milli(r.data['quantity']);
        final int min = _milli(variant['min_quantity'] ?? '0');
        if (qty <= 0 || qty < min) {
          return _validation(
            'quantity',
            "${p['name']} kamida ${min / 1000} ${variant['unit']} buyurtma qilinadi.",
          );
        }
        (b['items']! as List<Object?>).add(<String, Object?>{
          'id': ++_bookingItemSeq,
          'product_variant_id': id,
          'product_name': p['name'],
          'variant_name': variant['name'],
          'unit': variant['unit'],
          'quantity': qty / 1000,
          'unit_price': variant['price'],
          'modifiers': <Object?>[],
          'note': r.data['note'],
          'due_at': r.data['due_at'] ?? b['starts_at'],
        });
        _touchBooking(b);
        return _ok(b, status: 201, message: "Qo'shildi.");
      }
    }
    return _fail(422, 'variant_unavailable', 'Taom topilmadi.');
  }

  FakeResponse _takeDeposit(Map<String, Object?> b, FakeRequest r) {
    final String? key = r.idempotencyKey;
    if (key != null && _depositByKey.containsKey(key)) {
      return _ok(<String, Object?>{
        'booking': b,
        'payment': _depositByKey[key],
        'fiscal': _fiscalOf(
          _depositByKey[key]!['fiscal_receipt_uuid'] as String?,
        ),
      });
    }
    if (b['status'] != 'requested' && b['status'] != 'confirmed') {
      return _transition();
    }
    final int amount = _t(r.data['amount']);
    if (amount <= 0) return _validation('amount', 'Summani kiriting.');
    final String tender = '${r.data['tender']}';
    if (tender == 'card' &&
        ('${r.data['card_type'] ?? ''}'.isEmpty ||
            '${r.data['transaction_id'] ?? ''}'.trim().isEmpty)) {
      return _fail(
        422,
        'card_type_required',
        'Karta turi va tranzaksiya raqamini kiriting.',
      );
    }
    final String receipt = _uuid('r');
    final Map<String, Object?> payment = <String, Object?>{
      'uuid': _uuid('d'),
      'amount': _m(amount),
      'tender': tender,
      'card_type': r.data['card_type'],
      'transaction_id': r.data['transaction_id'],
      'status': 'taken',
      'status_label': 'Olingan',
      'remaining': _m(amount),
      'applied_amount': '0.00',
      'refunded_amount': '0.00',
      'retained_amount': '0.00',
      'fiscal_receipt_uuid': receipt,
      'fiscal_status': 'issued',
      'created_at': _iso(state.now()),
      'shift_uuid': state.shift?['uuid'],
    };
    receipts[receipt] = _simpleReceipt(
      receipt,
      'advance',
      amount,
      'Avans (bron)',
      tender,
      prepayment: false,
    );
    (b['payments']! as List<Object?>).add(payment);
    if (key != null) _depositByKey[key] = payment;
    if (b['status'] == 'requested') b['status'] = 'confirmed';
    _recountDeposits(b);
    _touchBooking(b);
    return _ok(
      <String, Object?>{
        'booking': b,
        'payment': payment,
        'fiscal': _fiscalOf(receipt),
      },
      status: 201,
      message: 'Avans qabul qilindi.',
    );
  }

  void _recountDeposits(Map<String, Object?> b) {
    int total = 0;
    int available = 0;
    for (final Map<String, Object?> p
        in (b['payments']! as List<Object?>).cast<Map<String, Object?>>()) {
      total += _t(p['amount']);
      available += _t(p['remaining']);
    }
    b
      ..['deposit_total'] = _m(total)
      ..['deposit_available'] = _m(available)
      ..['deposit_unsettled'] =
          available > 0 &&
          const <String>[
            'canceled',
            'no_show',
            'completed',
          ].contains(b['status']);
  }

  void _applyDeposit(Map<String, Object?> b, int amount) {
    int left = amount;
    for (final Map<String, Object?> p
        in (b['payments']! as List<Object?>).cast<Map<String, Object?>>()) {
      final int take = math.min(left, _t(p['remaining']));
      if (take <= 0) continue;
      p
        ..['remaining'] = _m(_t(p['remaining']) - take)
        ..['applied_amount'] = _m(_t(p['applied_amount']) + take)
        ..['status'] = _t(p['remaining']) - take == 0 ? 'applied' : p['status'];
      left -= take;
    }
    _recountDeposits(b);
  }

  FakeResponse _settleDeposit(
    Map<String, Object?> b,
    String paymentUuid,
    String op,
    FakeRequest r,
  ) {
    final FakeResponse? denied = state.authorize(
      r.member,
      op == 'refund' ? 'pos.refund' : 'orders.cancel',
      r.data['approval_token'],
    );
    if (denied != null) return denied;
    if ('${r.data['reason'] ?? ''}'.trim().isEmpty) {
      return _validation('reason', 'Sababini yozing.');
    }
    if (op == 'refund' && b['status'] == 'arrived') return _transition();
    if (op == 'retain' &&
        !const <String>[
          'canceled',
          'no_show',
          'completed',
        ].contains(b['status'])) {
      return _transition();
    }
    for (final Map<String, Object?> p
        in (b['payments']! as List<Object?>).cast<Map<String, Object?>>()) {
      if (p['uuid'] != paymentUuid) continue;
      final int left = _t(p['remaining']);
      if (left <= 0) {
        return _fail(422, 'deposit_settled', 'Avans qoldig\'i yo\'q.');
      }
      p
        ..['remaining'] = '0.00'
        ..[op == 'refund' ? 'refunded_amount' : 'retained_amount'] = _m(left)
        ..['status'] = op == 'refund' ? 'refunded' : 'retained'
        ..['status_label'] = op == 'refund' ? 'Qaytarilgan' : 'Ushlab qolingan';
      if (op == 'refund') p['refunded_shift_uuid'] = state.shift?['uuid'];
      _recountDeposits(b);
      _touchBooking(b);
      return _ok(<String, Object?>{
        'booking': b,
        'payment': p,
        'fiscal': _fiscalOf(null),
      });
    }
    return _fail(404, null, 'Avans topilmadi.');
  }

  // --- nasiya ----------------------------------------------------------------------------------------------------------

  FakeResponse? _accounts(FakeRequest r) {
    final String p = r.path;
    final String m = r.method;
    final List<String> perms = state.permissionsOf(r.member);
    final bool manage = perms.contains('customer_accounts.manage');
    if (!manage && !perms.contains('pos.take_payment')) {
      return _fail(403, null, "Bu amal uchun ruxsat yo'q.");
    }
    if (m == 'GET' && p == '/v1/pos/customer-accounts') {
      final String q = (r.query['q'] ?? '').toLowerCase();
      final String digits = q.replaceAll(RegExp(r'\D'), '');
      final List<Map<String, Object?>> list = <Map<String, Object?>>[
        for (final Map<String, Object?> a in accounts.values)
          if ((q.isEmpty ||
                  '${a['name']}'.toLowerCase().contains(q) ||
                  '${a['group_name'] ?? ''}'.toLowerCase().contains(q) ||
                  (digits.length >= 3 && '${a['phone']}'.contains(digits))) &&
              (r.query['debtors'] != '1' || _t(a['balance']) > 0))
            a,
      ];
      return _ok(<String, Object?>{
        'accounts': list,
        'meta': <String, Object?>{
          'current_page': 1,
          'last_page': 1,
          'per_page': 30,
          'total': list.length,
        },
      });
    }
    if (m == 'GET' && p == '/v1/pos/customer-accounts/debtors') {
      final List<Map<String, Object?>> list =
          <Map<String, Object?>>[
            for (final Map<String, Object?> a in accounts.values)
              if (_t(a['balance']) > 0) a,
          ]..sort(
            (Map<String, Object?> a, Map<String, Object?> b) =>
                _t(b['balance']).compareTo(_t(a['balance'])),
          );
      return _ok(<String, Object?>{
        'total_debt': _m(
          list.fold(0, (int s, Map<String, Object?> a) => s + _t(a['balance'])),
        ),
        'count': list.length,
        'accounts': list,
      });
    }
    if (m == 'POST' && p == '/v1/pos/customer-accounts') {
      // P3-26: a cashier with a manager's approval for customer_accounts.manage
      final FakeResponse? denied = state.authorize(
        r.member,
        'customer_accounts.manage',
        r.data['approval_token'],
      );
      if (denied != null) return denied;
      return _saveAccount(null, r.data);
    }
    final RegExpMatch? x = RegExp(
      r'^/v1/pos/customer-accounts/([^/]+)(?:/(.+))?$',
    ).firstMatch(p);
    if (x == null) return null;
    final Map<String, Object?>? a = accounts[x.group(1)];
    if (a == null) return _fail(404, null, 'Hisob topilmadi.');
    final List<Map<String, Object?>> list = entries.putIfAbsent(
      '${a['uuid']}',
      () => <Map<String, Object?>>[],
    );
    switch ((m, x.group(2))) {
      case ('GET', null):
        return _ok(<String, Object?>{
          'account': a,
          'entries': list.reversed.take(20).toList(),
        });
      case ('PATCH', null):
        final FakeResponse? denied = state.authorize(
          r.member,
          'customer_accounts.manage',
          r.data['approval_token'],
        );
        if (denied != null) return denied;
        return _saveAccount(a, r.data);
      case ('GET', 'statement'):
        int sum(String type) => list
            .where((Map<String, Object?> e) => e['type'] == type)
            .fold(0, (int s, Map<String, Object?> e) => s + _t(e['amount']));
        return _ok(<String, Object?>{
          'account': a,
          'from': r.query['from'],
          'to': r.query['to'],
          'opening_balance': '0.00',
          'closing_balance': a['balance'],
          'charges_total': _m(sum('charge')),
          'repayments_total': _m(-sum('repayment')),
          'adjustments_total': _m(sum('adjustment')),
          'entries': list,
        });
      case ('POST', 'repayments'):
        final String? key = r.idempotencyKey;
        if (key != null && _repayByKey.containsKey(key)) {
          return _ok(<String, Object?>{
            'account': a,
            'entry': _repayByKey[key],
            'fiscal': _fiscalOf(
              _repayByKey[key]!['fiscal_receipt_uuid'] as String?,
            ),
          });
        }
        final int amount = _t(r.data['amount']);
        if (amount <= 0) return _validation('amount', 'Summani kiriting.');
        if (amount > _t(a['balance'])) {
          return _fail(
            422,
            'repayment_exceeds_debt',
            "To'lov qarzdan ko'p bo'lmaydi.",
          );
        }
        final String tender = '${r.data['tender']}';
        if (tender == 'card' &&
            ('${r.data['card_type'] ?? ''}'.isEmpty ||
                '${r.data['transaction_id'] ?? ''}'.trim().isEmpty)) {
          return _fail(
            422,
            'card_type_required',
            'Karta turi va tranzaksiya raqamini kiriting.',
          );
        }
        final Map<String, Object?> e = _entry(
          a,
          'repayment',
          -amount,
          user: r.member,
          tender: tender,
          note: r.data['note'] as String?,
        );
        if (key != null) _repayByKey[key] = e;
        return _ok(
          <String, Object?>{
            'account': a,
            'entry': e,
            'fiscal': _fiscalOf(e['fiscal_receipt_uuid'] as String?),
          },
          status: 201,
          message: "Qarz to'lovi qabul qilindi.",
        );
      default:
        return _fail(404, null, 'Topilmadi.');
    }
  }

  FakeResponse _saveAccount(
    Map<String, Object?>? existing,
    Map<String, Object?> d,
  ) {
    final String name = '${d['name'] ?? existing?['name'] ?? ''}'.trim();
    if (name.isEmpty) return _validation('name', 'Ismini yozing.');
    final String? phone = d.containsKey('phone')
        ? _phone(d['phone'])
        : existing?['phone'] as String?;
    if (d['phone'] != null && phone == null) {
      return _validation('phone', "Telefon raqami noto'g'ri.");
    }
    for (final Map<String, Object?> o in accounts.values) {
      if (o != existing && phone != null && o['phone'] == phone) {
        return _validation('phone', 'Bu telefon bilan hisob bor.');
      }
    }
    final Map<String, Object?> a =
        existing ??
        <String, Object?>{
          'uuid': _uuid('a'),
          'balance': '0.00',
          'branch_id': null,
          'is_blocked': false,
          'created_at': _iso(state.now()),
        };
    a
      ..['name'] = name
      ..['phone'] = phone
      ..['group_name'] = d.containsKey('group_name')
          ? d['group_name']
          : a['group_name']
      ..['credit_limit'] = d.containsKey('credit_limit')
          ? d['credit_limit']
          : a['credit_limit']
      ..['note'] = d.containsKey('note') ? d['note'] : a['note']
      ..['is_blocked'] = d['is_blocked'] ?? a['is_blocked']
      ..['updated_at'] = _iso(state.now());
    _available(a);
    accounts['${a['uuid']}'] = a;
    return _ok(a, status: existing == null ? 201 : 200, message: 'Saqlandi.');
  }

  void _available(Map<String, Object?> a) {
    final Object? limit = a['credit_limit'];
    a['available_credit'] = limit == null
        ? null
        : _m(math.max(0, _t(limit) - _t(a['balance'])));
  }

  Map<String, Object?> _entry(
    Map<String, Object?> a,
    String type,
    int amount, {
    String? billUuid,
    Map<String, Object?>? user,
    String? tender,
    String? note,
  }) {
    a['balance'] = _m(_t(a['balance']) + amount);
    _available(a);
    final String? receipt = type == 'repayment' ? _uuid('r') : null;
    if (receipt != null) {
      receipts[receipt] = _simpleReceipt(
        receipt,
        'credit',
        -amount,
        "Qarz to'lovi",
        tender ?? 'cash',
        prepayment: false,
      );
    }
    final Map<String, Object?> e = <String, Object?>{
      'uuid': _uuid('e'),
      'type': type,
      'type_label': type == 'charge'
          ? 'Nasiyaga yopilgan hisob'
          : (type == 'repayment' ? "Qarz to'lovi" : 'Tuzatish'),
      'amount': _m(amount),
      'balance_after': a['balance'],
      'bill_uuid': billUuid,
      'tender': tender,
      'card_type': null,
      'transaction_id': null,
      'fiscal_receipt_uuid': receipt,
      'fiscal_status': receipt == null ? null : 'issued',
      'branch_id': FakePosState.branchId,
      'user': user == null
          ? null
          : <String, Object?>{'id': user['id'], 'name': user['name']},
      'approved_by': null,
      'note': note,
      'created_at': _iso(state.now()),
      'shift_uuid': state.shift?['uuid'],
    };
    entries.putIfAbsent('${a['uuid']}', () => <Map<String, Object?>>[]).add(e);
    return e;
  }

  // --- onlayn buyurtmalar ----------------------------------------------------------------------------------------------

  /// Yakunlangan POS buyurtmasi `/staff/orders` ko'rinishida.
  Map<String, Object?> _staffRow(Map<String, Object?> o) => <String, Object?>{
    'uuid': o['uuid'],
    'short_code': o['short_code'],
    'status': o['status'],
    'status_label': o['status'] == 'delivered' ? 'Yetkazildi' : 'Bekor qilindi',
    'type': o['type'],
    'channel': o['channel'],
    'store_id': FakePosState.storeId,
    'branch_id': FakePosState.branchId,
    'payment_method': 'cash',
    'payment_status': o['status'] == 'delivered' ? 'paid' : 'pending',
    'subtotal': o['subtotal'],
    'delivery_price': '0.00',
    'total_price': o['total_price'],
    'notes': null,
    'guests_count': o['guests_count'],
    'hall_ref': o['hall_ref'],
    'table_ref': o['table_ref'],
    'customer': null,
    'delivery': null,
    'items': <Object?>[],
    'created_at': o['opened_at'],
    'updated_at': o['updated_at'],
    'closed_at': o['closed_at'],
    'waiting_seconds': 0,
  };

  FakeResponse _staffOrders(FakeRequest r) {
    if (r.query['scope'] == 'history') {
      final List<Map<String, Object?>> done = <Map<String, Object?>>[
        for (final Map<String, Object?> o
            in state.orders.values.toList().reversed)
          if ((o['status'] == 'delivered' || o['status'] == 'canceled') &&
              (r.query['channel'] == null ||
                  o['channel'] == r.query['channel']))
            _staffRow(o),
        for (final Map<String, Object?> o in onlineOrders.reversed)
          if (o['status'] == 'rejected' && r.query['channel'] == null) o,
      ];
      return FakeResponse(200, <String, Object?>{
        'success': true,
        'message': '',
        'data': done,
        'meta': <String, Object?>{'next_cursor': null, 'per_page': 100},
      });
    }
    final Set<String> statuses = (r.query['status'] ?? '')
        .split(',')
        .where((String s) => s.isNotEmpty)
        .toSet();
    final List<Map<String, Object?>> list = <Map<String, Object?>>[
      for (final Map<String, Object?> o in onlineOrders.reversed)
        if (statuses.isEmpty || statuses.contains(o['status'])) o,
    ];
    return FakeResponse(200, <String, Object?>{
      'success': true,
      'message': '',
      'data': list,
      'meta': <String, Object?>{'next_cursor': null, 'per_page': 50},
    });
  }

  FakeResponse _staffStatus(String uuid, FakeRequest r) {
    Map<String, Object?>? order;
    for (final Map<String, Object?> o in onlineOrders) {
      if (o['uuid'] == uuid) order = o;
    }
    if (order == null) return _fail(404, null, 'Buyurtma topilmadi.');
    final String status = '${r.data['status']}';
    final List<String> perms = state.permissionsOf(r.member);
    if ((status == 'rejected' || status == 'canceled') &&
        !perms.contains('orders.cancel')) {
      return _fail(403, null, "Bu amal uchun ruxsat yo'q.");
    }
    if (status == 'accepted' && !perms.contains('orders.manage')) {
      return _fail(403, null, "Bu amal uchun ruxsat yo'q.");
    }
    if (order['status'] != 'new') {
      return _fail(
        422,
        null,
        "Buyurtma holatini bunday o'zgartirib bo'lmaydi.",
      );
    }
    order
      ..['status'] = status
      ..['status_label'] = status == 'accepted' ? 'Qabul qilindi' : 'Rad etildi'
      ..['cancel_reason'] = r.data['reason']
      ..['updated_at'] = _iso(state.now());
    state.emit('order.status_changed', <String, Object?>{
      'order': <String, Object?>{
        'uuid': uuid,
        'status': status,
        'store_id': FakePosState.storeId,
        'branch_id': FakePosState.branchId,
        'courier_id': null,
        'updated_at': order['updated_at'],
      },
    });
    return _ok(order);
  }

  /// Telegram yoki mobil ilovadan yangi buyurtma ([minutesAgo] daqiqa oldin kelgan).
  String seedOnlineOrder({
    String channel = 'telegram',
    String type = 'pickup',
    int minutesAgo = 0,
    String customer = 'Aziz',
    List<(String name, String quantity, String price)> items =
        const <(String, String, String)>[
          ('Osh (Choyxona oshi)', '1 kg (guruch)', '120000.00'),
          ('Non (Dona)', '2', '8000.00'),
        ],
    bool emit = true,
  }) {
    final String uuid = _uuid('o');
    final DateTime at = state.now().subtract(Duration(minutes: minutesAgo));
    final int total = items.fold(
      0,
      (int s, (String, String, String) i) => s + _t(i.$3),
    );
    onlineOrders.add(<String, Object?>{
      'uuid': uuid,
      'short_code': 'T${100 + onlineOrders.length + 1}',
      'status': 'new',
      'status_label': 'Yangi',
      'type': type,
      'channel': channel,
      'store_id': FakePosState.storeId,
      'branch_id': FakePosState.branchId,
      'branch_name': 'Chilonzor',
      'payment_method': 'cash',
      'payment_status': 'pending',
      'subtotal': _m(total),
      'discount': '0.00',
      'delivery_price': '0.00',
      'service_charge_amount': '0.00',
      'place_fee_amount': '0.00',
      'total_price': _m(total),
      'notes': null,
      'guests_count': null,
      'hall_ref': null,
      'table_ref': null,
      'cancel_reason': null,
      'cancel_note': null,
      'customer': <String, Object?>{'name': customer, 'phone': '+998901234567'},
      'delivery': type == 'delivery'
          ? <String, Object?>{'address': 'Chilonzor 9-kvartal, 12-uy'}
          : null,
      'courier': null,
      'items_total': items.length,
      'items_ready': 0,
      'items': <Object?>[
        for (final (String name, String q, String price) in items)
          <String, Object?>{
            'id': ++_bookingItemSeq,
            'product_name': name,
            'variant_name': null,
            'quantity': 1,
            'unit': 'piece',
            'quantity_label': q,
            'modifiers': <Object?>[],
            'kitchen_status': 'pending',
            'ready_at': null,
            'total_price': price,
          },
      ],
      'created_at': _iso(at),
      'updated_at': _iso(at),
      'accepted_at': null,
      'preparing_at': null,
      'ready_at': null,
      'closed_at': null,
      'waiting_seconds': minutesAgo * 60,
    });
    if (emit) {
      state.emit('order.created', <String, Object?>{
        'order': <String, Object?>{
          'uuid': uuid,
          'status': 'new',
          'store_id': FakePosState.storeId,
          'branch_id': FakePosState.branchId,
          'courier_id': null,
          'updated_at': _iso(at),
        },
      });
    }
    return uuid;
  }

  // --- tayyor ma'lumotlar ----------------------------------------------------------------------------------------------

  /// Nasiya hisobi ([debt] — qarz).
  String seedAccount({
    required String name,
    String? phone,
    String? group,
    String? limit,
    String debt = '0.00',
    bool blocked = false,
  }) {
    final String uuid = _uuid('a');
    final Map<String, Object?> a = <String, Object?>{
      'uuid': uuid,
      'name': name,
      'phone': phone,
      'group_name': group,
      'branch_id': null,
      'credit_limit': limit,
      'balance': '0.00',
      'is_blocked': blocked,
      'note': null,
      'created_at': _iso(state.now().subtract(const Duration(days: 30))),
      'updated_at': _iso(state.now()),
    };
    accounts[uuid] = a;
    _available(a);
    if (_t(debt) > 0) _entry(a, 'charge', _t(debt));
    return uuid;
  }

  /// Bron ([start] — UTC, [hours] davomiylik).
  String seedBooking({
    required int tableId,
    required DateTime start,
    int hours = 5,
    String name = 'Jamshid',
    String phone = '+998901234567',
    int guests = 8,
    String event = 'gap',
    String status = 'confirmed',
    String? deposit,
  }) {
    final String uuid = _uuid('k');
    final Map<String, Object?> b = <String, Object?>{
      'uuid': uuid,
      'branch_id': FakePosState.branchId,
      'dining_table_id': tableId,
      'table_ids': <int>[tableId],
      'starts_at': _iso(start),
      'ends_at': _iso(start.add(Duration(hours: hours))),
      'guests_planned': guests,
      'event_type': event,
      'event_type_label': _eventLabels[event],
      'contact_name': name,
      'contact_phone': phone,
      'source': 'phone',
      'source_label': 'Telefon',
      'status': status,
      'status_label': _bookingStatusLabels[status],
      'min_spend': null,
      'note': null,
      'order_uuid': null,
      'deposit_total': '0.00',
      'deposit_available': '0.00',
      'deposit_unsettled': false,
      'items_count': 0,
      'cancel_reason': null,
      'confirmation_sent_at': null,
      'confirmation_channel': null,
      'version': 1,
      'created_by': 3,
      'created_at': _iso(state.now()),
      'updated_at': _iso(state.now()),
      'items': <Object?>[],
      'payments': <Object?>[],
    };
    bookings[uuid] = b;
    if (deposit != null) {
      (b['payments']! as List<Object?>).add(<String, Object?>{
        'uuid': _uuid('d'),
        'amount': deposit,
        'tender': 'cash',
        'card_type': null,
        'transaction_id': null,
        'status': 'taken',
        'status_label': 'Olingan',
        'remaining': deposit,
        'applied_amount': '0.00',
        'refunded_amount': '0.00',
        'retained_amount': '0.00',
        'fiscal_receipt_uuid': _uuid('r'),
        'fiscal_status': 'issued',
        'created_at': _iso(state.now()),
        'shift_uuid': null,
      });
      _recountDeposits(b);
    }
    return uuid;
  }

  /// Ekran suratlari va qo'lda sinash uchun: ochiq smena, band xonalar, bronlar, qarzdorlar, onlayn buyurtmalar.
  void seedDemo() {
    if (state.shift == null) state.openShift();
    final DateTime now = state.now();
    // Toshkent kuni boshi (UTC 19:00 oldingi kun) + soat
    final DateTime localMidnight = DateTime.utc(
      now.toUtc().add(const Duration(hours: 5)).year,
      now.toUtc().add(const Duration(hours: 5)).month,
      now.toUtc().add(const Duration(hours: 5)).day,
    ).subtract(const Duration(hours: 5));
    DateTime at(int day, int hour) =>
        localMidnight.add(Duration(days: day, hours: hour));
    seedBooking(
      tableId: 2,
      start: at(0, 18),
      name: 'Jamshid aka',
      guests: 12,
      deposit: '1000000.00',
    );
    seedBooking(
      tableId: 4,
      start: at(0, 19),
      name: 'Dilshod',
      guests: 8,
      event: 'tugilgan_kun',
      status: 'requested',
    );
    seedBooking(
      tableId: 11,
      start: at(0, 13),
      hours: 3,
      name: 'Olimjon',
      guests: 5,
      event: 'nahor_oshi',
    );
    seedBooking(
      tableId: 1,
      start: at(1, 18),
      name: 'Bobur',
      guests: 10,
      event: 'ziyofat',
      deposit: '500000.00',
    );
    seedAccount(
      name: 'Jamshid aka',
      phone: '+998901112233',
      group: 'Gap guruhi',
      limit: '2000000.00',
      debt: '850000.00',
    );
    seedAccount(
      name: 'Sardor Rahimov',
      phone: '+998935554433',
      limit: '1000000.00',
      debt: '320000.00',
    );
    seedAccount(
      name: "Bekzod (qo'shni do'kon)",
      phone: '+998977001122',
      debt: '145000.00',
    );
    seedAccount(
      name: 'Ulug\'bek',
      phone: '+998909998877',
      limit: '500000.00',
      debt: '500000.00',
      blocked: true,
    );
    seedOnlineOrder(minutesAgo: 6, customer: 'Aziz', emit: false);
    seedOnlineOrder(
      channel: 'mobile',
      type: 'delivery',
      minutesAgo: 1,
      customer: 'Nodira',
      items: const <(String, String, String)>[
        ('Shashlik (Six)', '6', '108000.00'),
        ('Choy (Choynak)', '1', '5000.00'),
      ],
      emit: false,
    );
  }

  // --- yordamchi -------------------------------------------------------------------------------------------------------

  String _uuid(String kind) {
    final int n = ++_seq;
    final String hex = kind.codeUnitAt(0).toRadixString(16).padLeft(2, '0');
    return '${hex}00${n.toString().padLeft(4, '0')}-0000-4000-8000-${n.toString().padLeft(12, '0')}';
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

  static FakeResponse _validation(String field, String message) =>
      FakeResponse(422, <String, Object?>{
        'success': false,
        'message': message,
        'errors': <String, Object?>{
          field: <String>[message],
        },
      });

  static String _iso(DateTime d) => d.toUtc().toIso8601String();

  static String _date(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  /// Pul → tiyin.
  static int _t(Object? v) {
    if (v == null) return 0;
    if (v is num) return (v * 100).round();
    final String s = '$v'.trim();
    if (s.isEmpty) return 0;
    return (double.parse(s) * 100).round();
  }

  static String _m(int tiyin) {
    final String sign = tiyin < 0 ? '-' : '';
    final int a = tiyin.abs();
    return '$sign${a ~/ 100}.${(a % 100).toString().padLeft(2, '0')}';
  }

  /// Miqdor → mingdan bir.
  static int _milli(Object? v) {
    if (v == null) return 0;
    if (v is num) return (v * 1000).round();
    return (double.parse('$v') * 1000).round();
  }
}

/// Soxta fiskal provayder.
enum FakeFiscalMode {
  /// Chek darhol chiqadi.
  issued,

  /// Chek kutilmoqda ("fiskal kutmoqda"): `fiscal-retry` yoki [FakeCashierBackend.issuePending] chiqaradi.
  pending,
}
