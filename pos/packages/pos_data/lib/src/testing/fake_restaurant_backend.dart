import 'fake_cashier_backend.dart';
import 'fake_pos_state.dart';

/// Soxta backend'ning restoran qismi (P6-22 … P6-25, kassa testlari uchun): to'lovdagi choy puli (`tip_amount`,
/// `tip_from_change`, `tip_staff_id`; schyotning `tips_amount`/`tips`), `GET /pos/tips`, X/Z ning `tips` bo'limi va
/// `cash.tips`/`cash.tip_payouts` (choy pulini berish — `wage`/`tips` chiqimi), mehmon bo'yicha bo'lish (`by_guest`),
/// og'irlik kutilayotganda schyot rad etilishi (409 `weight_pending`), qatorga mehmon raqami va ish vaqti (`/pos/clock`).
/// Qolgani [FakeCashierBackend] ga o'tadi:
///
/// ```dart
/// final cashier = FakeCashierBackend(adapter.state);
/// final restaurant = FakeRestaurantBackend(cashier);   // state.extraRoutes endi shu
/// ```
class FakeRestaurantBackend {
  FakeRestaurantBackend(this.cashier) {
    cashier.state.extraRoutes = handle;
  }

  final FakeCashierBackend cashier;

  FakePosState get state => cashier.state;

  /// Olingan choy pullari (`pos_tips` qatorlari: `uuid`, `bill_uuid`, `payment_uuid`, `shift_uuid`, `method`,
  /// `source`, `amount`, `mode`, `staff_id`).
  final List<Map<String, Object?>> tips = <Map<String, Object?>>[];

  /// Ish vaqtlari (xodim id → ochiq ish vaqti).
  final Map<int, Map<String, Object?>> openSessions =
      <int, Map<String, Object?>>{};

  /// Yopilgan ish vaqtlari (daqiqa, xodim bo'yicha).
  final Map<int, int> closedMinutes = <int, int>{};

  /// Schyotdagi xizmat haqi e'loni (`service_charge_notice`: xizmat haqi yoki joy narxi bor schyotlarga); `null` —
  /// yo'q.
  String? serviceChargeNotice;

  final Set<Map<String, Object?>> _patched =
      Set<Map<String, Object?>>.identity();
  int _seq = 0;

  FakeResponse? handle(FakeRequest r) {
    final String p = r.path;
    final String m = r.method;
    RegExpMatch? x;

    if (m == 'POST' &&
        (x = RegExp(r'^/v1/pos/bills/([^/]+)/payments$').firstMatch(p)) !=
            null) {
      final FakeResponse? tip = _payWithTips(x!.group(1)!, r);
      if (tip != null) return tip;
    }
    if ((x = RegExp(r'^/v1/pos/orders/([^/]+)/bills$').firstMatch(p)) != null) {
      final Map<String, Object?>? order = state.orders[x!.group(1)];
      if (m == 'POST' && order != null) {
        final FakeResponse? pending = _weightPending(order);
        if (pending != null) return pending;
        if (r.data['mode'] == 'by_guest') return _byGuest(order, r);
      }
    }
    if (m == 'POST' &&
        (x = RegExp(r'^/v1/pos/bills/([^/]+)/print$').firstMatch(p)) != null) {
      final Map<String, Object?>? order = _orderOfBill(x!.group(1)!);
      if (order != null) {
        final FakeResponse? pending = _weightPending(order);
        if (pending != null) return pending;
      }
    }
    if (m == 'POST' &&
        (x = RegExp(r'^/v1/pos/orders/([^/]+)/items/(\d+)/seat$')
                .firstMatch(p)) !=
            null) {
      return _seat(x!.group(1)!, int.parse(x.group(2)!), r);
    }
    if (m == 'GET' && p == '/v1/pos/tips') return _shiftTips(r);
    if (p == '/v1/pos/clock' || p.startsWith('/v1/pos/clock/')) {
      return _clock(r);
    }

    final FakeResponse? res = cashier.handle(r);
    if (res != null) {
      _patchReports(res.body);
      _stampNotices();
    }
    return res;
  }

  void _stampNotices() {
    final String? notice = serviceChargeNotice;
    if (notice == null) return;
    for (final List<Map<String, Object?>> list in cashier.bills.values) {
      for (final Map<String, Object?> b in list) {
        final bool charged =
            _t(b['service_charge_amount']) > 0 || _t(b['place_fee_amount']) > 0;
        b['service_charge_notice'] = charged ? notice : null;
      }
    }
  }

  // --- modullar ------------------------------------------------------------------------------------------------------

  bool _module(String key) {
    final Map<String, Object?>? format = state.format;
    if (format == null) return true;
    final Object? modules = format['modules'];
    return modules is List && modules.contains(key);
  }

  static FakeResponse _moduleDisabled(String module) => FakeResponse(
    403,
    <String, Object?>{
      'success': false,
      'message': "Bu modul filialda o'chirilgan.",
      'code': 'module_disabled',
      'data': <String, Object?>{'reason': 'module_disabled', 'module': module},
    },
  );

  static FakeResponse _ok(Object? data, {int status = 200, String m = ''}) =>
      FakeResponse(status, <String, Object?>{
        'success': true,
        'message': m,
        'data': data,
      });

  static FakeResponse _fail(
    int status,
    String reason,
    String message, [
    Map<String, Object?> extra = const <String, Object?>{},
  ]) => FakeResponse(status, <String, Object?>{
    'success': false,
    'message': message,
    'data': <String, Object?>{'reason': reason, ...extra},
  });

  // --- choy puli -----------------------------------------------------------------------------------------------------

  Map<String, Object?> _tipSettings() {
    final Object? t = state.bootstrapExtras['tips'];
    return t is Map ? t.cast<String, Object?>() : const <String, Object?>{};
  }

  FakeResponse? _payWithTips(String billUuid, FakeRequest r) {
    final List<Map<String, Object?>> tenders = <Map<String, Object?>>[
      for (final Object? t in (r.data['tenders'] as List<Object?>?) ?? [])
        if (t is Map) t.cast<String, Object?>(),
    ];
    final List<int> tipOf = <int>[];
    for (final Map<String, Object?> t in tenders) {
      final int amount = _t(t['amount']);
      final int? given = t['tendered_amount'] == null
          ? null
          : _t(t['tendered_amount']);
      var tip = _t(t['tip_amount']);
      if (t['tip_from_change'] == true) {
        if (t['tender'] != 'cash' || given == null) {
          return _fail(
            422,
            'tip_from_change_invalid',
            "Qaytimni choy puli qilish faqat naqdda, berilgan pul bilan.",
          );
        }
        if (t['tip_amount'] == null) tip = given - amount;
      }
      if (tip > 0 && t['tender'] == 'cash' && given != null) {
        if (given < amount + tip) {
          return _fail(
            422,
            'tendered_too_small',
            "Berilgan pul to'lov va choy pulini qoplamaydi.",
          );
        }
      }
      tipOf.add(tip < 0 ? 0 : tip);
    }
    if (!tipOf.any((int t) => t > 0)) return null;
    if (!_module('tips')) return _moduleDisabled('tips');

    final (Map<String, Object?>, Map<String, Object?>)? found = _findBill(
      billUuid,
    );
    if (found == null) return null;
    final Map<String, Object?> bill = found.$1;
    final Map<String, Object?> order = found.$2;
    final int before = (bill['payments']! as List<Object?>).length;
    final FakeResponse? res = cashier.handle(r);
    if (res == null || res.status >= 300) return res;
    final List<Map<String, Object?>> payments =
        (bill['payments']! as List<Object?>).cast<Map<String, Object?>>();
    final bool personal = _tipSettings()['mode'] != 'pool';
    final int? staffId = personal
        ? ((r.data['tip_staff_id'] as num?)?.toInt() ??
              ((order['waiter'] as Map<String, Object?>?)?['id'] as int?))
        : null;
    for (var i = 0; i < tenders.length; i++) {
      final Map<String, Object?> pay = payments[before + i];
      final int tip = tipOf[i];
      pay['tip_amount'] = _m(tip);
      final Object? given = tenders[i]['tendered_amount'];
      if (given != null) {
        final int change = _t(given) - _t(pay['amount']) - tip;
        pay['change_amount'] = _m(change < 0 ? 0 : change);
      }
      if (tip <= 0) continue;
      tips.add(<String, Object?>{
        'uuid': 'tip-${++_seq}',
        'method': tenders[i]['tender'],
        'source': tenders[i]['tip_from_change'] == true ? 'change' : 'tender',
        'amount': _m(tip),
        'original_amount': _m(tip),
        'mode': personal ? 'personal' : 'pool',
        'staff': staffId == null ? null : _staffRef(staffId),
        'staff_id': staffId,
        'bill_uuid': bill['uuid'],
        'payment_uuid': pay['uuid'],
        'shift_uuid': pay['shift_uuid'],
        'business_date': state.shift?['business_date'],
        'closed': false,
        'corrections': <Object?>[],
      });
    }
    _billTips(bill);
    return res;
  }

  void _billTips(Map<String, Object?> bill) {
    final List<Map<String, Object?>> own = <Map<String, Object?>>[
      for (final Map<String, Object?> t in tips)
        if (t['bill_uuid'] == bill['uuid']) t,
    ];
    bill['tips'] = own;
    bill['tips_amount'] = _m(
      own.fold(0, (int s, Map<String, Object?> t) => s + _t(t['amount'])),
    );
  }

  Map<String, Object?>? _staffRef(int id) {
    for (final Map<String, Object?> s in state.staff) {
      if (s['id'] == id) return <String, Object?>{'id': id, 'name': s['name']};
    }
    return <String, Object?>{'id': id, 'name': null};
  }

  Map<String, Object?> _summary(Iterable<Map<String, Object?>> list) {
    int sum(bool Function(Map<String, Object?> t) f) => list
        .where(f)
        .fold(0, (int s, Map<String, Object?> t) => s + _t(t['amount']));
    final Map<int, int> byStaff = <int, int>{};
    for (final Map<String, Object?> t in list) {
      final int? id = t['staff_id'] as int?;
      if (id != null) byStaff[id] = (byStaff[id] ?? 0) + _t(t['amount']);
    }
    return <String, Object?>{
      'count': list.length,
      'amount': _m(sum((_) => true)),
      'by_method': <Object?>[
        for (final String method in <String>['cash', 'card', 'qr'])
          if (sum((Map<String, Object?> t) => t['method'] == method) > 0)
            <String, Object?>{
              'method': method,
              'count': list.where((t) => t['method'] == method).length,
              'amount': _m(
                sum((Map<String, Object?> t) => t['method'] == method),
              ),
            },
      ],
      'personal': _m(
        sum(
          (Map<String, Object?> t) =>
              t['mode'] == 'personal' && t['staff_id'] != null,
        ),
      ),
      'pool': _m(sum((Map<String, Object?> t) => t['mode'] == 'pool')),
      'unassigned': _m(
        sum(
          (Map<String, Object?> t) =>
              t['mode'] == 'personal' && t['staff_id'] == null,
        ),
      ),
      'by_staff': <Object?>[
        for (final MapEntry<int, int> e in byStaff.entries)
          <String, Object?>{
            'staff_id': e.key,
            'name': _staffRef(e.key)?['name'],
            'amount': _m(e.value),
          },
      ],
      'corrections': <String, Object?>{'count': 0, 'amount': '0.00'},
    };
  }

  FakeResponse _shiftTips(FakeRequest r) {
    final String? shift =
        r.query['shift_uuid'] ?? state.shift?['uuid'] as String?;
    final List<String> perms = state.permissionsOf(r.member);
    final bool all =
        perms.contains('tips.manage') ||
        perms.contains('pos.close_shift') ||
        perms.contains('reports.view');
    final List<Map<String, Object?>> list = <Map<String, Object?>>[
      for (final Map<String, Object?> t in tips)
        if (t['shift_uuid'] == shift &&
            (all || t['staff_id'] == r.member['id']))
          t,
    ];
    return _ok(<String, Object?>{
      'shift_uuid': shift,
      'business_date': state.shift?['business_date'],
      'own_only': !all,
      'summary': _summary(list),
      'tips': list,
    });
  }

  /// X/Z (`report`, Z ning `summary`): `tips` bo'limi, `cash.tips` (kutilgan naqdga qo'shiladi) va `cash.tip_payouts`
  /// (`wage`/`tips` chiqimi — `cash.wages` ga kirmaydi).
  void _patchReports(Object? body) {
    if (body is! Map) return;
    final Object? data = body['data'];
    if (data is! Map) return;
    for (final Object? report in <Object?>[data['report'], data['summary']]) {
      if (report is Map<String, Object?>) _patchReport(report, data);
    }
  }

  void _patchReport(Map<String, Object?> report, Map<Object?, Object?> data) {
    if (!_patched.add(report)) return;
    final Object shift = data['shift'] ?? data;
    final String? uuid = shift is Map ? shift['uuid'] as String? : null;
    final List<Map<String, Object?>> list = <Map<String, Object?>>[
      for (final Map<String, Object?> t in tips)
        if (t['shift_uuid'] == uuid) t,
    ];
    report['tips'] = _summary(list);
    final Map<String, Object?>? cash = (report['cash'] as Map?)
        ?.cast<String, Object?>();
    if (cash == null) return;
    final int cashTips = list
        .where((Map<String, Object?> t) => t['method'] == 'cash')
        .fold(0, (int s, Map<String, Object?> t) => s + _t(t['amount']));
    final int payouts = cashier.movements
        .where(
          (Map<String, Object?> mv) =>
              mv['type'] == 'wage' &&
              mv['category'] == 'tips' &&
              (mv['shift_uuid'] == null || mv['shift_uuid'] == uuid),
        )
        .fold(0, (int s, Map<String, Object?> mv) => s + _t(mv['amount']));
    cash
      ..['tips'] = _m(cashTips)
      ..['tip_payouts'] = _m(payouts)
      ..['wages'] = _m(_t(cash['wages']) - payouts)
      ..['expected'] = _m(_t(cash['expected']) + cashTips);
    report['cash'] = cash;
  }

  // --- schyot ----------------------------------------------------------------------------------------------------------

  (Map<String, Object?>, Map<String, Object?>)? _findBill(String uuid) {
    for (final MapEntry<String, List<Map<String, Object?>>> e
        in cashier.bills.entries) {
      for (final Map<String, Object?> b in e.value) {
        if (b['uuid'] == uuid) return (b, state.orders[e.key]!);
      }
    }
    return null;
  }

  Map<String, Object?>? _orderOfBill(String uuid) => _findBill(uuid)?.$2;

  static List<Map<String, Object?>> _items(Map<String, Object?> order) =>
      ((order['items'] as List<Object?>?) ?? const <Object?>[])
          .whereType<Map<String, Object?>>()
          .toList();

  FakeResponse? _weightPending(Map<String, Object?> order) {
    final List<Map<String, Object?>> waiting = <Map<String, Object?>>[
      for (final Map<String, Object?> i in _items(order))
        if (i['kitchen_status'] != 'void' && i['weight_status'] == 'awaiting')
          i,
    ];
    if (waiting.isEmpty) return null;
    return _fail(
      409,
      'weight_pending',
      "Og'irligi kiritilmagan taom bor: avval tortib, og'irligini kiriting.",
      <String, Object?>{
        'items': <Object?>[
          for (final Map<String, Object?> i in waiting)
            <String, Object?>{'id': i['id'], 'name': i['product_name']},
        ],
      },
    );
  }

  /// `by_guest`: har mehmonga bitta schyot (o'z qatorlari to'liq), mehmonsiz qator teng (mingdan bir, qoldiq
  /// birinchisiga) — taom bo'yicha bo'lish sifatida tuziladi, keyin schyotlarga `seat_no` yoziladi.
  FakeResponse? _byGuest(Map<String, Object?> order, FakeRequest r) {
    if (!_module('guest_split')) return _moduleDisabled('guest_split');
    final List<Map<String, Object?>> lines = <Map<String, Object?>>[
      for (final Map<String, Object?> i in _items(order))
        if (i['kitchen_status'] != 'void') i,
    ];
    final List<int> seats = <int>{
      for (final Map<String, Object?> i in lines)
        if (i['seat_no'] != null) (i['seat_no']! as num).toInt(),
    }.toList()..sort();
    if (seats.isEmpty) {
      return _fail(
        422,
        'no_seat_lines',
        "Qatorlarda mehmon raqami yo'q: avval qatorlarga mehmonni belgilang.",
      );
    }
    final List<Map<String, Object?>> bills = <Map<String, Object?>>[];
    for (var k = 0; k < seats.length; k++) {
      final List<Object?> part = <Object?>[];
      for (final Map<String, Object?> i in lines) {
        final int milli = (double.parse('${i['quantity']}') * 1000).round();
        final int? seat = (i['seat_no'] as num?)?.toInt();
        int share;
        if (seat != null) {
          share = seat == seats[k] ? milli : 0;
        } else {
          final int base = milli ~/ seats.length;
          share = k == 0 ? milli - base * (seats.length - 1) : base;
        }
        if (share <= 0) continue;
        part.add(<String, Object?>{
          'order_item_id': i['id'],
          'quantity': (share / 1000).toStringAsFixed(3),
        });
      }
      bills.add(<String, Object?>{'items': part, 'guests': 1});
    }
    final FakeResponse? res = cashier.handle(
      FakeRequest(
        method: r.method,
        path: r.path,
        query: r.query,
        data: <String, Object?>{...r.data, 'mode': 'items', 'bills': bills},
        member: r.member,
        idempotencyKey: r.idempotencyKey,
      ),
    );
    if (res == null || res.status >= 300) return res;
    final List<Map<String, Object?>> made = <Map<String, Object?>>[
      for (final Map<String, Object?> b
          in cashier.bills['${order['uuid']}'] ?? <Map<String, Object?>>[])
        if (b['status'] != 'void' && b['split_mode'] == 'items') b,
    ];
    final int from = made.length - seats.length;
    for (var k = 0; k < seats.length && from + k >= 0; k++) {
      made[from + k]
        ..['split_mode'] = 'by_guest'
        ..['seat_no'] = seats[k]
        ..['guests_share'] = 1;
    }
    return res;
  }

  // --- qator ---------------------------------------------------------------------------------------------------------

  FakeResponse _seat(String uuid, int itemId, FakeRequest r) {
    if (!_module('guest_split')) return _moduleDisabled('guest_split');
    final Map<String, Object?>? order = state.orders[uuid];
    if (order == null) return _fail(404, 'not_found', 'Buyurtma topilmadi.');
    if ((r.data['version'] as num?)?.toInt() != order['version']) {
      return _fail(
        409,
        'version_conflict',
        "Buyurtma boshqa qurilmada o'zgartirildi. Uni qayta yuklang va amalni takrorlang.",
        <String, Object?>{'version': order['version']},
      );
    }
    Map<String, Object?>? item;
    for (final Map<String, Object?> i in _items(order)) {
      if (i['id'] == itemId) item = i;
    }
    if (item == null) return _fail(404, 'not_found', 'Taom topilmadi.');
    item['seat_no'] = (r.data['seat_no'] as num?)?.toInt();
    order['version'] = (order['version']! as int) + 1;
    order['updated_at'] = state.now().toUtc().toIso8601String();
    return _ok(order, m: 'Mehmon raqami yozildi.');
  }

  // --- ish vaqti -------------------------------------------------------------------------------------------------------

  FakeResponse _clock(FakeRequest r) {
    if (!_module('staff_pay')) return _moduleDisabled('staff_pay');
    final int id = r.member['id']! as int;
    final DateTime now = state.now();
    final String date =
        '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
    Map<String, Object?> body() => <String, Object?>{
      'session': openSessions[id],
      'today_minutes': closedMinutes[id] ?? 0,
      'business_date': date,
    };
    if (r.method == 'GET') return _ok(body());
    if (r.path.endsWith('/in')) {
      if (openSessions[id] != null) {
        return _fail(409, 'already_clocked_in', 'Siz allaqachon ishdasiz.');
      }
      openSessions[id] = <String, Object?>{
        'uuid': 'ws-${++_seq}',
        'business_date': date,
        'clock_in_at': now.toUtc().toIso8601String(),
        'clock_out_at': null,
        'minutes': 0,
        'open': true,
        'abandoned': false,
      };
      return _ok(body(), m: 'Ishga keldingiz.');
    }
    final Map<String, Object?>? open = openSessions.remove(id);
    if (open == null) {
      return _fail(409, 'not_clocked_in', 'Siz hali ishga kelmagansiz.');
    }
    final int minutes = now
        .difference(DateTime.parse('${open['clock_in_at']}'))
        .inMinutes;
    closedMinutes[id] = (closedMinutes[id] ?? 0) + minutes;
    return _ok(body(), m: 'Ishdan ketdingiz.');
  }

  static int _t(Object? v) {
    if (v == null) return 0;
    if (v is num) return (v * 100).round();
    final String s = '$v'.trim();
    return s.isEmpty ? 0 : (double.parse(s) * 100).round();
  }

  static String _m(int tiyin) {
    final String sign = tiyin < 0 ? '-' : '';
    final int a = tiyin.abs();
    return '$sign${a ~/ 100}.${(a % 100).toString().padLeft(2, '0')}';
  }
}
