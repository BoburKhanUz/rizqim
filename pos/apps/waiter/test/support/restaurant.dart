import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:pos_data/testing.dart';

import 'harness.dart';

/// P6 2-to'lqin modullari (restoran profili): hammasi yoqilgan.
const List<String> restaurantModules = <String>[
  'rooms',
  'service_charge',
  'kds',
  'courses',
  'tips',
  'staff_pay',
  'guest_split',
  'waiter_transfer',
  'hostess',
  'table_booking',
  'skewer_buttons',
  'weight_later',
  'service_charge_rules',
];

/// Hostes (P6-24): soxta backend'dagi id, ism, PIN.
const int hostessId = 7;
const String hostessName = 'Dilnoza Hostes';
const String hostessPin = '6148';

/// Soxta backend'ning restoran qismi (ofitsiant testlari uchun; `pos_data` soxtasida bu yo'llar yo'q): kutish
/// ro'yxati, stol belgisi, stolni boshqa ofitsiantga berish, qator mehmoni, choy puli, ish vaqti va bugungi bronlar.
/// Javob shakllari docs/API.md (P6-22 … P6-26) bo'yicha. [FakePosState.extraRoutes] va [FakePosAdapter.respond]
/// (zal xaritasiga `service_state`) ga ulanadi.
class RestaurantFake {
  RestaurantFake(this.h, {List<String> modules = restaurantModules}) {
    h.state
      ..format = <String, Object?>{
        'profile': 'restoran',
        'profile_label': 'Restoran',
        'mode': 'table',
        'first_screen': 'hall_map',
        'modules': modules,
        'locks': <String, Object?>{'service_charge_zero': false},
      }
      ..extraRoutes = _route;
    h.state.bootstrapExtras
      ..['tips'] = tips
      ..['staff_pay'] = staffPay
      ..['booking_rules'] = bookingRules;
    h.state.rolePermissions['manager'] = <String>[
      ...h.state.rolePermissions['manager']!,
      'orders.transfer',
      'hostess.manage',
      'tips.manage',
    ];
    h.state.rolePermissions['hostess'] = <String>[
      'orders.view',
      'tables.manage',
      'menu.view',
      'bookings.manage',
      'hostess.manage',
    ];
    h.adapter.respond = _respond;
  }

  final WaiterHarness h;

  /// Bootstrap `tips`.
  Map<String, Object?> tips = <String, Object?>{
    'enabled': true,
    'mode': 'personal',
    'pool_rule': 'equal',
    'pool_period': 'day',
  };

  /// Bootstrap `staff_pay`.
  Map<String, Object?> staffPay = <String, Object?>{
    'clock': true,
    'session': null,
  };

  /// Bootstrap `booking_rules`.
  Map<String, Object?> bookingRules = <String, Object?>{
    'configured': true,
    'table_slot_minutes': 120,
    'room_slot_minutes': null,
    'tapchan_slot_minutes': null,
    'table_min_advance_minutes': 60,
    'room_min_advance_minutes': 0,
    'tapchan_min_advance_minutes': 0,
    'max_advance_days': null,
    'deposit_mode': 'per_guest',
    'deposit_amount': '50000.00',
    'deposit_from_guests': 6,
    'deposit_required_to_confirm': false,
  };

  /// Kutish ro'yxati (kelish tartibida).
  final List<Map<String, Object?>> waitlist = <Map<String, Object?>>[];

  /// Stol id → `cleaning`.
  final Map<int, String> serviceStates = <int, String>{};

  /// Kirgan xodimning choy pullari (`GET /pos/tips` `tips[]`).
  final List<Map<String, Object?>> tipRows = <Map<String, Object?>>[];

  /// Bugungi bronlar (`GET /pos/bookings`) va kelgan `POST /pos/bookings` tanalari.
  final List<Map<String, Object?>> bookings = <Map<String, Object?>>[];
  final List<Map<String, Object?>> bookingBodies = <Map<String, Object?>>[];

  /// Ish vaqti.
  Map<String, Object?>? clockSession;
  int todayMinutes = 0;

  int _seq = 0;

  /// Hostesni xodimlar ro'yxatiga qo'shadi.
  void addHostess() {
    h.state.staff.add(<String, Object?>{
      'id': hostessId,
      'name': hostessName,
      'role': 'hostess',
      'staff_code': '1007',
    });
    h.state.pins[hostessId] = hostessPin;
  }

  /// Menyuga restoran taomlari: Shashlik ×2/×5/×10 va 15 daqiqa, "Jigar" (six, ×N), "Sazan" (kg, keyin tortiladi).
  void seedMenu() {
    final Map<String, Object?> shashlik = h.state.products.firstWhere(
      (Map<String, Object?> p) => p['id'] == 4,
    );
    shashlik
      ..['quick_multipliers'] = <int>[2, 5, 10]
      ..['wait_minutes'] = 15;
    h.state.products
      ..add(
        h.state.sampleProduct(6, 2, 'Jigar', '15000.00')
          ..['quick_multipliers'] = <int>[2, 5, 10]
          ..['wait_minutes'] = 12,
      )
      ..add(
        h.state.sampleProduct(7, 2, 'Sazan', '245000.00')
          ..['weight_mode'] = 'raw'
          ..['variants'] = <Object?>[
            <String, Object?>{
              'id': 7,
              'uuid': FakePosState.variantUuid(7),
              'product_id': 7,
              'name': 'Mangal',
              'price': '245000.00',
              'base_price': '245000.00',
              'stock': 1000,
              'unit': 'kg',
              'quantity_step': null,
              'min_quantity': null,
              'batch_consumption': '1.000',
              'unit_label': 'kg',
              'is_available': true,
              'unavailable_reason': null,
            },
          ],
      );
  }

  Map<String, Object?> addWaiting(
    String name, {
    int party = 2,
    int? quoted = 15,
    int waited = 5,
    String? phone = '+998901234567',
  }) {
    final Map<String, Object?> e = <String, Object?>{
      'uuid': _uuid(),
      'branch_id': FakePosState.branchId,
      'business_date': '2026-10-22',
      'guest_name': name,
      'guest_phone': phone,
      'guest_phone_masked': phone == null ? null : '+99890*****67',
      'party_size': party,
      'quoted_wait_minutes': quoted,
      'note': null,
      'status': 'waiting',
      'status_label': 'Kutmoqda',
      'position': null,
      'waited_minutes': waited,
      'dining_table_id': null,
      'table_name': null,
      'order_uuid': null,
      'notified_at': null,
      'seated_at': null,
      'closed_at': null,
      'version': 1,
      'created_at': h.now.toIso8601String(),
    };
    waitlist.add(e);
    return e;
  }

  Map<String, Object?> addBooking({
    required String name,
    required DateTime startsAt,
    int tableId = 1,
    int guests = 4,
    String depositTotal = '0.00',
    String status = 'confirmed',
  }) {
    final Map<String, Object?> b = <String, Object?>{
      'uuid': _uuid(),
      'branch_id': FakePosState.branchId,
      'dining_table_id': tableId,
      'table_ids': <int>[tableId],
      'starts_at': startsAt.toUtc().toIso8601String(),
      'ends_at': startsAt
          .add(const Duration(hours: 2))
          .toUtc()
          .toIso8601String(),
      'guests_planned': guests,
      'event_type': 'gap',
      'event_type_label': 'Gap',
      'contact_name': name,
      'contact_phone': '+998901112233',
      'source': 'phone',
      'source_label': 'Telefon',
      'status': status,
      'status_label': status == 'confirmed' ? 'Tasdiqlangan' : "So'rov",
      'min_spend': null,
      'note': null,
      'order_uuid': null,
      'deposit_total': depositTotal,
      'deposit_available': depositTotal,
      'deposit_unsettled': false,
      'items_count': 0,
      'version': 1,
    };
    bookings.add(b);
    return b;
  }

  /// Kirgan ofitsiantga choy puli.
  void addTip(
    String amount, {
    String method = 'cash',
    String source = 'tender',
    int staffId = 4,
  }) {
    tipRows.add(<String, Object?>{
      'uuid': _uuid(),
      'method': method,
      'source': source,
      'amount': amount,
      'original_amount': amount,
      'mode': 'personal',
      'staff': <String, Object?>{'id': staffId, 'name': 'Botir Aliyev'},
      'holders': <Object?>[
        <String, Object?>{
          'mode': 'personal',
          'staff': <String, Object?>{'id': staffId, 'name': 'Botir Aliyev'},
          'amount': amount,
        },
      ],
      'business_date': '2026-10-22',
      'bill_uuid': null,
      'payment_uuid': null,
      'shift_uuid': null,
      'closed': false,
      'corrections': <Object?>[],
      'created_at': h.now.toIso8601String(),
    });
  }

  /// `order.course_ready` (oshxona kursni tugatdi).
  void courseReady(
    String orderUuid,
    int courseNo, {
    int? waiterId = 4,
    int? tableId = 1,
  }) => h.state.emit('order.course_ready', <String, Object?>{
    'order': <String, Object?>{
      'uuid': orderUuid,
      'status': 'open',
      'branch_id': FakePosState.branchId,
    },
    'course_no': courseNo,
    'version': h.state.orders[orderUuid]?['version'],
    'table_id': tableId,
    'waiter_id': waiterId,
    'item_ids': <int>[],
  });

  // --- yo'llar -----------------------------------------------------------------------------------------------------

  FakeResponse? _respond(RequestOptions o) {
    if (o.method != 'GET' || !o.uri.path.endsWith('/v1/pos/floor')) return null;
    final String? auth = o.headers['Authorization'] as String?;
    final FakeResponse r = h.state.handle(
      o.method,
      o.uri.path,
      o.uri.queryParameters,
      o.data,
      auth?.replaceFirst('Bearer ', ''),
    );
    final Object? body = r.body;
    if (body is Map && body['data'] is Map) {
      for (final Object? hall
          in (body['data'] as Map)['halls'] as List<Object?>) {
        for (final Object? t in (hall! as Map)['tables'] as List<Object?>) {
          final Map<Object?, Object?> table = t! as Map<Object?, Object?>;
          table['service_state'] = serviceStates[table['id']];
        }
      }
    }
    return r;
  }

  FakeResponse? _route(FakeRequest r) {
    final String p = r.path;
    final String m = r.method;
    if (m == 'GET' && p == '/v1/pos/waitlist') return _ok(_list());
    if (m == 'POST' && p == '/v1/pos/waitlist') {
      final Map<String, Object?> e = addWaiting(
        '${r.data['guest_name']}',
        party: (r.data['party_size']! as num).toInt(),
        quoted: (r.data['quoted_wait_minutes'] as num?)?.toInt(),
        waited: 0,
        phone: r.data['guest_phone'] as String?,
      )..['note'] = r.data['note'];
      _changed(e);
      return _ok(_present(e), status: 201);
    }
    final RegExpMatch? w = RegExp(
      r'^/v1/pos/waitlist/([^/]+)/(notify|seat|cancel|no-show)$',
    ).firstMatch(p);
    if (w != null && m == 'POST') {
      return _waitAction(w.group(1)!, w.group(2)!, r);
    }
    final RegExpMatch? t = RegExp(r'^/v1/pos/tables/(\d+)/state$')
        .firstMatch(p);
    if (t != null && m == 'PUT') {
      final int id = int.parse(t.group(1)!);
      if (r.data['state'] == 'cleaning') {
        serviceStates[id] = 'cleaning';
      } else {
        serviceStates.remove(id);
      }
      h.state.emit('table.state_changed', <String, Object?>{
        'table': <String, Object?>{
          'id': id,
          'branch_id': FakePosState.branchId,
          'service_state': serviceStates[id],
        },
      });
      return _ok(<String, Object?>{
        'id': id,
        'name': h.state.tables.firstWhere(
          (Map<String, Object?> x) => x['id'] == id,
        )['name'],
        'service_state': serviceStates[id],
        'service_state_at': h.now.toIso8601String(),
      });
    }
    final RegExpMatch? tr = RegExp(r'^/v1/pos/orders/([^/]+)/transfer$')
        .firstMatch(p);
    if (tr != null && m == 'POST') return _transfer(tr.group(1)!, r);
    final RegExpMatch? seat = RegExp(
      r'^/v1/pos/orders/([^/]+)/items/(\d+)/seat$',
    ).firstMatch(p);
    if (seat != null && m == 'POST') {
      final Map<String, Object?>? order = h.state.orders[seat.group(1)];
      if (order == null) return _fail(404, 'not_found', 'Buyurtma topilmadi.');
      for (final Object? i in order['items']! as List<Object?>) {
        final Map<String, Object?> item = i! as Map<String, Object?>;
        if (item['id'] == int.parse(seat.group(2)!)) {
          item['seat_no'] = r.data['seat_no'];
        }
      }
      order['version'] = (order['version']! as int) + 1;
      return _ok(order);
    }
    if (m == 'GET' && p == '/v1/pos/tips') {
      return _ok(<String, Object?>{
        'shift_uuid': null,
        'business_date': '2026-10-22',
        'own_only': r.member['role'] == 'waiter',
        'summary': <String, Object?>{
          'count': tipRows.length,
          'amount': '0.00',
          'by_method': <Object?>[],
          'personal': '0.00',
          'pool': '0.00',
          'unassigned': '0.00',
          'by_staff': <Object?>[],
          'corrections': <String, Object?>{'count': 0, 'amount': '0.00'},
        },
        'tips': tipRows,
      });
    }
    if (p == '/v1/pos/clock') return _ok(_clock());
    if (m == 'POST' && p == '/v1/pos/clock/in') {
      if (clockSession != null) {
        return _fail(409, 'already_clocked_in', 'Siz allaqachon ishdasiz.');
      }
      clockSession = <String, Object?>{
        'uuid': _uuid(),
        'business_date': '2026-10-22',
        'clock_in_at': h.now.toIso8601String(),
        'clock_out_at': null,
        'minutes': 0,
        'open': true,
        'abandoned': false,
      };
      return _ok(_clock(), message: 'Ishga keldingiz.');
    }
    if (m == 'POST' && p == '/v1/pos/clock/out') {
      if (clockSession == null) {
        return _fail(409, 'not_clocked_in', 'Siz hali ishga kelmagansiz.');
      }
      clockSession = null;
      todayMinutes += 60;
      return _ok(_clock(), message: 'Ishdan ketdingiz.');
    }
    if (m == 'GET' && p == '/v1/pos/bookings') {
      return _ok(<String, Object?>{'bookings': bookings});
    }
    if (m == 'POST' && p == '/v1/pos/bookings') {
      bookingBodies.add(r.data);
      final Map<String, Object?> b = addBooking(
        name: '${r.data['contact_name']}',
        startsAt: DateTime.parse('${r.data['starts_at']}'),
        tableId: (r.data['dining_table_id']! as num).toInt(),
        guests: (r.data['guests_planned'] as num?)?.toInt() ?? 2,
        status: 'pending',
      );
      return _ok(b, status: 201);
    }
    return null;
  }

  Map<String, Object?> _clock() => <String, Object?>{
    'session': clockSession,
    'today_minutes': todayMinutes,
    'business_date': '2026-10-22',
  };

  FakeResponse _waitAction(String uuid, String action, FakeRequest r) {
    final Map<String, Object?>? e = waitlist
        .where((Map<String, Object?> x) => x['uuid'] == uuid)
        .firstOrNull;
    if (e == null) return _fail(404, 'not_found', 'Yozuv topilmadi.');
    if (e['status'] != 'waiting') {
      return _fail(409, 'entry_closed', 'Bu yozuv yopilgan.');
    }
    final Object? version = r.data['version'];
    if (version != null && version != e['version']) {
      return _fail(409, 'stale_version', "Yozuvni boshqa qurilma o'zgartirdi.");
    }
    switch (action) {
      case 'notify':
        e['notified_at'] = h.now.toIso8601String();
      case 'seat':
        final int table = (r.data['dining_table_id']! as num).toInt();
        e
          ..['status'] = 'seated'
          ..['status_label'] = 'Joylashdi'
          ..['dining_table_id'] = table
          ..['table_name'] = h.state.tables.firstWhere(
            (Map<String, Object?> x) => x['id'] == table,
          )['name']
          ..['seated_at'] = h.now.toIso8601String();
        if (r.data['open_order'] == true) {
          final String order = h.state.seedOrder(
            tableId: table,
            guests: (e['party_size']! as num).toInt(),
            lines: const <(int, String)>[],
            sent: false,
            age: Duration.zero,
          );
          final Object? waiter = r.data['waiter_id'];
          if (waiter != null) {
            h.state.orders[order]!['waiter'] = <String, Object?>{
              'id': waiter,
              'name': 'Botir Aliyev',
            };
          }
          e['order_uuid'] = order;
        }
      case 'cancel':
        e
          ..['status'] = 'canceled'
          ..['status_label'] = 'Ketdi';
      case 'no-show':
        e
          ..['status'] = 'no_show'
          ..['status_label'] = 'Kelmadi';
    }
    e['version'] = (e['version']! as int) + 1;
    _changed(e);
    return _ok(_present(e));
  }

  FakeResponse _transfer(String uuid, FakeRequest r) {
    final Map<String, Object?>? order = h.state.orders[uuid];
    if (order == null) return _fail(404, 'not_found', 'Buyurtma topilmadi.');
    final FakeResponse? denied = h.state.authorize(
      r.member,
      'orders.transfer',
      r.data['approval_token'],
    );
    if (denied != null) return denied;
    final int to = (r.data['waiter_id']! as num).toInt();
    final Map<String, Object?>? waiter = h.state.staff
        .where(
          (Map<String, Object?> s) => s['id'] == to && s['role'] == 'waiter',
        )
        .firstOrNull;
    if (waiter == null) {
      return _fail(404, 'waiter_not_found', 'Ofitsiant topilmadi.');
    }
    final Object? from = (order['waiter'] as Map<Object?, Object?>?)?['id'];
    order
      ..['waiter'] = <String, Object?>{'id': to, 'name': waiter['name']}
      ..['version'] = (order['version']! as int) + 1;
    h.state.emit('order.transferred', <String, Object?>{
      'order': <String, Object?>{
        'uuid': uuid,
        'status': 'open',
        'branch_id': FakePosState.branchId,
      },
      'version': order['version'],
      'table_ref': (order['table'] as Map<Object?, Object?>?)?['name'],
      'from_waiter_id': from,
      'to_waiter_id': to,
    });
    return _ok(order, message: 'Stol boshqa ofitsiantga berildi.');
  }

  Map<String, Object?> _list() {
    var position = 0;
    final List<Map<String, Object?>> entries = <Map<String, Object?>>[
      for (final Map<String, Object?> e in waitlist) _present(e, ++position),
    ];
    final List<Map<String, Object?>> waiting = <Map<String, Object?>>[
      for (final Map<String, Object?> e in waitlist)
        if (e['status'] == 'waiting') e,
    ];
    return <String, Object?>{
      'branch_id': FakePosState.branchId,
      'date': '2026-10-22',
      'entries': entries,
      'summary': <String, Object?>{
        'total': waitlist.length,
        'waiting': waiting.length,
        'seated': waitlist
            .where((Map<String, Object?> e) => e['status'] == 'seated')
            .length,
        'canceled': 0,
        'no_show': 0,
        'party_size_total': 0,
        'avg_quoted_wait_minutes': null,
        'avg_seated_wait_minutes': null,
        'longest_waiting_minutes': waiting.isEmpty
            ? null
            : waiting
                  .map((Map<String, Object?> e) => e['waited_minutes']! as int)
                  .reduce((int a, int b) => a > b ? a : b),
      },
    };
  }

  /// Kutayotganlarga o'rni (1 dan).
  Map<String, Object?> _present(Map<String, Object?> e, [int? _]) {
    final int at = waitlist
        .where((Map<String, Object?> x) => x['status'] == 'waiting')
        .toList()
        .indexOf(e);
    return <String, Object?>{...e, 'position': at < 0 ? null : at + 1};
  }

  void _changed(Map<String, Object?> e) =>
      h.state.emit('waitlist.changed', <String, Object?>{
        'entry': <String, Object?>{
          'uuid': e['uuid'],
          'status': e['status'],
          'branch_id': FakePosState.branchId,
          'version': e['version'],
        },
      });

  String _uuid() =>
      '00000000-0000-4000-9000-${(++_seq).toString().padLeft(12, '0')}';

  static FakeResponse _ok(
    Object? data, {
    int status = 200,
    String message = 'OK',
  }) => FakeResponse(status, <String, Object?>{
    'success': true,
    'message': message,
    'data': data,
  });

  static FakeResponse _fail(int status, String reason, String message) =>
      FakeResponse(status, <String, Object?>{
        'success': false,
        'message': message,
        'data': <String, Object?>{'reason': reason},
      });
}

/// So'rov tanasi (JSON xarita).
Map<String, Object?> bodyOf(RequestOptions r) => r.data is Map
    ? (r.data! as Map<Object?, Object?>).cast<String, Object?>()
    : (jsonDecode(r.data! as String) as Map<Object?, Object?>)
          .cast<String, Object?>();
