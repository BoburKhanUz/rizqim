import 'dart:math' as math;

import 'fake_cashier_backend.dart';
import 'fake_pos_state.dart';

/// P6-51…P6-54 (to'lqin 5, B): soxta tadbirlar backend'i ([FakeCashierBackend.useBanquets] ulaydi; avvalgi yo'llar
/// saqlanadi). Taqvim, ro'yxat, katalog, tadbir kartasi, band qilish (`slot_taken`), o'zgartirish; to'lov jadvali
/// (30 % zaklad, 40 % 14 kun oldin, qolgani tadbir kuni), qatorni kassada to'lash (qismi ham; smena, BO'NAK cheki,
/// zaklad → `confirmed`), bekor qilish hisobi va bekor qilish (≥ 30 kun 0 %, 14–29 kun 30 %, < 14 kun 100 %; naqd
/// qaytarish — ochiq smena va `pos.refund` yoki menejer tasdig'i); tadbir kuni (`start` — stolsiz zal buyurtmasi),
/// kelgan mehmonlar, yakuniy hisob (`settle`: bitta schyot — paket, zal, qo'shimchalar + buyurtma, zakladlar
/// `prepayment`; qoldiq oddiy to'lov bilan), ortiqcha zakladni qaytarish; ishlab chiqarish varag'i, bozorlik,
/// shartnoma. Javob shakllari docs/API.md "Tadbirlar" bo'yicha. Pul — tiyin (butun son) ichkarida, satr tashqarida.
class FakeBanquetBackend {
  FakeBanquetBackend(this.cashier);

  final FakeCashierBackend cashier;

  FakePosState get state => cashier.state;

  /// Filial zallari (`id`, `name`).
  final List<Map<String, Object?>> halls = <Map<String, Object?>>[
    <String, Object?>{'id': 5, 'name': 'Oq zal'},
    <String, Object?>{'id': 6, 'name': "Ko'k zal"},
  ];

  /// Do'kon vaqtlari (mahalliy `HH:MM`).
  final Map<String, (String, String)> slots = <String, (String, String)>{
    'morning': ('08:00', '12:00'),
    'day': ('12:00', '17:00'),
    'evening': ('18:00', '23:00'),
  };

  /// Paketlar: `id`, `name`, `price` (tiyin, kishi boshiga), `minimum`, `included`.
  final List<Map<String, Object?>> packages = <Map<String, Object?>>[
    <String, Object?>{
      'id': 1,
      'name': 'Standart',
      'price': 15000000,
      'minimum': 150,
      'included': <String>['Osh', 'Salatlar', 'Choy, non'],
    },
    <String, Object?>{
      'id': 2,
      'name': 'Premium',
      'price': 22000000,
      'minimum': 100,
      'included': <String>['Osh', 'Kabob', 'Salatlar', 'Ichimliklar'],
    },
  ];

  /// Qo'shimchalar: `id`, `name`, `price` (tiyin), `per_person`.
  final List<Map<String, Object?>> extras = <Map<String, Object?>>[
    <String, Object?>{
      'id': 1,
      'name': "To'y torti",
      'price': 150000000,
      'per_person': false,
    },
    <String, Object?>{
      'id': 2,
      'name': 'Ichimliklar',
      'price': 2000000,
      'per_person': true,
    },
  ];

  /// Zal id → slot → ijara (tiyin).
  final Map<int, Map<String, int>> hallRates = <int, Map<String, int>>{
    5: <String, int>{
      'morning': 500000000,
      'day': 700000000,
      'evening': 1000000000,
    },
    6: <String, int>{
      'morning': 300000000,
      'day': 400000000,
      'evening': 600000000,
    },
  };

  int holdHours = 48;

  /// Mehmonlar chegarasi (ogohlantirish).
  int guestThreshold = 250;

  /// Bekor qilish qoidalari: (kamida kun, ushlab qolinadigan %), kamayish tartibida.
  List<(int, int)> cancellationRules = <(int, int)>[
    (30, 0),
    (14, 30),
    (0, 100),
  ];

  /// uuid → tadbir (ichki ko'rinish).
  final Map<String, Map<String, Object?>> events =
      <String, Map<String, Object?>>{};

  /// uuid → jadval qatorlari.
  final Map<String, List<Map<String, Object?>>> rows =
      <String, List<Map<String, Object?>>>{};

  /// uuid → pul yozuvlari (`type` payment/refund/retain, `shift_uuid`, `tender`, `amount` tiyin).
  final Map<String, List<Map<String, Object?>>> entries =
      <String, List<Map<String, Object?>>>{};

  /// uuid → bekor qilish qarori.
  final Map<String, Map<String, Object?>> cancellations =
      <String, Map<String, Object?>>{};

  /// uuid → yakuniy hisob (`bill_uuid`, `applied`, `excess`, `excess_status`).
  final Map<String, Map<String, Object?>> settlements =
      <String, Map<String, Object?>>{};

  final Map<String, FakeResponse> _byKey = <String, FakeResponse>{};
  int _seq = 0;

  // --- yordamchilar --------------------------------------------------------------------------------------------------

  static int _t(Object? v) {
    if (v == null) return 0;
    if (v is int) return v * 100;
    return (double.parse('$v') * 100).round();
  }

  static String _m(int tiyin) {
    final String sign = tiyin < 0 ? '-' : '';
    final int a = tiyin.abs();
    return '$sign${a ~/ 100}.${(a % 100).toString().padLeft(2, '0')}';
  }

  String _uuid(String kind) {
    _seq++;
    final String n = _seq.toString().padLeft(12, '0');
    final String k = switch (kind) {
      'e' => 'e',
      'p' => 'a',
      'n' => 'b',
      'r' => 'c',
      _ => 'd',
    };
    return '${k * 8}-0000-4000-9000-$n';
  }

  static String _iso(DateTime d) => d.toUtc().toIso8601String();

  /// Toshkent (UTC+5) bugungi sanasi.
  String get today => _day(state.now().toUtc().add(const Duration(hours: 5)));

  static String _day(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  static DateTime _date(String day) => DateTime.parse('${day}T00:00:00Z');

  static String _addDays(String day, int days) =>
      _day(_date(day).add(Duration(days: days)));

  static int _daysBetween(String from, String to) =>
      _date(to).difference(_date(from)).inDays;

  DateTime _at(String day, String hm) {
    final DateTime d = _date(day);
    final List<String> p = hm.split(':');
    return DateTime.utc(
      d.year,
      d.month,
      d.day,
      int.parse(p[0]),
      int.parse(p[1]),
    ).subtract(const Duration(hours: 5));
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

  static FakeResponse _forbidden() =>
      _fail(403, 'forbidden', 'Ruxsat etilmagan');

  bool _can(FakeRequest r, String permission) =>
      state.permissionsOf(r.member).contains(permission);

  bool _canRead(FakeRequest r) =>
      _can(r, 'banquets.manage') || _can(r, 'pos.take_payment');

  /// Filial formatida `banquet` moduli o'chiq (`format` yo'q — yoqiq).
  bool get moduleOff {
    final Map<String, Object?>? format = state.format;
    if (format == null) return false;
    final Object? modules = format['modules'];
    return !(modules is List && modules.contains('banquet'));
  }

  static FakeResponse _moduleDisabled() =>
      const FakeResponse(403, <String, Object?>{
        'success': false,
        'message': "Bu modul filialda o'chirilgan.",
        'code': 'module_disabled',
        'module': 'banquet',
        'data': <String, Object?>{
          'reason': 'module_disabled',
          'module': 'banquet',
        },
      });

  Map<String, Object?>? _package(Object? id) {
    for (final Map<String, Object?> p in packages) {
      if (p['id'] == id) return p;
    }
    return null;
  }

  Map<String, Object?>? _extra(Object? id) {
    for (final Map<String, Object?> x in extras) {
      if (x['id'] == id) return x;
    }
    return null;
  }

  String _hallName(int id) =>
      '${halls.firstWhere((Map<String, Object?> h) => h['id'] == id, orElse: () => <String, Object?>{'name': 'Zal $id'})['name']}';

  // --- hisob ---------------------------------------------------------------------------------------------------------

  static List<int> _hallIds(Map<String, Object?> e) =>
      (e['hall_ids']! as List<Object?>).cast<int>();

  static List<Map<String, Object?>> _extrasOf(Map<String, Object?> e) =>
      (e['extras']! as List<Object?>).cast<Map<String, Object?>>();

  /// max(kafolatlangan, minimal) — yoki yakuniy hisobda kelgan mehmonlar ham.
  static int billedGuests(Map<String, Object?> e, {bool actual = false}) =>
      <int>[
        e['guests_guaranteed']! as int,
        e['guests_minimum']! as int,
        if (actual && e['guests_actual'] != null) e['guests_actual']! as int,
      ].reduce(math.max);

  List<Map<String, Object?>> _lines(
    Map<String, Object?> e, {
    bool actual = false,
  }) {
    final int guests = billedGuests(e, actual: actual);
    final Map<String, Object?>? pkg = _package(e['package_id']);
    final int pkgPrice =
        (e['package_price'] as int?) ?? (pkg?['price'] as int? ?? 0);
    return <Map<String, Object?>>[
      if (pkg != null)
        <String, Object?>{
          'uuid': '${e['uuid']}-pkg',
          'kind': 'package',
          'kind_label': 'Paket',
          'name': pkg['name'],
          'extra_id': null,
          'hall_id': null,
          'quantity': '1',
          'persons': guests,
          'per_person': true,
          'unit_price': _m(pkgPrice),
          'amount': _m(pkgPrice * guests),
          'frozen': e['frozen'] == true,
          'note': null,
        },
      for (final int h in _hallIds(e))
        <String, Object?>{
          'uuid': '${e['uuid']}-hall-$h',
          'kind': 'hall_rent',
          'kind_label': 'Zal ijarasi',
          'name': 'Zal ijarasi: ${_hallName(h)}',
          'extra_id': null,
          'hall_id': h,
          'quantity': '1',
          'persons': null,
          'per_person': false,
          'unit_price': _m(hallRates[h]?[e['slot']] ?? 0),
          'amount': _m(hallRates[h]?[e['slot']] ?? 0),
          'frozen': e['frozen'] == true,
          'note': null,
        },
      for (final Map<String, Object?> x in _extrasOf(e))
        <String, Object?>{
          'uuid': x['line_uuid'],
          'kind': 'extra',
          'kind_label': "Qo'shimcha",
          'name': x['name'],
          'extra_id': x['extra_id'],
          'hall_id': null,
          'quantity': '${x['quantity']}',
          'persons': x['per_person'] == true ? guests : null,
          'per_person': x['per_person'] == true,
          'unit_price': _m(x['unit_price']! as int),
          'amount': _m(
            (x['unit_price']! as int) *
                (x['quantity']! as int) *
                (x['per_person'] == true ? guests : 1),
          ),
          'frozen': e['frozen'] == true,
          'note': x['note'],
        },
    ];
  }

  int _sumOf(List<Map<String, Object?>> lines, [String? kind]) => lines
      .where((Map<String, Object?> l) => kind == null || l['kind'] == kind)
      .fold(0, (int s, Map<String, Object?> l) => s + _t(l['amount']));

  /// Tadbir kuni buyurtmasining jami (qatorlar + xizmat haqi).
  int _orderTotal(Map<String, Object?> e) {
    final Map<String, Object?>? o = state.orders['${e['order_uuid']}'];
    if (o == null) return 0;
    return _t(o['total_price']) - _t(o['banquet_amount']);
  }

  int totalOf(Map<String, Object?> e, {bool actual = false}) =>
      _sumOf(_lines(e, actual: actual)) + _orderTotal(e);

  List<Map<String, Object?>> _rowsOf(String uuid) =>
      rows.putIfAbsent(uuid, () => <Map<String, Object?>>[]);

  List<Map<String, Object?>> _entriesOf(String uuid) =>
      entries.putIfAbsent(uuid, () => <Map<String, Object?>>[]);

  /// To'langan (kassa va bank) — qaytarilgan va ushlab qolinganlardan oldingi summa.
  int paidOf(String uuid) => _rowsOf(uuid)
      .where((Map<String, Object?> r) => r['status'] != 'due')
      .fold(0, (int s, Map<String, Object?> r) => s + (r['amount']! as int));

  /// Hali amalda bo'lgan zakladlar (qaytarilmagan).
  int advancesOf(String uuid) {
    final int refunded = _entriesOf(uuid)
        .where((Map<String, Object?> x) => x['type'] == 'refund')
        .fold(0, (int s, Map<String, Object?> x) => s + (x['amount']! as int));
    return paidOf(uuid) - refunded;
  }

  /// Jadval: zaklad 30 % (bugun), oraliq 40 % (14 kun oldin), qolgani (tadbir kuni); butun so'm.
  void _plan(Map<String, Object?> e) {
    final String uuid = '${e['uuid']}';
    final int total = _sumOf(_lines(e));
    final List<Map<String, Object?>> list = _rowsOf(uuid);
    if (list.isEmpty) {
      final int deposit = (total * 30 / 100 / 100).floor() * 100;
      final int interim = (total * 40 / 100 / 100).floor() * 100;
      final String date = '${e['event_date']}';
      String due(String d) => d.compareTo(today) < 0 ? today : d;
      for (final (String kind, int amount, String day)
          in <(String, int, String)>[
            ('deposit', deposit, today),
            ('interim', interim, due(_addDays(date, -14))),
            ('final', total - deposit - interim, date),
          ]) {
        list.add(_row(kind, amount, day));
      }
      return;
    }
    // Summa o'zgardi: farq to'lanmagan qatorlarga (oshsa — oxirgisiga, kamaysa — oxiridan)
    final int planned = list.fold(
      0,
      (int s, Map<String, Object?> r) => s + (r['amount']! as int),
    );
    int diff = total - planned;
    if (diff == 0) return;
    final List<Map<String, Object?>> unpaid = list
        .where((Map<String, Object?> r) => r['status'] == 'due')
        .toList();
    if (diff > 0) {
      if (unpaid.isEmpty) {
        list.add(_row('final', diff, '${e['event_date']}'));
      } else {
        unpaid.last['amount'] = (unpaid.last['amount']! as int) + diff;
      }
      return;
    }
    for (final Map<String, Object?> r in unpaid.reversed) {
      if (diff == 0) break;
      final int cut = math.min(r['amount']! as int, -diff);
      r['amount'] = (r['amount']! as int) - cut;
      diff += cut;
    }
    list.removeWhere(
      (Map<String, Object?> r) => r['status'] == 'due' && r['amount'] == 0,
    );
  }

  Map<String, Object?> _row(String kind, int amount, String day) =>
      <String, Object?>{
        'uuid': _uuid('p'),
        'kind': kind,
        'due_date': day,
        'amount': amount,
        'status': 'due',
        'tender': null,
        'card_type': null,
        'transaction_id': null,
        'reference': null,
        'shift_uuid': null,
        'paid_at': null,
        'paid_by': null,
        'receipt_uuid': null,
        'refunded': 0,
        'retained': 0,
      };

  /// Hold muddati o'tgan va to'lov yo'q — bo'shatiladi; yopilgan yakuniy schyot — tadbir `settled`.
  void _sync(Map<String, Object?> e) {
    final String uuid = '${e['uuid']}';
    if (e['status'] == 'hold' &&
        e['hold_expires_at'] != null &&
        !DateTime.parse('${e['hold_expires_at']}').isAfter(state.now()) &&
        paidOf(uuid) == 0) {
      e['status'] = 'cancelled';
      e['released'] = true;
    }
    final Map<String, Object?>? s = settlements[uuid];
    if (e['status'] == 'in_progress' && s != null) {
      final Map<String, Object?>? order = state.orders['${e['order_uuid']}'];
      if (order != null && order['status'] == 'delivered') {
        e['status'] = 'settled';
        e['version'] = (e['version']! as int) + 1;
        s['status'] = 'settled';
      }
    }
  }

  // --- ko'rinishlar --------------------------------------------------------------------------------------------------

  static const Map<String, String> _statusLabels = <String, String>{
    'hold': 'Vaqtincha band',
    'confirmed': 'Tasdiqlangan',
    'in_progress': "O'tkazilmoqda",
    'settled': 'Yakunlangan',
    'cancelled': 'Bekor qilingan',
  };

  static const Map<String, String> _typeLabels = <String, String>{
    'toy': "To'y",
    'osh': 'Osh',
    'birthday': "Tug'ilgan kun",
    'corporate': 'Korporativ',
    'other': 'Boshqa',
  };

  Map<String, Object?> summary(Map<String, Object?> e) {
    final String uuid = '${e['uuid']}';
    final List<Map<String, Object?>> lines = _lines(e);
    final int total = _sumOf(lines) + _orderTotal(e);
    final int paid = paidOf(uuid);
    final List<int> ids = _hallIds(e);
    return <String, Object?>{
      'uuid': uuid,
      'branch_id': FakePosState.branchId,
      'event_date': e['event_date'],
      'slot': e['slot'],
      'slot_label': e['slot'],
      'start_time': e['start_time'],
      'end_time': e['end_time'],
      'starts_at': _iso(_at('${e['event_date']}', '${e['start_time']}')),
      'ends_at': _iso(_at('${e['event_date']}', '${e['end_time']}')),
      'hall_ids': ids,
      'halls': <Object?>[
        for (final int h in ids)
          <String, Object?>{'id': h, 'name': _hallName(h)},
      ],
      'event_type': e['event_type'],
      'event_type_label': _typeLabels['${e['event_type']}'] ?? e['event_type'],
      'status': e['status'],
      'status_label': _statusLabels['${e['status']}'],
      'title': e['title'],
      'customer_name': e['customer_name'],
      'customer_phone': e['customer_phone'],
      'guests_guaranteed': e['guests_guaranteed'],
      'guests_minimum': e['guests_minimum'],
      'guests_actual': e['guests_actual'],
      'guests_billed': billedGuests(e),
      'package_id': e['package_id'],
      'package_name': _package(e['package_id'])?['name'],
      'total': _m(total),
      'paid_total': _m(paid),
      'outstanding': _m(math.max(0, total - paid)),
      'hold_expires_at': e['status'] == 'hold' ? e['hold_expires_at'] : null,
      'frozen': e['frozen'] == true,
      'contract_no': e['contract_no'],
      'version': e['version'],
      'overdue': _rowsOf(uuid).any(_overdue),
    };
  }

  bool _overdue(Map<String, Object?> r) =>
      r['status'] == 'due' && '${r['due_date']}'.compareTo(today) < 0;

  List<Map<String, Object?>> _warnings(Map<String, Object?> e) {
    final int guests = billedGuests(e, actual: true);
    return <Map<String, Object?>>[
      if (guests > guestThreshold)
        <String, Object?>{
          'code': 'guests_over_threshold',
          'message':
              "Mehmonlar soni $guests: $guestThreshold dan ko'p. Zal sig'imi va xodimlarni tekshiring.",
        },
    ];
  }

  Map<String, Object?> detail(Map<String, Object?> e) {
    final List<Map<String, Object?>> lines = _lines(e);
    return <String, Object?>{
      ...summary(e),
      'package_total': _m(_sumOf(lines, 'package')),
      'hall_total': _m(_sumOf(lines, 'hall_rent')),
      'extras_total': _m(_sumOf(lines, 'extra')),
      'orders_total': _m(_orderTotal(e)),
      'quote': null,
      'note': e['note'],
      'order_uuid': e['order_uuid'],
      'lines': lines,
      'payments': <Object?>[
        for (final Map<String, Object?> r in _rowsOf('${e['uuid']}'))
          <String, Object?>{
            'uuid': r['uuid'],
            'kind': r['kind'],
            'due_date': r['due_date'],
            'amount': _m(r['amount']! as int),
            'status': r['status'],
            'tender': r['tender'],
            'paid_at': r['paid_at'],
            'fiscal_receipt_uuid': r['receipt_uuid'],
          },
      ],
      'warnings': _warnings(e),
    };
  }

  static const Map<String, String> _kindLabels = <String, String>{
    'deposit': 'Zaklad',
    'interim': "Oraliq to'lov",
    'final': "Yakuniy to'lov",
  };

  Map<String, Object?> _rowView(Map<String, Object?> r) => <String, Object?>{
    'uuid': r['uuid'],
    'kind': r['kind'],
    'kind_label': _kindLabels['${r['kind']}'],
    'due_date': r['due_date'],
    'amount': _m(r['amount']! as int),
    'status': r['status'],
    'status_label': r['status'],
    'overdue': _overdue(r),
    'tender': r['tender'],
    'card_type': r['card_type'],
    'transaction_id': r['transaction_id'],
    'reference': r['reference'],
    'shift_id': r['shift_uuid'] == null ? null : 1,
    'paid_at': r['paid_at'],
    'paid_by': r['paid_by'],
    'fiscal': <String, Object?>{
      'receipt_uuid': r['receipt_uuid'],
      'status': r['receipt_uuid'] == null ? null : 'issued',
    },
    'refunded_amount': _m(r['refunded']! as int),
    'retained_amount': _m(r['retained']! as int),
    'settle_receipt_uuid': null,
    'note': null,
  };

  Map<String, Object?> _entryView(Map<String, Object?> x) => <String, Object?>{
    'uuid': x['uuid'],
    'type': x['type'],
    'type_label': x['type'],
    'payment_uuid': x['payment_uuid'],
    'amount': _m(x['amount']! as int),
    'tender': x['tender'],
    'card_type': x['card_type'],
    'reference': x['reference'],
    'business_date': today,
    'shift_id': x['shift_uuid'] == null ? null : 1,
    'user': x['user'],
    'approved_by': null,
    'reason': x['reason'],
    'transfer_pending': x['transfer_pending'] == true,
    'fiscal': <String, Object?>{
      'receipt_uuid': x['receipt_uuid'],
      'status': x['receipt_uuid'] == null ? null : 'issued',
    },
    'original_receipt_uuid': x['original_receipt_uuid'],
    'created_at': x['created_at'],
  };

  Map<String, Object?> schedule(Map<String, Object?> e) {
    final String uuid = '${e['uuid']}';
    final List<Map<String, Object?>> list = _rowsOf(uuid);
    final int total = totalOf(e);
    final int paid = paidOf(uuid);
    final List<Map<String, Object?>> overdue = list.where(_overdue).toList();
    final Map<String, Object?>? next = list
        .where((Map<String, Object?> r) => r['status'] == 'due')
        .firstOrNull;
    final Map<String, Object?>? c = cancellations[uuid];
    return <String, Object?>{
      'event': <String, Object?>{
        'uuid': uuid,
        'status': e['status'],
        'status_label': _statusLabels['${e['status']}'],
        'event_date': e['event_date'],
        'total': _m(total),
        'paid_total': _m(paid),
        'version': e['version'],
      },
      'summary': <String, Object?>{
        'total': _m(total),
        'paid': _m(paid),
        'outstanding': _m(math.max(0, total - paid)),
        'overdue': overdue.isNotEmpty,
        'overdue_amount': _m(
          overdue.fold(
            0,
            (int s, Map<String, Object?> r) => s + (r['amount']! as int),
          ),
        ),
        'overdue_count': overdue.length,
        'next_due': next == null
            ? null
            : <String, Object?>{
                'date': next['due_date'],
                'amount': _m(next['amount']! as int),
                'kind': next['kind'],
              },
      },
      'rows': <Object?>[for (final Map<String, Object?> r in list) _rowView(r)],
      'entries': <Object?>[
        for (final Map<String, Object?> x in _entriesOf(uuid)) _entryView(x),
      ],
      'cancellation': c,
    };
  }

  // --- urug' (testlar uchun) -----------------------------------------------------------------------------------------

  /// Tadbir qo'shadi: [status] `hold` yoki `confirmed` ([depositPaid] — zaklad naqd to'langan), jadval tuziladi.
  String seedEvent({
    required String date,
    String slot = 'evening',
    List<int> hallIds = const <int>[5],
    String customer = 'Karimovlar',
    String? phone = '+998901234567',
    String type = 'toy',
    String? title,
    int guests = 200,
    int? minimum,
    int packageId = 1,
    List<(int, int)> extraIds = const <(int, int)>[],
    String status = 'hold',
    bool depositPaid = false,
    String depositTender = 'cash',
  }) {
    final String uuid = _uuid('e');
    final (String, String) times = slots[slot] ?? ('18:00', '23:00');
    final Map<String, Object?> e = <String, Object?>{
      'uuid': uuid,
      'event_date': date,
      'slot': slot,
      'start_time': times.$1,
      'end_time': times.$2,
      'hall_ids': hallIds,
      'event_type': type,
      'status': status,
      'title': title,
      'customer_name': customer,
      'customer_phone': phone,
      'guests_guaranteed': guests,
      'guests_minimum':
          minimum ?? (_package(packageId)?['minimum'] as int? ?? 0),
      'guests_actual': null,
      'package_id': packageId,
      'extras': <Object?>[
        for (final (int id, int qty) in extraIds)
          <String, Object?>{
            'line_uuid': _uuid('x'),
            'extra_id': id,
            'name': _extra(id)?['name'],
            'unit_price': _extra(id)?['price'],
            'per_person': _extra(id)?['per_person'],
            'quantity': qty,
            'note': null,
          },
      ],
      'frozen': false,
      'contract_no': null,
      'version': 1,
      'note': null,
      'order_uuid': null,
      'hold_expires_at': status == 'hold'
          ? _iso(state.now().add(Duration(hours: holdHours)))
          : null,
    };
    events[uuid] = e;
    _plan(e);
    if (depositPaid || status != 'hold') {
      final Map<String, Object?> deposit = _rowsOf(uuid).first;
      _markPaid(
        e,
        deposit,
        tender: depositTender,
        amount: deposit['amount']! as int,
        shiftUuid: state.shift?['uuid'] as String?,
        member: <String, Object?>{'id': 2, 'name': 'Malika Rahimova'},
        data: const <String, Object?>{},
      );
      if (status == 'hold') e['status'] = 'confirmed';
    }
    return uuid;
  }

  /// Qatorni muddati o'tgan qiladi (sanasi kechagi kun).
  void makeOverdue(String eventUuid, {int index = 1}) {
    _rowsOf(eventUuid)[index]['due_date'] = _addDays(today, -1);
  }

  // --- yo'llar -------------------------------------------------------------------------------------------------------

  FakeResponse? handle(FakeRequest r) {
    final String p = r.path;
    if (!p.startsWith('/v1/pos/banquets')) return null;
    for (final Map<String, Object?> e in events.values) {
      _sync(e);
    }
    final String m = r.method;
    if (!_canRead(r)) return _forbidden();
    if (m == 'GET' && p == '/v1/pos/banquets/calendar') return _calendar(r);
    if (m == 'GET' && p == '/v1/pos/banquets/catalog') return _catalog();
    if (m == 'GET' && p == '/v1/pos/banquets/shopping-list') {
      return moduleOff ? _moduleDisabled() : _shopping(r);
    }
    if (m == 'GET' && p == '/v1/pos/banquets') return _list(r);
    if (m == 'POST' && p == '/v1/pos/banquets') return _hold(r);
    final RegExpMatch? x = RegExp(r'^/v1/pos/banquets/([^/]+)(?:/(.+))?$')
        .firstMatch(p);
    if (x == null) return null;
    final Map<String, Object?>? e = events[x.group(1)];
    if (e == null) {
      return _fail(404, 'event_not_found', 'Tadbir topilmadi.');
    }
    final String? tail = x.group(2);
    final String? key = r.idempotencyKey;
    if (m != 'GET' && key != null && _byKey.containsKey('$p|$key')) {
      return _byKey['$p|$key'];
    }
    final FakeResponse res = _event(m, tail, e, r);
    if (m != 'GET' && key != null && res.status < 300) {
      _byKey['$p|$key'] = res;
    }
    return res;
  }

  FakeResponse _event(
    String m,
    String? tail,
    Map<String, Object?> e,
    FakeRequest r,
  ) {
    if (tail == null) {
      if (m == 'GET') return _ok(detail(e));
      if (m == 'PATCH') return _update(e, r);
    }
    if (m == 'GET' && tail == 'payments') return _ok(schedule(e));
    final RegExpMatch? pay = RegExp(r'^payments/([^/]+)/pay$')
        .firstMatch(tail ?? '');
    if (m == 'POST' && pay != null) return _pay(e, pay.group(1)!, r);
    if (tail == 'cancellation') {
      if (!_can(r, 'banquets.manage')) return _forbidden();
      if (m == 'GET') {
        final Object? retained = r.query['retained'];
        return _ok(_quote(e, retained == null ? null : _t(retained)));
      }
      if (m == 'POST') return _cancel(e, r);
    }
    if (m == 'GET' && tail == 'warnings') {
      return _ok(<String, Object?>{'warnings': _warnings(e)});
    }
    if (m == 'POST' && tail == 'start') return _start(e, r);
    if (m == 'PATCH' && tail == 'guests') return _guests(e, r);
    if (m == 'GET' && tail == 'settlement') return _ok(_settlement(e));
    if (m == 'POST' && tail == 'settle') return _settle(e, r);
    if (m == 'POST' && tail == 'settlement/refund-excess') {
      return _refundExcess(e, r);
    }
    if (m == 'GET' && tail == 'production-sheet') {
      return moduleOff ? _moduleDisabled() : _ok(_sheet(e));
    }
    if (m == 'GET' && tail == 'contract') {
      if (!_can(r, 'banquets.manage')) return _forbidden();
      return moduleOff ? _moduleDisabled() : _ok(_contract(e));
    }
    if (m == 'POST' && tail == 'freeze') {
      if (!_can(r, 'banquets.manage')) return _forbidden();
      e
        ..['frozen'] = true
        ..['package_price'] = _package(e['package_id'])?['price']
        ..['version'] = (e['version']! as int) + 1;
      return _ok(detail(e));
    }
    return _fail(404, 'not_found', 'Topilmadi.');
  }

  FakeResponse _calendar(FakeRequest r) {
    final String from = r.query['from'] ?? today;
    final String to = r.query['to'] ?? _addDays(from, 30);
    final bool cancelled = r.query['include_cancelled'] == '1';
    return _ok(<String, Object?>{
      'branch_id': FakePosState.branchId,
      'from': from,
      'to': to,
      'slots': <String, Object?>{
        for (final MapEntry<String, (String, String)> s in slots.entries)
          s.key: <String, Object?>{'start': s.value.$1, 'end': s.value.$2},
      },
      'halls': <Object?>[
        for (final Map<String, Object?> h in halls)
          <String, Object?>{
            'id': h['id'],
            'name': h['name'],
            'is_active': true,
            'events': <Object?>[
              for (final Map<String, Object?> e in events.values)
                if (_hallIds(e).contains(h['id']) &&
                    '${e['event_date']}'.compareTo(from) >= 0 &&
                    '${e['event_date']}'.compareTo(to) <= 0 &&
                    (cancelled || e['status'] != 'cancelled'))
                  summary(e),
            ],
          },
      ],
    });
  }

  FakeResponse _list(FakeRequest r) {
    final String? phone = r.query['phone'];
    final String? status = r.query['status'];
    return _ok(<String, Object?>{
      'events': <Object?>[
        for (final Map<String, Object?> e in events.values)
          if ((phone == null ||
                  '${e['customer_phone'] ?? ''}'.contains(phone)) &&
              (status == null || e['status'] == status))
            summary(e),
      ],
    });
  }

  FakeResponse _catalog() => _ok(<String, Object?>{
    'packages': <Object?>[
      for (final Map<String, Object?> p in packages)
        <String, Object?>{
          'id': p['id'],
          'name': p['name'],
          'per_person_price': _m(p['price']! as int),
          'minimum_guests': p['minimum'],
          'included': p['included'],
        },
    ],
    'extras': <Object?>[
      for (final Map<String, Object?> x in extras)
        <String, Object?>{
          'id': x['id'],
          'name': x['name'],
          'price': _m(x['price']! as int),
          'pricing': x['per_person'] == true ? 'per_person' : 'per_event',
        },
    ],
    'halls': <Object?>[
      for (final Map<String, Object?> h in halls)
        <String, Object?>{
          'hall_id': h['id'],
          'name': h['name'],
          'rates': <String, Object?>{
            for (final MapEntry<String, int> rate
                in (hallRates[h['id']] ?? const <String, int>{}).entries)
              rate.key: _m(rate.value),
          },
        },
    ],
    'settings': <String, Object?>{
      'slots': <String, Object?>{
        for (final MapEntry<String, (String, String)> s in slots.entries)
          s.key: <String, Object?>{'start': s.value.$1, 'end': s.value.$2},
      },
      'hold_hours': holdHours,
    },
  });

  bool _clash(Map<String, Object?> e, String? except) {
    for (final Map<String, Object?> o in events.values) {
      if (o['uuid'] == except) continue;
      if (!<String>{'hold', 'confirmed', 'in_progress'}.contains(o['status'])) {
        continue;
      }
      if (o['event_date'] != e['event_date']) continue;
      if (!_hallIds(o).any(_hallIds(e).contains)) continue;
      final bool overlaps =
          '${o['start_time']}'.compareTo('${e['end_time']}') < 0 &&
          '${e['start_time']}'.compareTo('${o['end_time']}') < 0;
      if (overlaps) return true;
    }
    return false;
  }

  List<Map<String, Object?>> _extrasFrom(Object? raw) => <Map<String, Object?>>[
    for (final Object? x in (raw as List<Object?>?) ?? const <Object?>[])
      () {
        final Map<String, Object?> d = (x! as Map<Object?, Object?>)
            .cast<String, Object?>();
        final Map<String, Object?>? cat = _extra(d['extra_id']);
        return <String, Object?>{
          'line_uuid': d['line_uuid'] ?? _uuid('x'),
          'extra_id': d['extra_id'],
          'name': cat?['name'] ?? d['name'],
          'unit_price': cat?['price'] ?? _t(d['unit_price']),
          'per_person': cat?['per_person'] ?? d['per_person'] == true,
          'quantity': d['quantity'] == null
              ? 1
              : double.parse('${d['quantity']}').round(),
          'note': d['note'],
        };
      }(),
  ];

  FakeResponse _hold(FakeRequest r) {
    if (!_can(r, 'banquets.manage')) return _forbidden();
    if (moduleOff) return _moduleDisabled();
    final Map<String, Object?> d = r.data;
    final String slot = '${d['slot'] ?? 'evening'}';
    final (String, String) times = slot == 'custom'
        ? ('${d['start_time']}', '${d['end_time']}')
        : slots[slot]!;
    final String date = '${d['event_date']}';
    if (date.compareTo(today) < 0) {
      return _fail(
        422,
        'event_in_past',
        "O'tgan kunga tadbir qo'yib bo'lmaydi.",
      );
    }
    final int? packageId = (d['banquet_package_id'] as num?)?.toInt();
    final String uuid = _uuid('e');
    final Map<String, Object?> e = <String, Object?>{
      'uuid': uuid,
      'event_date': date,
      'slot': slot,
      'start_time': times.$1,
      'end_time': times.$2,
      'hall_ids': <int>[
        for (final Object? h
            in (d['hall_ids'] as List<Object?>?) ?? <Object?>[])
          (h! as num).toInt(),
      ],
      'event_type': d['event_type'] ?? 'toy',
      'status': 'hold',
      'title': d['title'],
      'customer_name': d['customer_name'],
      'customer_phone': d['customer_phone'],
      'guests_guaranteed': (d['guests_guaranteed'] as num?)?.toInt() ?? 0,
      'guests_minimum':
          (d['guests_minimum'] as num?)?.toInt() ??
          (_package(packageId)?['minimum'] as int? ?? 0),
      'guests_actual': null,
      'package_id': packageId,
      'extras': _extrasFrom(d['extras']),
      'frozen': false,
      'contract_no': null,
      'version': 1,
      'note': d['note'],
      'order_uuid': null,
      'hold_expires_at': _iso(state.now().add(Duration(hours: holdHours))),
    };
    if (_clash(e, null)) {
      return _fail(
        409,
        'slot_taken',
        "Zal shu vaqtda band: boshqa zal yoki vaqtni tanlang.",
      );
    }
    events[uuid] = e;
    _plan(e);
    return _ok(detail(e), status: 201, message: 'Zal vaqtincha band qilindi.');
  }

  FakeResponse _update(Map<String, Object?> e, FakeRequest r) {
    if (!_can(r, 'banquets.manage')) return _forbidden();
    if (!<String>{'hold', 'confirmed'}.contains(e['status'])) {
      return _fail(
        409,
        'event_not_editable',
        "Bu tadbirni o'zgartirib bo'lmaydi.",
      );
    }
    if ((r.data['version'] as num?)?.toInt() != e['version']) {
      return _fail(
        409,
        'stale_version',
        "Tadbir boshqa joyda o'zgartirildi: qayta oching.",
      );
    }
    final Map<String, Object?> d = r.data;
    if (d['guests_guaranteed'] != null) {
      e['guests_guaranteed'] = (d['guests_guaranteed']! as num).toInt();
    }
    if (d['guests_minimum'] != null) {
      e['guests_minimum'] = (d['guests_minimum']! as num).toInt();
    }
    if (d['extras'] != null) e['extras'] = _extrasFrom(d['extras']);
    if (d['note'] != null) e['note'] = d['note'];
    if (d['customer_name'] != null) e['customer_name'] = d['customer_name'];
    if (d['customer_phone'] != null) e['customer_phone'] = d['customer_phone'];
    e['version'] = (e['version']! as int) + 1;
    _plan(e);
    return _ok(detail(e), message: 'Tadbir saqlandi.');
  }

  // --- to'lov --------------------------------------------------------------------------------------------------------

  Map<String, Object?> _advanceReceipt(
    String uuid,
    int amount,
    String tender,
    String name, {
    String type = 'advance',
    bool prepayment = false,
    String? original,
  }) {
    _seq++;
    final Map<String, Object?> receipt = <String, Object?>{
      'uuid': uuid,
      'type': type,
      'status': 'issued',
      'payment_form': tender == 'card' || tender == 'qr' ? tender : 'cash',
      'non_fiscal': cashier.nonFiscal,
      'total': _m(amount),
      'vat_total': _m((amount * 12 / 112).round()),
      'fiscal_sign': type == 'advance' ? null : '${200000000 + _seq * 7919}',
      'receipt_number': '${3000 + _seq}',
      'qr_url': type == 'advance'
          ? null
          : 'https://ofd.soliq.uz/check?t=BQ$_seq',
      'terminal_id': 'EP000000000123',
      'error': null,
      'issued_at': _iso(state.now()),
      'created_at': _iso(state.now()),
      'original_receipt_uuid': original,
      'lines': <Object?>[
        <String, Object?>{
          'name': name,
          'mxik_code': '10202002001000004',
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
    cashier.receipts[uuid] = receipt;
    return receipt;
  }

  Map<String, Object?> _markPaid(
    Map<String, Object?> e,
    Map<String, Object?> row, {
    required String tender,
    required int amount,
    required String? shiftUuid,
    required Map<String, Object?> member,
    required Map<String, Object?> data,
  }) {
    final String uuid = '${e['uuid']}';
    final List<Map<String, Object?>> list = _rowsOf(uuid);
    final int left = (row['amount']! as int) - amount;
    if (left > 0) {
      final Map<String, Object?> rest = _row(
        '${row['kind']}',
        left,
        '${row['due_date']}',
      );
      list.insert(list.indexOf(row) + 1, rest);
      row['amount'] = amount;
    }
    final String receipt = _uuid('r');
    final String label = row['kind'] == 'deposit'
        ? 'Tadbir zakladi (avans)'
        : "Tadbir to'lovi (avans)";
    _advanceReceipt(receipt, amount, tender, label);
    row
      ..['status'] = 'paid'
      ..['tender'] = tender
      ..['card_type'] = data['card_type']
      ..['transaction_id'] = data['transaction_id']
      ..['reference'] = data['reference']
      ..['shift_uuid'] = shiftUuid
      ..['paid_at'] = _iso(state.now())
      ..['paid_by'] = <String, Object?>{
        'id': member['id'],
        'name': member['name'],
      }
      ..['receipt_uuid'] = receipt;
    final Map<String, Object?> entry = <String, Object?>{
      'uuid': _uuid('n'),
      'type': 'payment',
      'payment_uuid': row['uuid'],
      'amount': amount,
      'tender': tender,
      'card_type': data['card_type'],
      'reference': data['transaction_id'] ?? data['reference'],
      'shift_uuid': shiftUuid,
      'user': <String, Object?>{'id': member['id'], 'name': member['name']},
      'receipt_uuid': receipt,
      'created_at': _iso(state.now()),
    };
    _entriesOf(uuid).add(entry);
    if (row['kind'] == 'deposit' && e['status'] == 'hold') {
      final bool depositLeft = list.any(
        (Map<String, Object?> x) =>
            x['kind'] == 'deposit' && x['status'] == 'due',
      );
      if (!depositLeft) {
        e
          ..['status'] = 'confirmed'
          ..['hold_expires_at'] = null;
      }
    }
    e['version'] = (e['version']! as int) + 1;
    return entry;
  }

  FakeResponse _pay(Map<String, Object?> e, String rowUuid, FakeRequest r) {
    if (r.idempotencyKey == null) {
      return _fail(
        400,
        'idempotency_key_required',
        'Idempotency-Key sarlavhasi majburiy.',
      );
    }
    if (!_can(r, 'pos.take_payment')) return _forbidden();
    final Map<String, Object?>? row = _rowsOf('${e['uuid']}')
        .where((Map<String, Object?> x) => x['uuid'] == rowUuid)
        .firstOrNull;
    if (row == null) {
      return _fail(404, 'payment_not_found', "To'lov qatori topilmadi.");
    }
    if (!<String>{'hold', 'confirmed', 'in_progress'}.contains(e['status'])) {
      return _fail(
        409,
        'event_not_payable',
        "Bu tadbirga to'lov qabul qilib bo'lmaydi.",
      );
    }
    if (row['status'] != 'due') {
      return _fail(409, 'payment_not_due', "Bu qator allaqachon to'langan.");
    }
    final Map<String, Object?>? shift = state.shift;
    if (shift == null) {
      return _fail(
        409,
        'shift_not_open',
        "Smena ochilmagan: to'lov qabul qilish uchun avval smenani oching.",
      );
    }
    final Map<String, Object?> d = r.data;
    final String tender = '${d['tender'] ?? ''}';
    if (!<String>{'cash', 'card', 'qr'}.contains(tender)) {
      return _fail(422, 'tender_invalid', "To'lov turi noto'g'ri.");
    }
    if (tender == 'card' && '${d['card_type'] ?? ''}'.isEmpty) {
      return _fail(422, 'card_type_required', 'Karta turini tanlang.');
    }
    if (tender == 'card' && '${d['transaction_id'] ?? ''}'.trim().isEmpty) {
      return _fail(
        422,
        'transaction_id_required',
        'Terminal tranzaksiya raqamini kiriting.',
      );
    }
    final int amount = d['amount'] == null
        ? row['amount']! as int
        : _t(d['amount']);
    if (amount <= 0 || amount > (row['amount']! as int)) {
      return _fail(
        422,
        'amount_exceeds_row',
        "Summa qatordan ko'p: ${_m(row['amount']! as int)} so'm.",
        <String, Object?>{'row_amount': _m(row['amount']! as int)},
      );
    }
    final Map<String, Object?> entry = _markPaid(
      e,
      row,
      tender: tender,
      amount: amount,
      shiftUuid: shift['uuid'] as String?,
      member: r.member,
      data: d,
    );
    final String receipt = '${entry['receipt_uuid']}';
    return _ok(
      <String, Object?>{
        'schedule': schedule(e),
        'entry': _entryView(entry),
        'fiscal': <String, Object?>{
          'status': 'issued',
          'receipt_uuid': receipt,
          'error': null,
          'receipt': cashier.receipts[receipt],
        },
      },
      status: 201,
      message: "To'lov qabul qilindi.",
    );
  }

  // --- bekor qilish ----------------------------------------------------------------------------------------------------

  Map<String, Object?> _quote(Map<String, Object?> e, int? retained) {
    final String uuid = '${e['uuid']}';
    final int days = math.max(0, _daysBetween(today, '${e['event_date']}'));
    final int percent = cancellationRules
        .firstWhere(
          ((int, int) rule) => days >= rule.$1,
          orElse: () => (0, 100),
        )
        .$2;
    final List<Map<String, Object?>> paidRows = _rowsOf(uuid)
        .where((Map<String, Object?> r) => r['status'] == 'paid')
        .toList();
    final int paid = paidRows.fold(
      0,
      (int s, Map<String, Object?> r) => s + (r['amount']! as int),
    );
    final int computed = (paid * percent / 100 / 100).round() * 100;
    final int keep = retained ?? computed;
    int toRetain = keep;
    int cash = 0;
    final List<Map<String, Object?>> parts = <Map<String, Object?>>[];
    for (final Map<String, Object?> r in paidRows) {
      final int amount = r['amount']! as int;
      final int retain = math.min(amount, toRetain);
      toRetain -= retain;
      final int refund = amount - retain;
      if (r['tender'] == 'cash') cash += refund;
      parts.add(<String, Object?>{
        'payment_uuid': r['uuid'],
        'kind': r['kind'],
        'tender': r['tender'],
        'amount': _m(amount),
        'retain': _m(retain),
        'refund': _m(refund),
        'refund_path': r['tender'] == 'cash' ? 'cash_drawer' : r['tender'],
      });
    }
    return <String, Object?>{
      'days_before': days,
      'rule_percent': '$percent',
      'paid': _m(paid),
      'computed_retained': _m(computed),
      'retained': _m(keep),
      'refund': _m(paid - keep),
      'overridden': retained != null && retained != computed,
      'cash_refund': _m(cash),
      'transfer_refund': '0.00',
      'rows': parts,
    };
  }

  FakeResponse _cancel(Map<String, Object?> e, FakeRequest r) {
    if (r.idempotencyKey == null) {
      return _fail(
        400,
        'idempotency_key_required',
        'Idempotency-Key sarlavhasi majburiy.',
      );
    }
    if (!<String>{'hold', 'confirmed'}.contains(e['status'])) {
      return _fail(
        409,
        'event_not_cancellable',
        "Tadbirni bu holatda bekor qilib bo'lmaydi.",
      );
    }
    final String reason = '${r.data['reason'] ?? ''}'.trim();
    if (reason.length < 3) {
      return const FakeResponse(422, <String, Object?>{
        'success': false,
        'message': 'Sababni yozing.',
        'errors': <String, Object?>{
          'reason': <String>['Sababni yozing (kamida 3 harf).'],
        },
      });
    }
    final int? retained = r.data['retained'] == null
        ? null
        : _t(r.data['retained']);
    final Map<String, Object?> q = _quote(e, retained);
    if (retained != null && _t(q['paid']) < retained) {
      return _fail(
        422,
        'retained_invalid',
        "Ushlab qolinadigan summa 0 dan ${q['paid']} so'mgacha bo'lsin.",
      );
    }
    if (q['overridden'] == true &&
        '${r.data['override_reason'] ?? ''}'.trim().isEmpty) {
      return _fail(
        422,
        'override_reason_required',
        "Ushlab qolinadigan summani o'zgartirish sababini yozing.",
      );
    }
    final Map<String, Object?>? shift = state.shift;
    if (_t(q['cash_refund']) > 0 && shift == null) {
      return _fail(
        409,
        'refund_needs_shift',
        "Naqd pulni qaytarish uchun kassada ochiq smena kerak: bekor qilishni kassada bajaring.",
      );
    }
    if (_t(q['refund']) > 0) {
      final FakeResponse? denied = state.authorize(
        r.member,
        'pos.refund',
        r.data['approval_token'],
      );
      if (denied != null) return denied;
    }
    final String uuid = '${e['uuid']}';
    for (final Map<String, Object?> part
        in (q['rows']! as List<Object?>).cast<Map<String, Object?>>()) {
      final Map<String, Object?> row = _rowsOf(uuid).firstWhere(
        (Map<String, Object?> x) => x['uuid'] == part['payment_uuid'],
      );
      final int refund = _t(part['refund']);
      final int retain = _t(part['retain']);
      final String tender = '${row['tender']}';
      if (refund > 0) {
        final String receipt = _uuid('r');
        _advanceReceipt(
          receipt,
          refund,
          tender,
          'Tadbir zakladi qaytarildi',
          type: 'refund',
          original: row['receipt_uuid'] as String?,
        );
        _entriesOf(uuid).add(<String, Object?>{
          'uuid': _uuid('n'),
          'type': 'refund',
          'payment_uuid': row['uuid'],
          'amount': refund,
          'tender': tender,
          'shift_uuid': shift?['uuid'],
          'user': <String, Object?>{
            'id': r.member['id'],
            'name': r.member['name'],
          },
          'reason': reason,
          'receipt_uuid': receipt,
          'original_receipt_uuid': row['receipt_uuid'],
          'created_at': _iso(state.now()),
        });
      }
      if (retain > 0) {
        final String receipt = _uuid('r');
        _advanceReceipt(
          receipt,
          retain,
          tender,
          'Bekor qilish haqi',
          type: 'sale',
          prepayment: true,
        );
        _entriesOf(uuid).add(<String, Object?>{
          'uuid': _uuid('n'),
          'type': 'retain',
          'payment_uuid': row['uuid'],
          'amount': retain,
          'tender': tender,
          'shift_uuid': null,
          'user': <String, Object?>{
            'id': r.member['id'],
            'name': r.member['name'],
          },
          'reason': reason,
          'receipt_uuid': receipt,
          'created_at': _iso(state.now()),
        });
      }
      row
        ..['status'] = retain > 0 ? 'retained' : 'refunded'
        ..['refunded'] = refund
        ..['retained'] = retain;
    }
    _rowsOf(uuid).removeWhere((Map<String, Object?> x) => x['status'] == 'due');
    cancellations[uuid] = <String, Object?>{
      'days_before': q['days_before'],
      'rule_percent': q['rule_percent'],
      'paid': q['paid'],
      'computed_retained': q['computed_retained'],
      'retained': q['retained'],
      'refund': q['refund'],
      'override_reason': q['overridden'] == true
          ? r.data['override_reason']
          : null,
      'reason': reason,
      'business_date': today,
      'cancelled_by': r.member['id'],
      'created_at': _iso(state.now()),
    };
    e
      ..['status'] = 'cancelled'
      ..['hold_expires_at'] = null
      ..['version'] = (e['version']! as int) + 1;
    return _ok(schedule(e), message: 'Tadbir bekor qilindi.');
  }

  // --- tadbir kuni va yakuniy hisob -----------------------------------------------------------------------------------

  Map<String, Object?> _eventState(Map<String, Object?> e) => <String, Object?>{
    'uuid': e['uuid'],
    'status': e['status'],
    'version': e['version'],
    'order_uuid': e['order_uuid'],
    'started_at': e['started_at'],
  };

  void _open(Map<String, Object?> e, FakeRequest r) {
    final String order = state.seedOrder(
      guests: billedGuests(e, actual: true),
      lines: const <(int, String)>[],
      sent: false,
      age: Duration.zero,
    );
    final Map<String, Object?> o = state.orders[order]!;
    final String names = _hallIds(e).map(_hallName).join(', ');
    o
      ..['channel'] = 'pos_hall'
      ..['type'] = 'dine_in'
      ..['status'] = 'accepted'
      ..['hall_ref'] = names
      ..['table_ref'] = names
      ..['notes'] =
          'Tadbir: ${e['title'] ?? e['customer_name']}, ${e['event_date']}'
      ..['banquet_event_uuid'] = e['uuid'];
    e
      ..['order_uuid'] = order
      ..['status'] = 'in_progress'
      ..['started_at'] = _iso(state.now())
      ..['version'] = (e['version']! as int) + 1;
  }

  FakeResponse _start(Map<String, Object?> e, FakeRequest r) {
    if (moduleOff) return _moduleDisabled();
    if (e['status'] == 'in_progress' && e['order_uuid'] != null) {
      return _ok(<String, Object?>{'event': _eventState(e)});
    }
    if (e['status'] != 'confirmed') {
      return _fail(
        409,
        'invalid_transition',
        'Tadbir "${_statusLabels['${e['status']}']}" holatida: boshlab bo\'lmaydi.',
        <String, Object?>{'status': e['status']},
      );
    }
    _open(e, r);
    return _ok(<String, Object?>{
      'event': _eventState(e),
    }, message: 'Tadbir boshlandi.');
  }

  FakeResponse _guests(Map<String, Object?> e, FakeRequest r) {
    if (!_can(r, 'banquets.manage')) return _forbidden();
    if (!<String>{'confirmed', 'in_progress'}.contains(e['status'])) {
      return _fail(
        409,
        'invalid_transition',
        "Kelgan mehmonlar sonini o'zgartirib bo'lmaydi.",
      );
    }
    e
      ..['guests_actual'] = (r.data['guests_actual']! as num).toInt()
      ..['version'] = (e['version']! as int) + 1;
    return _ok(<String, Object?>{
      'event': _eventState(e),
      'settlement': _settlement(e),
    });
  }

  Map<String, Object?> _settlement(Map<String, Object?> e) {
    final String uuid = '${e['uuid']}';
    final List<Map<String, Object?>> lines = _lines(e, actual: true);
    final int orders = _orderTotal(e);
    final int total = _sumOf(lines) + orders;
    final int advances = advancesOf(uuid);
    final Map<String, Object?>? s = settlements[uuid];
    return <String, Object?>{
      'event': <String, Object?>{
        'uuid': uuid,
        'status': e['status'],
        'version': e['version'],
        'order_uuid': e['order_uuid'],
      },
      'guests': <String, Object?>{
        'guaranteed': e['guests_guaranteed'],
        'minimum': e['guests_minimum'],
        'actual': e['guests_actual'],
        'billed': billedGuests(e, actual: true),
      },
      'lines': <Object?>[
        for (final Map<String, Object?> l in lines)
          <String, Object?>{
            'uuid': l['uuid'],
            'kind': l['kind'],
            'name': l['name'],
            'unit_price': l['unit_price'],
            'quantity': l['quantity'],
            'persons': l['persons'],
            'amount': l['amount'],
          },
      ],
      'package_total': _m(_sumOf(lines, 'package')),
      'hall_total': _m(_sumOf(lines, 'hall_rent')),
      'extras_total': _m(_sumOf(lines, 'extra')),
      'orders': <String, Object?>{
        'lines': _m(orders),
        'discount': '0.00',
        'service_charge': '0.00',
        'other_charges': '0.00',
        'total': _m(orders),
      },
      'total': _m(total),
      'advances': <String, Object?>{
        'total': _m(advances),
        'applied': _m(s == null ? 0 : s['applied']! as int),
        'rows': <Object?>[
          for (final Map<String, Object?> row in _rowsOf(uuid))
            if (row['status'] == 'paid')
              <String, Object?>{
                'uuid': row['uuid'],
                'tender': row['tender'],
                'amount': _m(row['amount']! as int),
                'paid_at': row['paid_at'],
              },
        ],
      },
      'due': _m(math.max(0, total - advances)),
      'excess': _m(math.max(0, advances - total)),
      'settlement': s == null
          ? null
          : <String, Object?>{
              'uuid': s['uuid'],
              'status': s['status'],
              'bill_uuid': s['bill_uuid'],
              'applied_amount': _m(s['applied']! as int),
              'excess_amount': _m(s['excess']! as int),
              'excess_status': s['excess_status'],
              'settled_at': null,
            },
      'warnings': _warnings(e),
    };
  }

  FakeResponse _settle(Map<String, Object?> e, FakeRequest r) {
    if (r.idempotencyKey == null) {
      return _fail(
        428,
        'idempotency_key_required',
        'Idempotency-Key sarlavhasi majburiy.',
      );
    }
    if (!_can(r, 'pos.take_payment')) return _forbidden();
    final String uuid = '${e['uuid']}';
    if (e['status'] == 'confirmed') _open(e, r);
    if (e['status'] == 'settled') {
      final Map<String, Object?> s = settlements[uuid]!;
      return _ok(<String, Object?>{
        'settlement': _settlement(e),
        'bill': cashier.billByUuid('${s['bill_uuid']}'),
      });
    }
    if (e['status'] != 'in_progress') {
      return _fail(
        409,
        'invalid_transition',
        'Tadbir "${_statusLabels['${e['status']}']}" holatida: yakuniy hisob qilib bo\'lmaydi.',
      );
    }
    final Map<String, Object?> order = state.orders['${e['order_uuid']}']!;
    if (order['status'] == 'delivered') {
      return _fail(
        409,
        'order_closed',
        "Tadbir buyurtmasi yopilgan: yakuniy hisob faqat kassada.",
      );
    }
    if (state.shift == null) {
      return _fail(
        409,
        'shift_not_open',
        "Smena ochilmagan: avval smenani oching.",
      );
    }
    final List<Map<String, Object?>> lines = _lines(e, actual: true);
    final int banquet = _sumOf(lines);
    final Map<String, Object?> bill = cashier.banquetBill(
      order,
      banquet,
      <Map<String, Object?>>[
        for (final Map<String, Object?> l in lines)
          <String, Object?>{
            'kind': l['kind'],
            'name': l['kind'] == 'package'
                ? 'Banket paketi: ${l['name']}'
                : l['name'],
            'quantity': l['persons'] ?? l['quantity'],
            'unit': l['persons'] != null ? 'kishi' : 'xizmat',
            'amount': l['amount'],
          },
      ],
    );
    // Unpaid schedule rows go: the rest is paid on the bill
    _rowsOf(uuid).removeWhere((Map<String, Object?> x) => x['status'] == 'due');
    final int total = _t(bill['total']);
    final int advances = advancesOf(uuid);
    final int applied = math.min(total, advances);
    final int excess = math.max(0, advances - total);
    settlements[uuid] = <String, Object?>{
      'uuid': _uuid('s'),
      'status': 'open',
      'bill_uuid': bill['uuid'],
      'applied': applied,
      'excess': excess,
      'excess_status': excess > 0 ? 'pending' : 'none',
    };
    cashier.prepayBill(bill, order, applied);
    e['version'] = (e['version']! as int) + 1;
    _sync(e);
    return _ok(<String, Object?>{
      'settlement': _settlement(e),
      'bill': bill,
    }, message: 'Yakuniy hisob tayyor.');
  }

  FakeResponse _refundExcess(Map<String, Object?> e, FakeRequest r) {
    if (r.idempotencyKey == null) {
      return _fail(
        428,
        'idempotency_key_required',
        'Idempotency-Key sarlavhasi majburiy.',
      );
    }
    final String uuid = '${e['uuid']}';
    final Map<String, Object?>? s = settlements[uuid];
    if (s == null || s['excess_status'] != 'pending') {
      return _fail(
        409,
        'nothing_to_refund',
        "Qaytariladigan ortiqcha zaklad yo'q.",
      );
    }
    final Map<String, Object?>? shift = state.shift;
    if (shift == null) {
      return _fail(
        409,
        'refund_needs_shift',
        "Naqd pulni qaytarish uchun kassada ochiq smena kerak.",
      );
    }
    final FakeResponse? denied = state.authorize(
      r.member,
      'pos.refund',
      r.data['approval_token'],
    );
    if (denied != null) return denied;
    int left = s['excess']! as int;
    for (final Map<String, Object?> row in _rowsOf(uuid).reversed) {
      if (left == 0) break;
      if (row['status'] != 'paid') continue;
      final int part = math.min(left, row['amount']! as int);
      left -= part;
      final String receipt = _uuid('r');
      _advanceReceipt(
        receipt,
        part,
        '${row['tender']}',
        'Ortiqcha zaklad qaytarildi',
        type: 'refund',
        original: row['receipt_uuid'] as String?,
      );
      row['refunded'] = (row['refunded']! as int) + part;
      _entriesOf(uuid).add(<String, Object?>{
        'uuid': _uuid('n'),
        'type': 'refund',
        'payment_uuid': row['uuid'],
        'amount': part,
        'tender': row['tender'],
        'shift_uuid': shift['uuid'],
        'user': <String, Object?>{
          'id': r.member['id'],
          'name': r.member['name'],
        },
        'reason': 'Ortiqcha zaklad',
        'receipt_uuid': receipt,
        'original_receipt_uuid': row['receipt_uuid'],
        'created_at': _iso(state.now()),
      });
    }
    s['excess_status'] = 'refunded';
    return _ok(_settlement(e), message: 'Ortiqcha zaklad qaytarildi.');
  }

  // --- qog'ozlar -----------------------------------------------------------------------------------------------------

  Map<String, Object?> _sheet(Map<String, Object?> e) {
    final int base = billedGuests(e, actual: true);
    final int planned = (base * 1.1).ceil();
    return <String, Object?>{
      'event': <String, Object?>{
        'uuid': e['uuid'],
        'title': e['title'],
        'event_type': e['event_type'],
        'event_type_label': _typeLabels['${e['event_type']}'],
        'event_date': e['event_date'],
        'local_time': '${e['start_time']}–${e['end_time']}',
        'halls': <String>[for (final int h in _hallIds(e)) _hallName(h)],
      },
      'guests': <String, Object?>{
        'guaranteed': e['guests_guaranteed'],
        'minimum': e['guests_minimum'],
        'actual': e['guests_actual'],
        'base': base,
        'reserve_percent': '10',
        'planned': planned,
      },
      'stations': <Object?>[
        <String, Object?>{
          'station_id': 1,
          'name': 'Oshxona',
          'dishes': <Object?>[
            <String, Object?>{
              'product_id': 3,
              'name': 'Osh',
              'unit': 'portion',
              'quantity_per_person': '1',
              'quantity': '$planned',
              'kg': (planned * 0.35).toStringAsFixed(3),
            },
          ],
        },
      ],
      'osh': <String, Object?>{
        'persons': planned,
        'persons_per_kg': '5',
        'osh_kg': (planned / 5).ceil().toString(),
        'ingredients': <Object?>[
          <String, Object?>{
            'key': 'rice',
            'name': 'Guruch',
            'unit': 'kg',
            'per_kg': '1',
            'quantity': (planned / 5).ceil().toString(),
          },
        ],
        'kazans': <Object?>[
          <String, Object?>{'size_kg': 50, 'count': (planned / 250).ceil()},
        ],
        'kazan_capacity_kg': 50,
      },
      'warnings': _warnings(e),
    };
  }

  FakeResponse _shopping(FakeRequest r) {
    final String from = r.query['from'] ?? today;
    final String to = r.query['to'] ?? from;
    final List<Map<String, Object?>> list = <Map<String, Object?>>[
      for (final Map<String, Object?> e in events.values)
        if (<String>{
              'hold',
              'confirmed',
              'in_progress',
            }.contains(e['status']) &&
            '${e['event_date']}'.compareTo(from) >= 0 &&
            '${e['event_date']}'.compareTo(to) <= 0)
          e,
    ];
    final int persons = list.fold(
      0,
      (int s, Map<String, Object?> e) => s + (billedGuests(e) * 1.1).ceil(),
    );
    return _ok(<String, Object?>{
      'from': from,
      'to': to,
      'events': <Object?>[
        for (final Map<String, Object?> e in list)
          <String, Object?>{
            'uuid': e['uuid'],
            'title': e['title'] ?? e['customer_name'],
            'event_type': e['event_type'],
            'event_date': e['event_date'],
            'slot': e['slot'],
            'status': e['status'],
            'planned_guests': (billedGuests(e) * 1.1).ceil(),
          },
      ],
      'dishes': <Object?>[
        <String, Object?>{
          'product_id': 3,
          'name': 'Osh',
          'unit': 'portion',
          'quantity_per_person': null,
          'quantity': '$persons',
          'kg': null,
        },
      ],
      'osh': <String, Object?>{
        'persons': persons,
        'osh_kg': (persons / 5).ceil().toString(),
        'ingredients': <Object?>[
          <String, Object?>{
            'key': 'rice',
            'name': 'Guruch',
            'unit': 'kg',
            'per_kg': '1',
            'quantity': (persons / 5).ceil().toString(),
          },
        ],
      },
    });
  }

  Map<String, Object?> _contract(Map<String, Object?> e) => <String, Object?>{
    'contract_no': e['contract_no'],
    'text':
        'SHARTNOMA ${e['contract_no'] ?? ''}\n\nMijoz: ${e['customer_name']}\nSana: ${e['event_date']} ${e['start_time']}–${e['end_time']}\n'
        'Mehmonlar: ${e['guests_guaranteed']}\nJami: ${_m(totalOf(e))} so\'m',
    'lines': <Object?>[],
    'fields': <String, Object?>{},
    'warnings': _warnings(e),
  };

  /// X/Z uchun: smenadagi tadbir yozuvlari (`payment` va `refund`).
  List<Map<String, Object?>> shiftEntries(String shiftUuid) =>
      <Map<String, Object?>>[
        for (final List<Map<String, Object?>> list in entries.values)
          for (final Map<String, Object?> x in list)
            if (x['shift_uuid'] == shiftUuid &&
                (x['type'] == 'payment' || x['type'] == 'refund'))
              x,
      ];
}
