import 'dart:async';

import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';

import 'messages.dart';

/// The till's [LanOpApplier] (README §6): a device's operation is applied through the till's own [PosOffline] rules
/// (prices from the till's menu copy, the 500-order limit, the 24-hour menu and contact rule, rooms, no void after
/// sending), as the staff member of the device's grant (their permissions; the operation is queued with the device's
/// `op_id` and as its origin), plus what the server checks too: the permission, the branch's modules and the waiter
/// rule. Every change of the till's queue (its own operations too) becomes `order.changed` and the kitchen's events.
class PosOfflineLanApplier implements LanOpApplier {
  PosOfflineLanApplier({required this.offline, this.printsBill}) {
    _lastSeq = offline.store.lastSeq;
    _subs
      ..add(offline.store.changes.listen((_) => _onStoreChange()))
      ..add(offline.changes.listen((_) => _status()));
  }

  final PosOffline offline;

  /// P6 B bosqichi: markaz shu `bill.request` ning pre-check'ini o'zi chiqaradimi (kassa internetsiz va filialda
  /// "schyotni avtomatik chop etish" yoqilgan). `true` bo'lsa amal navbatga `printed_on_lan: true` bilan yoziladi
  /// (server navbat yetganda qayta chiqarmaydi); chiqarishni kassa navbatdagi shu amalni ko'rib bajaradi.
  ///
  /// Review P6 C6: it may print before answering ([LanBillPrints.decide]): `true` only when the pre-check really went to
  /// the printer, so the flag never says printed for a paper that did not come out.
  final FutureOr<bool> Function(LanRemoteOp op)? printsBill;

  late int _lastSeq;
  bool? _lastCloud;
  int? _lastPending;
  final List<StreamSubscription<Object?>> _subs =
      <StreamSubscription<Object?>>[];
  final StreamController<LanEvent> _events =
      StreamController<LanEvent>.broadcast();
  final StreamController<void> _statusChanges =
      StreamController<void>.broadcast();

  @override
  Stream<LanEvent> get events => _events.stream;

  @override
  Stream<void> get statusChanges => _statusChanges.stream;

  @override
  bool get cloud => offline.connectivity.state.online;

  @override
  int get pending => offline.store.pendingCount;

  @override
  Map<String, Object?> state() => <String, Object?>{
    'floor': offline.book.floor(),
    'open_orders': offline.book.openOrders(),
  };

  @override
  Map<String, Object?>? order(String ref) => offline.book.order(ref);

  @override
  Future<LanOpResult> apply(LanRemoteOp op, LanGrantView staff) async {
    final String id = op.opId;
    // The device sent it again (its answer was lost on the Wi-Fi): applied once
    if (offline.store.find(id) != null) {
      return LanOpResult(
        opId: id,
        accepted: true,
        duplicate: true,
        order: op.order == null ? null : offline.book.order(op.order!),
      );
    }
    final String permission = LanOpType.permission(op.type);
    if (!staff.can(permission)) {
      return LanOpResult.refused(
        id,
        'permission_denied',
        "${staff.name ?? 'Xodim'} uchun bu amalga ruxsat yo'q.",
      );
    }
    final String? module = LanOpType.module(op.type);
    final PosModule? needed = module == null
        ? null
        : PosModule.fromWire(module);
    if (needed != null &&
        !(offline.bootstrap()?.modules.has(needed) ?? false)) {
      return LanOpResult.refused(
        id,
        'module_disabled',
        "Filialda '${needed.label}' moduli yoqilmagan.",
      );
    }
    final String? ref = op.order;
    if (ref == null || ref.isEmpty) {
      return LanOpResult.refused(
        id,
        'invalid_payload',
        "Amalda buyurtma ko'rsatilmagan.",
      );
    }
    // The waiter rule (WaiterAccess): a waiter changes only their own order or one without a waiter; offline nobody
    // can approve another waiter's
    if (staff.isWaiter && op.type != LanOpType.orderOpen) {
      final Map<String, Object?>? seen = offline.book.order(ref);
      final Object? waiter = (seen?['waiter'] as Map?)?['id'];
      if (waiter is num && waiter.toInt() != staff.staffId) {
        return LanOpResult.refused(
          id,
          'approval_required',
          "Bu buyurtma boshqa ofitsiantniki: uni o'zgartirish uchun internet va menejer tasdig'i kerak.",
        );
      }
    }

    final PosOfflineActor actor = PosOfflineActor(
      staffId: staff.staffId,
      staffName: staff.name,
      role: staff.role,
      device: staff.device,
      permissions: staff.permissions,
      opId: id,
    );
    try {
      await offline.actAs(actor, () => _run(op, staff));
    } on PosOfflineUnavailable catch (e) {
      return LanOpResult.refused(
        id,
        e.reason ?? 'offline_unavailable',
        e.message,
      );
    } on FormatException {
      return LanOpResult.refused(
        id,
        'invalid_payload',
        "Amal ma'lumotlari noto'g'ri.",
      );
    } on TypeError {
      return LanOpResult.refused(
        id,
        'invalid_payload',
        "Amal ma'lumotlari noto'g'ri.",
      );
    } on ArgumentError {
      return LanOpResult.refused(
        id,
        'invalid_payload',
        "Amal ma'lumotlari noto'g'ri.",
      );
    }
    // Queued (or nothing to do, e.g. the bill asked already): the order as the till shows it now
    return LanOpResult(
      opId: id,
      accepted: true,
      order: offline.book.order(ref),
    );
  }

  Future<void> _run(LanRemoteOp op, LanGrantView staff) async {
    final Map<String, Object?> p = op.payload;
    final String ref = op.order!;
    // Review P6 C1: the device's op_id is its request's Idempotency-Key (the waiter app retries an online request whose
    // answer was lost over the LAN with the same key): the server replays what it did online instead of doing it twice
    final String key = op.opId;
    switch (op.type) {
      case LanOpType.orderOpen:
        final PosChannel? channel = PosChannel.fromWire(
          p['channel'] as String?,
        );
        if (channel == null) throw const FormatException('channel');
        await offline.open(
          channel: channel,
          externalId: ref,
          tableId: (p['table_id'] as num?)?.toInt(),
          guestsCount: (p['guests_count'] as num?)?.toInt(),
          note: p['note'] as String?,
          tableLabel: p['table_label'] as String?,
          items: LanLines.parse(p['items']),
          send: p['send'] == true,
          // A waiter's order is theirs (the server does the same)
          waiterId: staff.isWaiter ? staff.staffId : null,
          waiterName: staff.isWaiter ? staff.name : null,
          idempotencyKey: key,
        );
      case LanOpType.orderRound:
        await offline.addRound(
          ref,
          items: LanLines.parse(p['items']),
          fireMode: PosFireMode.fromWire(p['fire_mode'] as String?),
          send: p['send'] == true,
          idempotencyKey: key,
        );
      case LanOpType.orderSend:
        final Object? lines = p['lines'];
        await offline.send(
          ref,
          itemIds: lines is List
              ? <int>[for (final Object? l in lines) _id(l)]
              : null,
          idempotencyKey: key,
        );
      case LanOpType.orderCourseSend:
        await offline.sendCourse(ref, _id(p['course_no']), idempotencyKey: key);
      case LanOpType.orderWeight:
        final PosQuantity? q = PosQuantity.tryParse(p['quantity']);
        if (q == null || !q.isPositive) throw const FormatException('quantity');
        await offline.setWeight(
          ref,
          _id(p['line']),
          quantity: q,
          idempotencyKey: key,
        );
      case LanOpType.itemsVoid:
        await offline.voidItem(
          ref,
          _id(p['line']),
          reason: p['reason'] as String?,
          idempotencyKey: key,
        );
      case LanOpType.orderServed:
        await offline.markServed(ref, _id(p['line']), idempotencyKey: key);
      case LanOpType.kitchenBump:
        await offline.kitchenBump(ref, _id(p['line']));
      case LanOpType.kitchenRecall:
        await offline.kitchenRecall(ref, _id(p['line']));
      case LanOpType.kitchenAssembled:
        await offline.kitchenAssembled(ref, idempotencyKey: key);
      case LanOpType.billRequest:
        await offline.requestBill(
          ref,
          printedOnLan: await printsBill?.call(op) ?? false,
          idempotencyKey: key,
        );
      default:
        throw const PosOfflineUnavailable(
          'Bu amal kassada bajariladi.',
          reason: 'not_allowed_on_lan',
        );
    }
  }

  static int _id(Object? v) {
    if (v is int) return v;
    if (v is String) {
      final int? n = int.tryParse(v);
      if (n != null) return n;
    }
    throw const FormatException('id');
  }

  // --- events -------------------------------------------------------------------------------------------------------

  void _onStoreChange() {
    final List<SyncOperation> fresh = offline.store.operationsAfter(_lastSeq);
    if (fresh.isNotEmpty) _lastSeq = fresh.last.seq;
    final Set<String> touched = <String>{};
    for (final SyncOperation op in fresh) {
      final String? ref = op.orderRef;
      if (ref == null) continue;
      touched.add(ref);
      final Map<String, Object?>? order = offline.book.order(ref);
      if (order == null) continue;
      for (final LanEvent e in _kitchenEvents(op, ref, order)) {
        _events.add(e);
      }
    }
    for (final String ref in touched) {
      final Map<String, Object?>? order = offline.book.order(ref);
      if (order != null) {
        _events.add(
          LanEvent(LanMsg.orderChanged, <String, Object?>{
            'order': order,
            'ref': ref,
          }),
        );
      }
    }
    _status();
  }

  Iterable<LanEvent> _kitchenEvents(
    SyncOperation op,
    String ref,
    Map<String, Object?> order,
  ) sync* {
    final List<Map<String, Object?>> items = <Map<String, Object?>>[
      for (final Object? i
          in (order['items'] as List<Object?>?) ?? const <Object?>[])
        if (i is Map) i.cast<String, Object?>(),
    ];
    final String at = op.occurredAt.toUtc().toIso8601String();
    final bool sends = switch (op.type) {
      SyncOpType.orderOpen ||
      SyncOpType.orderRound => op.payload['send'] == true,
      SyncOpType.orderSend || SyncOpType.orderCourseSend => true,
      _ => false,
    };
    if (sends) {
      final List<Map<String, Object?>> lines = <Map<String, Object?>>[
        for (final Map<String, Object?> i in items)
          if (i['sent_at'] == at && i['kitchen_status'] != 'void')
            <String, Object?>{
              'id': i['id'],
              'client_line_id': i['client_line_id'],
              'name': i['product_name'],
              'variant_name': i['variant_name'],
              'quantity': i['quantity'],
              'unit_label': i['quantity_label'],
              'station_id': i['station_id'],
              'course_no': i['course_no'],
              'note': i['note'],
              'modifiers': i['modifiers'],
            },
      ];
      if (lines.isNotEmpty) {
        yield LanEvent(LanMsg.kitchenTicket, <String, Object?>{
          'order': ref,
          'short_code': order['short_code'],
          'table_ref': order['table_ref'],
          'hall_ref': order['hall_ref'],
          'lines': lines,
          'sent_at': at,
        });
      }
    }
    final String? status = switch (op.type) {
      SyncOpType.kitchenBump => 'ready',
      SyncOpType.kitchenRecall => 'sent',
      SyncOpType.orderServed => 'served',
      SyncOpType.orderVoid => 'void',
      _ => null,
    };
    final Object? line = op.payload['line'];
    if (status != null && line != null) {
      final Map<String, Object?>? item = items
          .cast<Map<String, Object?>?>()
          .firstWhere(
            (Map<String, Object?>? i) =>
                i!['id'] == line || i['client_line_id'] == line,
            orElse: () => null,
          );
      yield LanEvent(LanMsg.kitchenItemStatus, <String, Object?>{
        'order': ref,
        'line': item?['id'] ?? line,
        'status': status,
      });
    }
  }

  void _status() {
    final bool c = cloud;
    final int n = pending;
    if (c == _lastCloud && n == _lastPending) return;
    _lastCloud = c;
    _lastPending = n;
    if (!_statusChanges.isClosed) _statusChanges.add(null);
  }

  Future<void> dispose() async {
    for (final StreamSubscription<Object?> s in _subs) {
      await s.cancel();
    }
    await _events.close();
    await _statusChanges.close();
  }
}

/// The lines of a round as a device sends them (`POST /pos/orders` `items[]`) → the till's [PosLineInput].
abstract final class LanLines {
  static List<PosLineInput> parse(Object? items) {
    if (items == null) return const <PosLineInput>[];
    if (items is! List) throw const FormatException('items');
    return <PosLineInput>[
      for (final Object? i in items)
        if (i is Map)
          _line(i.cast<String, Object?>())
        else
          throw const FormatException('item'),
    ];
  }

  static PosLineInput _line(Map<String, Object?> j) {
    final Object? variant = j['variant_id'];
    final PosQuantity? qty = PosQuantity.tryParse(j['quantity']);
    if (variant is! String || qty == null || !qty.isPositive) {
      throw const FormatException('line');
    }
    final Object? due = j['due_at'];
    return PosLineInput(
      variantUuid: variant,
      quantity: qty,
      modifiers: <PosModifierChoice>[
        for (final Object? m
            in (j['modifiers'] as List<Object?>?) ?? const <Object?>[])
          if (m is Map && m['id'] is String)
            PosModifierChoice(
              m['id']! as String,
              quantity: (m['quantity'] as num?)?.toInt() ?? 1,
            ),
      ],
      note: j['note'] as String?,
      seatNo: (j['seat_no'] as num?)?.toInt(),
      fireMode: PosFireMode.fromWire(j['fire_mode'] as String?),
      dueAt: due is String ? DateTime.tryParse(due) : null,
      courseNo: (j['course_no'] as num?)?.toInt(),
      awaitingWeight: j['awaiting_weight'] as bool?,
      combo: j['combo'] is List
          ? <PosComboPick>[
              for (final Object? c in j['combo']! as List<Object?>)
                if (c is Map && c['option_id'] is String)
                  PosComboPick(
                    c['option_id']! as String,
                    quantity: (c['quantity'] as num?)?.toInt() ?? 1,
                    modifiers: c['modifiers'] is List
                        ? <String, int>{
                            for (final Object? m
                                in c['modifiers']! as List<Object?>)
                              if (m is Map && m['id'] is String)
                                m['id']! as String:
                                    (m['quantity'] as num?)?.toInt() ?? 1,
                          }
                        : null,
                  ),
            ]
          : null,
    );
  }
}
