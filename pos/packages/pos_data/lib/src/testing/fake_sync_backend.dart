import '../offline/sync_models.dart';
import 'fake_pos_state.dart';

/// P3-31: soxta backend'ning `POST /pos/sync` qismi: har amalni soxta backend'ning odatdagi yo'li orqali o'sha xodim
/// nomidan bajaradi (buyurtma ochish, bosqich, yuborish, o'chirish, "berildi", schyot, to'lov), `op_id` bo'yicha bir
/// marta; javob — haqiqiy backend'dagi shakl (`results[].status`, `refs`). Rad etilgan va farqli amallar "Sinxronlash
/// xatolari"da (`GET /pos/sync/errors`, `.../resolve`).
///
/// ```dart
/// final FakePosState state = FakePosState();
/// FakeCashierBackend(state);
/// final FakeSyncBackend sync = FakeSyncBackend(state);
/// sync.rejectNext[SyncOpType.orderRound] = 'order_closed';
/// ```
class FakeSyncBackend {
  FakeSyncBackend(this.state) {
    state.terminalRoutes = _terminal;
    state.syncRoutes = _staff;
  }

  final FakePosState state;

  /// Qo'llangan amallar (op_id → natija).
  final Map<String, Map<String, Object?>> results =
      <String, Map<String, Object?>>{};

  /// Hamma kelgan amallar (`seq` tartibida qo'llangan).
  final List<Map<String, Object?>> received = <Map<String, Object?>>[];

  /// Qurilma id'si → server uuid'i (buyurtma, schyot); qurilma qator id'si → server qator id'si.
  final Map<String, String> uuids = <String, String>{};
  final Map<String, int> lines = <String, int>{};

  /// Keyingi shu turdagi amal shu sabab bilan rad etiladi (sinash uchun; P3-35: `invalid_payload`,
  /// `modifier_not_found` ham).
  final Map<String, String> rejectNext = <String, String>{};

  /// P3-35: keyingi shu turdagi amal qo'llanadi va javobiga shu belgilar (`refs.flags`) qo'shiladi, masalan
  /// `{'flag': 'offline_price_mismatch', 'name': 'Osh', 'device_price': '40000.00', 'server_price': '45000.00'}`
  /// (holat `conflict`) yoki `{'flag': 'device_time_clamped', ...}` (faqat ma'lumot: `applied`).
  final Map<String, List<Map<String, Object?>>> flagNext =
      <String, List<Map<String, Object?>>>{};

  /// Serverdagidek: bu belgilar e'tibor talab qilmaydi (holat `applied` qoladi).
  static const Set<String> informationalFlags = <String>{
    'offline_price',
    'amount_mismatch',
    'local_number_taken',
    PosSyncReasons.deviceTimeClamped,
  };

  /// Server kassaning navbati haqida bilgani.
  int pendingReported = 0;

  /// Hal qilingan xatolar.
  final Set<String> resolved = <String>{};

  /// Har paket (`POST /pos/sync` chaqiruvlari soni).
  int batches = 0;

  List<Map<String, Object?>> get errors => <Map<String, Object?>>[
    for (final Map<String, Object?> r in results.values.toList().reversed)
      if (r['status'] != 'applied' && !resolved.contains(r['op_id'])) r,
  ];

  FakeResponse? _terminal(
    String method,
    String path,
    Map<String, String> query,
    Map<String, Object?> data,
  ) {
    if (method == 'POST' && path == '/v1/pos/sync') return _sync(data);
    if (method == 'GET' && path == '/v1/pos/sync/status') {
      if (query['queue_size'] != null) {
        pendingReported = int.tryParse(query['queue_size']!) ?? pendingReported;
      }
      return _ok(<String, Object?>{
        'server_time': state.now().toUtc().toIso8601String(),
        'device': _device(),
        'errors': <Object?>[
          for (final Map<String, Object?> e in errors) _error(e),
        ],
        'errors_count': errors.length,
      });
    }
    return null;
  }

  FakeResponse? _staff(FakeRequest r) {
    if (r.method == 'GET' && r.path == '/v1/pos/sync/errors') {
      final List<Map<String, Object?>> list = r.query['all'] == '1'
          ? <Map<String, Object?>>[
              for (final Map<String, Object?> e
                  in results.values.toList().reversed)
                if (e['status'] != 'applied') e,
            ]
          : errors;
      return _ok(<String, Object?>{
        'errors': <Object?>[
          for (final Map<String, Object?> e in list) _error(e),
        ],
        'meta': <String, Object?>{
          'current_page': 1,
          'last_page': 1,
          'per_page': 50,
          'total': list.length,
        },
      });
    }
    final RegExpMatch? m = RegExp(r'^/v1/pos/sync/errors/([^/]+)/resolve$')
        .firstMatch(r.path);
    if (r.method == 'POST' && m != null) {
      if (!state.permissionsOf(r.member).contains('orders.cancel')) {
        return const FakeResponse(403, <String, Object?>{
          'success': false,
          'message': 'Ruxsat etilmagan',
          'data': <String, Object?>{},
        });
      }
      final Map<String, Object?>? e = results[m.group(1)];
      if (e == null) {
        return const FakeResponse(404, <String, Object?>{
          'success': false,
          'message': 'Topilmadi',
          'data': <String, Object?>{},
        });
      }
      resolved.add(e['op_id']! as String);
      e['resolution'] = r.data['resolution'];
      e['resolved_at'] = state.now().toUtc().toIso8601String();
      return _ok(_error(e));
    }
    return null;
  }

  FakeResponse _sync(Map<String, Object?> data) {
    batches++;
    final List<Map<String, Object?>> ops =
        <Map<String, Object?>>[
          for (final Object? o
              in (data['operations'] as List<Object?>?) ?? const <Object?>[])
            if (o is Map) o.cast<String, Object?>(),
        ]..sort(
          (Map<String, Object?> a, Map<String, Object?> b) =>
              (a['seq']! as int).compareTo(b['seq']! as int),
        );
    final List<Object?> out = <Object?>[];
    for (final Map<String, Object?> op in ops) {
      final String id = op['op_id']! as String;
      final Map<String, Object?>? before = results[id];
      if (before != null) {
        out.add(<String, Object?>{...before, 'duplicate': true});
        continue;
      }
      received.add(op);
      final Map<String, Object?> r = _apply(op);
      results[id] = r;
      out.add(<String, Object?>{...r, 'duplicate': false});
    }
    final Map<String, Object?>? queue = (data['queue'] as Map?)
        ?.cast<String, Object?>();
    final int size = (queue?['size'] as num?)?.toInt() ?? 0;
    pendingReported = size - ops.length < 0 ? 0 : size - ops.length;
    return _ok(<String, Object?>{
      'server_time': state.now().toUtc().toIso8601String(),
      'clock_skew_seconds': 0,
      'clock_warning': false,
      'stopped_at': null,
      'results': out,
      'device': _device(),
    }, message: 'Sinxronlandi.');
  }

  Map<String, Object?> _apply(Map<String, Object?> op) {
    final String type = op['type']! as String;
    final Map<String, Object?> p =
        (op['payload'] as Map?)?.cast<String, Object?>() ?? <String, Object?>{};
    final int staff = (op['staff_id']! as num).toInt();
    final String? forced = rejectNext.remove(type);
    if (forced != null) {
      return _result(
        op,
        'rejected',
        forced,
        PosSyncReasons.describe(forced) ?? 'Serverda rad etildi ($forced).',
        const <String, Object?>{},
      );
    }

    final String? ref = p['order'] as String?;
    final String? orderUuid = ref == null ? null : (uuids[ref] ?? ref);
    FakeResponse res;
    final Map<String, Object?> refs = <String, Object?>{};
    switch (type) {
      case 'order.open':
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders',
          <String, Object?>{
            'channel': p['channel'],
            'table_id': ?p['table_id'],
            'guests_count': ?p['guests_count'],
            'external_id': ref,
            'note': ?p['note'],
            'table_label': ?p['table_label'],
            'items': _items(p['items']),
            if (p['send'] == true) 'send': true,
          },
        );
        final Map<String, Object?>? order = _data(res);
        if (order != null) {
          uuids[ref!] = order['uuid']! as String;
          _mapLines(p['items'], order, const <int>{});
        }
      case 'order.round':
        final Set<int> before = _itemIds(orderUuid!);
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders/$orderUuid/rounds',
          <String, Object?>{
            'version': _version(orderUuid),
            'items': _items(p['items']),
            'fire_mode': ?p['fire_mode'],
            if (p['send'] == true) 'send': true,
          },
        );
        final Map<String, Object?>? order = _data(res);
        if (order != null) _mapLines(p['items'], order, before);
      case 'order.send':
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders/$orderUuid/send',
          <String, Object?>{
            'version': _version(orderUuid!),
            if (p['lines'] != null)
              'item_ids': <Object?>[
                for (final Object? l in p['lines']! as List<Object?>) _line(l),
              ],
          },
        );
      case 'order.void':
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders/$orderUuid/items/${_line(p['line'])}/void',
          <String, Object?>{
            'version': _version(orderUuid!),
            'reason': ?p['reason'],
          },
        );
      case 'order.served':
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders/$orderUuid/items/${_line(p['line'])}/served',
          <String, Object?>{'version': _version(orderUuid!)},
        );
      case 'order.course_send':
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders/$orderUuid/courses/${p['course_no']}/send',
          <String, Object?>{'version': _version(orderUuid!)},
        );
      case 'order.weight':
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders/$orderUuid/items/${_line(p['line'])}/weight',
          <String, Object?>{
            'version': _version(orderUuid!),
            'quantity': p['quantity'],
          },
        );
      case 'bill.create':
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders/$orderUuid/bills',
          <String, Object?>{'version': _version(orderUuid!), 'mode': 'single'},
        );
        final Map<String, Object?>? billing = _data(res);
        for (final Object? b
            in (billing?['bills'] as List<Object?>?) ?? const <Object?>[]) {
          if (b is Map && b['status'] != 'void' && b['status'] != 'paid') {
            uuids[p['bill']! as String] = b['uuid']! as String;
            refs['bill_uuid'] = b['uuid'];
          }
        }
      case 'bill.pay':
        final String bill = uuids[p['bill']] ?? p['bill']! as String;
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/bills/$bill/payments',
          <String, Object?>{
            'tenders': p['tenders'],
            'shift_uuid': ?p['shift_uuid'],
          },
        );
        refs['bill_uuid'] = bill;
        final Map<String, Object?>? r = _data(res);
        refs['fiscal_status'] =
            ((r?['bill'] as Map?)?['fiscal'] as Map?)?['status'];
      default:
        res = const FakeResponse(200, <String, Object?>{
          'data': <String, Object?>{},
        });
    }

    if (orderUuid != null || ref != null) {
      refs['order_uuid'] = uuids[ref] ?? orderUuid;
    }
    refs['items'] = <String, Object?>{
      for (final MapEntry<String, int> e in lines.entries) e.key: e.value,
    };
    if (res.status >= 200 && res.status < 300) {
      final List<Map<String, Object?>> flags =
          flagNext.remove(type) ?? const <Map<String, Object?>>[];
      if (flags.isNotEmpty) refs['flags'] = flags;
      final List<Map<String, Object?>> attention = <Map<String, Object?>>[
        for (final Map<String, Object?> f in flags)
          if (!informationalFlags.contains(f['flag'])) f,
      ];
      if (attention.isEmpty) return _result(op, 'applied', null, null, refs);
      final Map<String, Object?> first = attention.first;
      final String flag = '${first['flag']}';
      return _result(
        op,
        'conflict',
        flag,
        flag == PosSyncReasons.offlinePriceMismatch
            ? "'${first['name']}' kassada ${first['device_price']} so'mdan sotilgan, lekin menyu nusxasi vaqtida "
                  "bunday narx bo'lmagan: server narxi (${first['server_price']} so'm) qo'yildi. Menejer tekshirsin."
            : PosSyncReasons.describe(flag),
        refs,
      );
    }
    final Map<String, Object?> body =
        (res.body as Map?)?.cast<String, Object?>() ?? <String, Object?>{};
    final String reason =
        ((body['data'] as Map?)?['reason'] as String?) ?? 'invalid';
    final String message = body['message'] as String? ?? 'Rad etildi.';
    return type == 'bill.pay'
        ? _result(
            op,
            'conflict',
            'payment_unallocated',
            "To'lov schyotga tushmadi: $message",
            refs,
          )
        : _result(op, 'rejected', reason, message, refs);
  }

  Map<String, Object?> _result(
    Map<String, Object?> op,
    String status,
    String? reason,
    String? message,
    Map<String, Object?> refs,
  ) => <String, Object?>{
    'op_id': op['op_id'],
    'seq': op['seq'],
    'type': op['type'],
    'status': status,
    'reason': reason,
    'message': message,
    'refs': refs,
    'payload': op['payload'],
    'occurred_at': op['occurred_at'],
    'staff_id': op['staff_id'],
  };

  Map<String, Object?> _error(Map<String, Object?> e) {
    final Map<String, Object?> p =
        (e['payload'] as Map?)?.cast<String, Object?>() ?? <String, Object?>{};
    String? name;
    for (final Map<String, Object?> s in state.staff) {
      if (s['id'] == e['staff_id']) name = s['name'] as String?;
    }
    return <String, Object?>{
      'op_id': e['op_id'],
      'seq': e['seq'],
      'type': e['type'],
      'type_label': _labels[e['type']] ?? e['type'],
      'status': e['status'],
      'reason': e['reason'],
      'message': e['message'],
      'staff': <String, Object?>{'id': e['staff_id'], 'name': name},
      'device': <String, Object?>{
        'uuid': FakePosState.deviceUuid,
        'name': state.deviceName,
      },
      'occurred_at': e['occurred_at'],
      'received_at': state.now().toUtc().toIso8601String(),
      'applied_at': null,
      'clock_skew_seconds': 0,
      'order': null,
      'payload': p,
      'refs': e['refs'],
      'resolved_at': e['resolved_at'],
      'resolved_by': null,
      'resolution': e['resolution'],
      'resolution_note': null,
    };
  }

  static const Map<String, String> _labels = <String, String>{
    'order.open': 'Buyurtma ochildi',
    'order.round': "Bosqich qo'shildi",
    'order.send': 'Oshxonaga yuborildi',
    'order.void': "Taom o'chirildi",
    'order.served': 'Taom berildi',
    'bill.create': 'Schyot tuzildi',
    'bill.pay': "To'lov olindi",
    'order.close': 'Buyurtma yopildi',
    'order.course_send': 'Kurs oshxonaga yuborildi',
    'order.weight': "Og'irlik kiritildi",
  };

  Map<String, Object?> _device() => <String, Object?>{
    'uuid': FakePosState.deviceUuid,
    'name': state.deviceName,
    'prefix': 'K1',
    'last_seq': received.isEmpty ? 0 : received.last['seq'],
    'pending_ops': pendingReported,
    'oldest_op_at': null,
    'reported_at': state.now().toUtc().toIso8601String(),
    'clock_skew_seconds': 0,
    'clock_warning': false,
  };

  List<Object?> _items(Object? items) => <Object?>[
    for (final Object? i in (items as List<Object?>?) ?? const <Object?>[])
      if (i is Map)
        <String, Object?>{
          'variant_id': i['variant_id'],
          'quantity': i['quantity'],
          if (i['modifiers'] is List &&
              (i['modifiers']! as List<Object?>).isNotEmpty)
            'modifiers': <Object?>[
              for (final Object? m in i['modifiers']! as List<Object?>)
                if (m is Map)
                  <String, Object?>{
                    'id': m['id'],
                    'quantity': m['quantity'] ?? 1,
                  },
            ],
          'note': ?i['note'],
          'seat_no': ?i['seat_no'],
          'fire_mode': ?i['fire_mode'],
          'course_no': ?i['course_no'],
          'awaiting_weight': ?i['awaiting_weight'],
        },
  ];

  void _mapLines(Object? sent, Map<String, Object?> order, Set<int> before) {
    final List<int> fresh = <int>[
      for (final Object? i
          in (order['items'] as List<Object?>?) ?? const <Object?>[])
        if (i is Map && !before.contains(i['id'])) (i['id']! as num).toInt(),
    ];
    var k = 0;
    for (final Object? l in (sent as List<Object?>?) ?? const <Object?>[]) {
      if (l is Map && l['line_id'] is String && k < fresh.length) {
        lines[l['line_id']! as String] = fresh[k];
      }
      k++;
    }
  }

  Object? _line(Object? ref) => ref is String ? (lines[ref] ?? ref) : ref;

  int _version(String uuid) {
    final Map<String, Object?>? o = state.orders[uuid];
    return (o?['version'] as num?)?.toInt() ?? 0;
  }

  Set<int> _itemIds(String uuid) => <int>{
    for (final Object? i
        in (state.orders[uuid]?['items'] as List<Object?>?) ??
            const <Object?>[])
      if (i is Map) (i['id']! as num).toInt(),
  };

  static Map<String, Object?>? _data(FakeResponse r) {
    if (r.status < 200 || r.status >= 300) return null;
    final Object? d = (r.body as Map?)?['data'];
    return d is Map ? d.cast<String, Object?>() : null;
  }

  static FakeResponse _ok(Map<String, Object?> data, {String message = ''}) =>
      FakeResponse(200, <String, Object?>{
        'success': true,
        'message': message,
        'data': data,
      });
}
