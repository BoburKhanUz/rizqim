import 'dart:convert';

import 'package:pos_core/pos_core.dart';
import 'package:pos_sync/pos_sync.dart';

import 'offline_pricing.dart';
import 'offline_settings.dart';

/// Internetsiz rejimdagi buyurtmalarning lokal ko'rinishi: serverdan olingan oxirgi nusxa (buyurtma, schyot, zal,
/// navbat) ustiga navbatdagi (va hali qayta yuklanmagan) amallar qo'yiladi. Natija — serverning o'z JSON shakli, shuning
/// uchun ilova uni oddiy modellar bilan (`PosOrder.fromJson` ...) o'qiydi va ekranlar o'zgarmaydi.
///
/// Narxlar amalning o'zida (menyu nusxasidan, oflayn olingan payt): bu yerda faqat `pos_core` hisobi ([OfflinePricing]).
/// Rad etilgan amal ko'rinishga qo'shilmaydi (serverda u bo'lmagan).
class OfflineOrderBook {
  OfflineOrderBook({required this.store, required this.settings});

  final SyncStore store;
  PosOfflineSettings Function() settings;

  static String orderKey(String uuid) => 'order:$uuid';
  static String billingKey(String uuid) => 'billing:$uuid';
  static const String floorKey = 'floor';
  static const String openOrdersKey = 'open_orders';

  /// Hamma lokal buyurtmalar (kalit — qurilma bilgan id: oflayn ochilganniki qurilma uuid'si).
  Map<String, OfflineOrderState> build() {
    final Map<String, OfflineOrderState> states = <String, OfflineOrderState>{};
    for (final SyncOperation op in store.operationsAfter(0)) {
      if (op.status == SyncOpStatus.rejected) continue;
      final String? ref = op.orderRef;
      if (ref == null) continue;
      final SyncDoc? base = store.doc(orderKey(store.resolve(ref)));
      if (base != null && base.seq >= op.seq) continue;
      final OfflineOrderState? state = states[ref] ?? _initial(ref, base, op);
      if (state == null) continue;
      states[ref] = state;
      _apply(state, op);
    }
    return states;
  }

  /// Schyot qaysi buyurtmaniki: navbatdagi amallar ko'rinishida yoki serverdan olingan schyot nusxalarida (onlayn
  /// tuzilib, uzilishdan keyin to'lanadigan schyot). Topilmasa `null`.
  (String, OfflineOrderState)? stateOfBill(String billUuid) {
    for (final MapEntry<String, OfflineOrderState> e in build().entries) {
      if (e.value.bill(billUuid) != null) return (e.key, e.value);
    }
    for (final String key in store.docKeys(prefix: 'billing:')) {
      final Map<String, Object?>? billing = _copy(store.doc(key)?.map);
      final bool has = _list(billing?['bills'])
          .any((Map<String, Object?> b) => b['uuid'] == billUuid);
      if (!has) continue;
      final String ref = key.substring('billing:'.length);
      final Map<String, Object?>? order = _copy(store.doc(orderKey(ref))?.map);
      if (order == null) return null;
      return (ref, OfflineOrderState.existing(ref, order, billing, settings()));
    }
    return null;
  }

  /// Buyurtmaning lokal holati (navbatdagi amallar bilan yoki serverdan olingan nusxasi); ko'rilmagan bo'lsa `null`.
  OfflineOrderState? state(String ref) {
    final OfflineOrderState? built = build()[ref];
    if (built != null) return built;
    final Map<String, Object?>? order = _copy(
      store.doc(orderKey(store.resolve(ref)))?.map,
    );
    if (order == null) return null;
    return OfflineOrderState.existing(
      ref,
      order,
      _copy(store.doc(billingKey(store.resolve(ref)))?.map),
      settings(),
    );
  }

  /// Bitta buyurtma (lokal o'zgarishlar bilan); hech qachon ko'rilmagan bo'lsa `null`.
  Map<String, Object?>? order(String ref) {
    final OfflineOrderState? state = build()[ref];
    if (state != null) return state.order;
    return _copy(store.doc(orderKey(store.resolve(ref)))?.map);
  }

  /// Buyurtmaning schyotlari (lokal o'zgarishlar bilan).
  Map<String, Object?>? billing(String ref) {
    final OfflineOrderState? state = build()[ref];
    if (state != null) return state.billingJson();
    final Map<String, Object?>? doc = _copy(
      store.doc(billingKey(store.resolve(ref)))?.map,
    );
    if (doc != null) return doc;
    final Map<String, Object?>? o = order(ref);
    return o == null ? null : OfflineOrderState._emptyBilling(o);
  }

  /// Zal xaritasi: oxirgi nusxa + oflayn ochilgan, o'zgargan va to'langan buyurtmalar.
  Map<String, Object?>? floor() {
    final Map<String, Object?>? base = _copy(store.doc(floorKey)?.map);
    if (base == null) return null;
    final Map<String, OfflineOrderState> states = build();
    final List<Map<String, Object?>> halls = _list(base['halls']);
    final List<Map<String, Object?>> untabled = _list(base['untabled_orders']);

    bool isState(Map<String, Object?> o) => states.values.any(
      (OfflineOrderState s) =>
          s.matches(o['uuid'] as String?) || store.resolve(s.ref) == o['uuid'],
    );
    for (final Map<String, Object?> hall in halls) {
      for (final Map<String, Object?> table in _list(hall['tables'])) {
        final List<Map<String, Object?>> orders = _list(table['orders'])
          ..removeWhere(isState);
        table['orders'] = orders;
      }
    }
    untabled.removeWhere(isState);

    for (final OfflineOrderState s in states.values) {
      if (!s.isOpen) continue;
      final Map<String, Object?> entry = s.floorEntry();
      final int? tableId = (s.order['table'] as Map?)?['id'] as int?;
      Map<String, Object?>? table;
      for (final Map<String, Object?> hall in halls) {
        for (final Map<String, Object?> t in _list(hall['tables'])) {
          if (t['id'] == tableId) table = t;
        }
      }
      if (table != null) {
        (table['orders']! as List<Object?>).add(entry);
      } else {
        untabled.add(entry);
      }
    }

    for (final Map<String, Object?> hall in halls) {
      for (final Map<String, Object?> table in _list(hall['tables'])) {
        final List<Object?> orders = table['orders']! as List<Object?>;
        if (orders.isEmpty && table['status'] != 'reserved_soon') {
          table['status'] = 'free';
        } else if (orders.isNotEmpty &&
            (table['status'] == null || table['status'] == 'free')) {
          table['status'] = 'occupied';
        }
      }
    }
    base['halls'] = halls;
    base['untabled_orders'] = untabled;
    return base;
  }

  /// Ochiq buyurtmalar navbati (`GET /pos/orders?scope=open` shakli).
  List<Map<String, Object?>> openOrders() {
    final List<Map<String, Object?>> base = _list(
      store.doc(openOrdersKey)?.value,
    );
    final Map<String, OfflineOrderState> states = build();
    base.removeWhere(
      (Map<String, Object?> o) => states.values.any(
        (OfflineOrderState s) =>
            s.matches(o['uuid'] as String?) ||
            store.resolve(s.ref) == o['uuid'],
      ),
    );
    for (final OfflineOrderState s in states.values) {
      if (s.isOpen) base.add(s.queueRow());
    }
    return base;
  }

  OfflineOrderState? _initial(String ref, SyncDoc? base, SyncOperation op) {
    if (op.type == SyncOpType.orderOpen) {
      return OfflineOrderState.created(ref, op, settings());
    }
    final Map<String, Object?>? order = _copy(base?.map);
    if (order == null) return null;
    final Map<String, Object?>? billing = _copy(
      store.doc(billingKey(store.resolve(ref)))?.map,
    );
    return OfflineOrderState.existing(ref, order, billing, settings());
  }

  void _apply(OfflineOrderState s, SyncOperation op) {
    switch (op.type) {
      case SyncOpType.orderOpen:
        s.addRound(op, 1);
      case SyncOpType.orderRound:
        s.addRound(op, s.rounds + 1);
      case SyncOpType.orderSend:
        s.send(op);
      case SyncOpType.orderVoid:
        s.voidLine(op);
      case SyncOpType.orderServed:
        s.served(op);
      case SyncOpType.billCreate:
        s.createBill(op);
      case SyncOpType.billPay:
        s.pay(op);
      case SyncOpType.orderClose:
        s.closeIfSettled(op.occurredAt);
    }
    s.recalculate();
    s.touch(op);
  }

  static Map<String, Object?>? _copy(Map<String, Object?>? m) => m == null
      ? null
      : (jsonDecode(jsonEncode(m)) as Map).cast<String, Object?>();

  static List<Map<String, Object?>> _list(Object? v) => <Map<String, Object?>>[
    for (final Object? e in (v as List<Object?>?) ?? const <Object?>[])
      if (e is Map) e.cast<String, Object?>(),
  ];
}

/// Bitta buyurtmaning lokal holati (serverdagi JSON shaklida) va uning schyotlari.
class OfflineOrderState {
  OfflineOrderState._(
    this.ref,
    this.order,
    this._billing,
    this._settings, {
    required this.created,
  });

  factory OfflineOrderState.created(
    String ref,
    SyncOperation op,
    PosOfflineSettings settings,
  ) {
    final Map<String, Object?> p = op.payload;
    final Map<String, Object?>? view = (p['view'] as Map?)
        ?.cast<String, Object?>();
    final String channel = p['channel'] as String? ?? 'pos_takeaway';
    final String opened = op.occurredAt.toUtc().toIso8601String();
    final String? local = p['local_number'] as String?;
    final Map<String, Object?> order = <String, Object?>{
      'uuid': ref,
      'short_code': local,
      'status': 'accepted',
      'channel': channel,
      'type': channel == 'pos_hall' ? 'dine_in' : 'pickup',
      'store_id': view?['store_id'],
      'branch_id': view?['branch_id'],
      'version': 1,
      'hall': view?['hall'],
      'table': view?['table'],
      'hall_ref': (view?['hall'] as Map?)?['name'],
      'table_ref': (view?['table'] as Map?)?['name'],
      'guests_count': p['guests_count'],
      'waiter': null,
      'opened_by': op.staffId,
      'device_id': null,
      'booking_id': null,
      'booking_uuid': null,
      'deposit_available': '0.00',
      'bill_locked': false,
      'bill_requested_at': null,
      'notes': p['note'],
      'opened_at': opened,
      'updated_at': opened,
      'subtotal': '0.00',
      'discount': '0.00',
      'service_charge_amount': '0.00',
      'place_fee_amount': '0.00',
      'room_fee_amount': '0.00',
      'min_spend_topup_amount': '0.00',
      'total_price': '0.00',
      'charges': <String, Object?>{
        'waived': <Object?>[],
        'waivers': <Object?>[],
      },
      'offline': <String, Object?>{
        'local_number': local,
        'created_at': opened,
        'synced_at': null,
        'flags': <Object?>[],
        'pending': true,
      },
      'rounds': 0,
      'items': <Object?>[],
    };
    return OfflineOrderState._(ref, order, null, settings, created: true);
  }

  factory OfflineOrderState.existing(
    String ref,
    Map<String, Object?> order,
    Map<String, Object?>? billing,
    PosOfflineSettings settings,
  ) => OfflineOrderState._(ref, order, billing, settings, created: false);

  /// Qurilma bilgan id (oflayn ochilganniki — qurilma uuid'si).
  final String ref;
  final Map<String, Object?> order;
  Map<String, Object?>? _billing;
  final PosOfflineSettings _settings;

  /// Oflayn ochilgan (serverda hali yo'q yoki endi yetib bordi).
  final bool created;

  bool matches(String? uuid) =>
      uuid != null && (uuid == ref || uuid == order['uuid']);

  bool get isOpen =>
      order['status'] != 'delivered' && order['status'] != 'canceled';

  int get rounds => (order['rounds'] as num?)?.toInt() ?? 0;

  List<Map<String, Object?>> get items => <Map<String, Object?>>[
    for (final Object? e in order['items']! as List<Object?>)
      if (e is Map<String, Object?>) e,
  ];

  // --- buyurtma ---------------------------------------------------------------------------------------------------

  void addRound(SyncOperation op, int roundNo) {
    final Map<String, Object?> p = op.payload;
    final List<Object?> target = order['items']! as List<Object?>;
    final List<Map<String, Object?>> lines = _maps(p['items']);
    final String? roundMode = p['fire_mode'] as String?;
    final bool send = p['send'] == true;
    final String at = op.occurredAt.toUtc().toIso8601String();
    final Map<String, Object?> addedBy = <String, Object?>{
      'id': op.staffId,
      'name': (p['view'] as Map?)?['staff_name'],
    };
    var i = 0;
    for (final Map<String, Object?> line in <Map<String, Object?>>[
      ...lines,
      ..._maps(p['auto_items']),
    ]) {
      i++;
      final PosQuantity qty = PosQuantity.parse(line['quantity']);
      final PosUnit unit = PosUnit.fromWire(line['unit'] as String?);
      final PosMoney price = PosMoney.parse(line['unit_price']);
      final List<Map<String, Object?>> mods = _maps(line['modifiers']);
      final PosMoney perUnit = PosMoney.sum(<PosMoney>[
        for (final Map<String, Object?> m in mods)
          PosMoney.parse(m['price_delta']) *
              ((m['quantity'] as num?)?.toInt() ?? 1),
      ]);
      final String fire = line['fire_mode'] as String? ?? roundMode ?? 'now';
      final bool sendNow = send && fire == 'now';
      target.add(<String, Object?>{
        'id': -(op.seq * 1000 + i),
        'client_line_id': line['line_id'],
        'round_no': roundNo,
        'product_variant_id': line['product_variant_id'],
        'product_name': line['name'] ?? '',
        'variant_name': line['variant_name'],
        'quantity': qty.toApiString(),
        'unit': unit.wire,
        'quantity_label': qty.label(
          unit,
          unitLabel: line['unit_label'] as String?,
        ),
        'unit_price': price.toApiString(),
        'modifiers': <Object?>[
          for (final Map<String, Object?> m in mods)
            <String, Object?>{
              'id': m['modifier_id'] ?? 0,
              'name': m['name'] ?? "Qo'shimcha",
              'group_name': m['group_name'],
              'quantity': m['quantity'] ?? 1,
              'price_delta': PosMoney.parse(m['price_delta']).toApiString(),
            },
        ],
        'modifiers_total': perUnit.toApiString(),
        'total_price': OfflinePricing.lineTotal(
          price,
          qty,
          perUnit,
          unit,
        ).toApiString(),
        'note': line['note'],
        'seat_no': line['seat_no'],
        'fire_mode': fire,
        'due_at': null,
        'kitchen_status': sendNow ? 'sent' : 'pending',
        'station_id': line['station_id'],
        'sent_at': sendNow ? at : null,
        'ready_at': null,
        'added_by': addedBy,
        'added_at': at,
        'voided_at': null,
        'voided_by': null,
        'void_reason': null,
        'offline': true,
        'auto': line['line_id'] == null,
      });
    }
    if (roundNo > rounds) order['rounds'] = roundNo;
    if (send) _started();
  }

  void send(SyncOperation op) {
    final List<Object?>? refs = op.payload['lines'] as List<Object?>?;
    final String at = op.occurredAt.toUtc().toIso8601String();
    var any = false;
    for (final Map<String, Object?> item in items) {
      if (item['kitchen_status'] != 'pending') continue;
      final bool pick = refs == null
          ? item['fire_mode'] != 'hold'
          : refs.any((Object? r) => _is(item, r));
      if (!pick) continue;
      item['kitchen_status'] = 'sent';
      item['sent_at'] = at;
      any = true;
    }
    if (any) _started();
  }

  void voidLine(SyncOperation op) {
    final Map<String, Object?>? item = line(op.payload['line']);
    if (item == null || item['kitchen_status'] == 'void') return;
    item['kitchen_status'] = 'void';
    item['voided_at'] = op.occurredAt.toUtc().toIso8601String();
    item['voided_by'] = <String, Object?>{'id': op.staffId, 'name': null};
    item['void_reason'] = op.payload['reason'];
  }

  void served(SyncOperation op) {
    final Map<String, Object?>? item = line(op.payload['line']);
    if (item == null) return;
    item['kitchen_status'] = 'served';
    item['ready_at'] ??= op.occurredAt.toUtc().toIso8601String();
  }

  /// Qator: qurilma id'si (uuid), server id'si yoki lokal (manfiy) id bo'yicha.
  Map<String, Object?>? line(Object? ref) {
    for (final Map<String, Object?> item in items) {
      if (_is(item, ref)) return item;
    }
    return null;
  }

  static bool _is(Map<String, Object?> item, Object? ref) =>
      ref != null &&
      (item['client_line_id'] == ref ||
          item['id'] == ref ||
          '${item['id']}' == '$ref');

  void _started() {
    if (order['status'] == 'accepted') order['status'] = 'preparing';
  }

  void touch(SyncOperation op) {
    order['version'] = ((order['version'] as num?)?.toInt() ?? 0) + 1;
    order['updated_at'] = op.occurredAt.toUtc().toIso8601String();
    final Object? offline = order['offline'];
    if (offline is Map<String, Object?>) {
      offline['pending'] = true;
    } else {
      order['offline'] = <String, Object?>{
        'local_number': null,
        'created_at': null,
        'synced_at': null,
        'flags': <Object?>[],
        'pending': true,
      };
    }
  }

  /// Xizmat qatorlari va jami (docs/POS_SPEC.md 2.7, oddiy qoidalar).
  void recalculate() {
    final PosMoney itemsTotal = PosMoney.sum(<PosMoney>[
      for (final Map<String, Object?> i in items)
        if (i['kitchen_status'] != 'void') PosMoney.parse(i['total_price']),
    ]);
    final PosMoney discount = PosMoney.parse(order['discount']);
    final PosMoney gap = itemsTotal - discount;
    final PosMoney after = gap.isPositive ? gap : PosMoney.zero;
    final Map<String, Object?> charges =
        (order['charges'] as Map?)?.cast<String, Object?>() ??
        <String, Object?>{};
    final Set<String> waived = <String>{
      for (final Object? w
          in (charges['waived'] as List<Object?>?) ?? const <Object?>[])
        '$w',
    };
    final String channel = order['channel'] as String? ?? 'pos_hall';
    final int? hallId = (order['hall'] as Map?)?['id'] as int?;
    final int? tableId = (order['table'] as Map?)?['id'] as int?;
    final int? guests = (order['guests_count'] as num?)?.toInt();
    final PosOfflineFee? fee = _settings.feeFor(hallId, channel);
    final String percent =
        fee?.serviceChargePercent ??
        (charges['service_charge_percent'] as String? ?? '0.00');

    final PosMoney service = waived.contains('service_charge')
        ? PosMoney.zero
        : OfflinePricing.percentSom(after, percent);
    final PosMoney place = waived.contains('place_fee')
        ? PosMoney.zero
        : fee != null
        ? OfflinePricing.placeFee(fee.placeFeeAmount, fee.placeFeeMode, guests)
        : created
        ? PosMoney.zero
        : PosMoney.parse(order['place_fee_amount']);
    final PosOfflinePlace? spot = tableId == null
        ? null
        : _settings.place(tableId);
    final PosMoney room = waived.contains('room_fee')
        ? PosMoney.zero
        : created
        ? (channel == 'pos_hall'
              ? (spot?.roomFee ?? PosMoney.zero)
              : PosMoney.zero)
        : PosMoney.parse(order['room_fee_amount']);
    final PosMoney minSpend = created
        ? (spot == null
              ? PosMoney.zero
              : (spot.minSpendMode == 'per_person'
                    ? spot.minSpend * ((guests ?? 0) < 1 ? 1 : guests!)
                    : spot.minSpend))
        : PosMoney.parse(charges['min_spend']);
    final PosMoney topup = waived.contains('min_spend_topup')
        ? PosMoney.zero
        : OfflinePricing.minSpendTopup(minSpend, 'per_table', guests, after);
    final PosMoney total = after + service + place + room + topup;

    order
      ..['subtotal'] = itemsTotal.toApiString()
      ..['service_charge_amount'] = service.toApiString()
      ..['place_fee_amount'] = place.toApiString()
      ..['room_fee_amount'] = room.toApiString()
      ..['min_spend_topup_amount'] = topup.toApiString()
      ..['total_price'] = total.toApiString();
    order['charges'] = <String, Object?>{
      ...charges,
      'items_subtotal': itemsTotal.toApiString(),
      'discount': discount.toApiString(),
      'service_charge_percent': service.isPositive ? percent : '0.00',
      'service_charge_amount': service.toApiString(),
      'place_fee_amount': place.toApiString(),
      'room_fee_amount': room.toApiString(),
      'min_spend': minSpend.toApiString(),
      'min_spend_topup_amount': topup.toApiString(),
      'total': total.toApiString(),
    };
    _syncBillingOrder();
  }

  // --- schyot ------------------------------------------------------------------------------------------------------

  Map<String, Object?> get _bill => _billing ??= _emptyBilling(order);

  static Map<String, Object?> _emptyBilling(Map<String, Object?> order) =>
      <String, Object?>{
        'order': <String, Object?>{
          'uuid': order['uuid'],
          'status': order['status'],
          'payment_status': 'pending',
          'version': order['version'],
          'total_price': order['total_price'] ?? '0.00',
          'billed_amount': '0.00',
          'paid_amount': '0.00',
          'unbilled_amount': order['total_price'] ?? '0.00',
          'fiscal_pending': false,
          'bills_out_of_date': false,
          'closed_at': null,
          'booking_uuid': order['booking_uuid'],
          'deposit_available': order['deposit_available'] ?? '0.00',
        },
        'bills': <Object?>[],
        'refunds': <Object?>[],
      };

  List<Map<String, Object?>> get bills => <Map<String, Object?>>[
    for (final Object? b
        in (_bill['bills'] as List<Object?>?) ?? const <Object?>[])
      if (b is Map<String, Object?>) b,
  ];

  /// Schyotdan pul olingan (server hisobi bilan, BillableOrder): `paid` yoki unda to'lov bor. Serverda `paying` holati
  /// yo'q: to'lanayotgan schyot chek chiqquncha `open`/`printed` bo'lib qoladi.
  static bool _taken(Map<String, Object?> b) =>
      b['status'] != 'void' &&
      (b['status'] == 'paid' ||
          _payments(b).isNotEmpty ||
          PosMoney.parse(b['paid_amount']).isPositive);

  /// To'liq to'langan (qarz qolmagan).
  static bool _covered(Map<String, Object?> b) =>
      b['status'] == 'paid' ||
      (_taken(b) && !PosMoney.parse(b['due_amount']).isPositive);

  /// Hali schyotga tushmagan (pul olingan schyotlardan tashqari) summa.
  PosMoney get leftToBill =>
      PosMoney.parse(order['total_price']) -
      PosMoney.sum(<PosMoney>[
        for (final Map<String, Object?> b in bills)
          if (_taken(b)) PosMoney.parse(b['total']),
      ]);

  Map<String, Object?>? bill(String uuid) {
    for (final Map<String, Object?> b in bills) {
      if (b['uuid'] == uuid) return b;
    }
    return null;
  }

  /// Bitta schyot: hali pul olinmagan schyotlar bekor bo'ladi, qolgan hamma narsa (pul olingan schyotlardagidan
  /// tashqari — qator miqdori, summasi va xizmatlar bo'yicha) yangi schyotga.
  void createBill(SyncOperation op) {
    final List<Map<String, Object?>> existing = bills;
    for (final Map<String, Object?> b in existing) {
      if (b['status'] != 'void' && !_taken(b)) b['status'] = 'void';
    }
    final List<Map<String, Object?>> taken = existing.where(_taken).toList();
    PosMoney takenOf(String key) => PosMoney.sum(<PosMoney>[
      for (final Map<String, Object?> b in taken) PosMoney.parse(b[key]),
    ]);
    String left(PosMoney whole, String key) {
      final PosMoney rest = whole - takenOf(key);
      return (rest.isPositive ? rest : PosMoney.zero).toApiString();
    }

    final Map<String, List<Map<String, Object?>>> shares =
        <String, List<Map<String, Object?>>>{};
    for (final Map<String, Object?> b in taken) {
      for (final Map<String, Object?> i in _maps(b['items'])) {
        (shares['${i['order_item_id']}'] ??= <Map<String, Object?>>[]).add(i);
      }
    }
    final PosMoney total =
        PosMoney.parse(order['total_price']) - takenOf('total');
    final List<Object?> lines = <Object?>[];
    var subtotal = PosMoney.zero;
    for (final Map<String, Object?> i in items) {
      if (i['kitchen_status'] == 'void') continue;
      final List<Map<String, Object?>> paid =
          shares['${i['id']}'] ?? const <Map<String, Object?>>[];
      final PosQuantity qty = paid.fold(
        PosQuantity.parse(i['quantity']),
        (PosQuantity q, Map<String, Object?> x) =>
            q - PosQuantity.parse(x['quantity']),
      );
      final PosMoney amount =
          PosMoney.parse(i['total_price']) -
          PosMoney.sum(<PosMoney>[
            for (final Map<String, Object?> x in paid)
              PosMoney.parse(x['amount']),
          ]);
      if (!amount.isPositive && !qty.isPositive) continue;
      subtotal = subtotal + amount;
      lines.add(<String, Object?>{
        'order_item_id': i['id'],
        'product_name': i['product_name'],
        'variant_name': i['variant_name'],
        'unit': i['unit'],
        'quantity': qty.toApiString(),
        'amount': amount.toApiString(),
        'discount_amount': '0.00',
      });
    }
    final int? guests = (order['guests_count'] as num?)?.toInt();
    final Map<String, Object?> bill = <String, Object?>{
      'uuid': op.payload['bill'],
      'order_uuid': order['uuid'],
      'number': existing.length + 1,
      'status': 'open',
      'split_mode': 'single',
      'subtotal': subtotal.toApiString(),
      'discount': left(PosMoney.parse(order['discount']), 'discount'),
      'room_fee_amount': left(
        PosMoney.parse(order['room_fee_amount']),
        'room_fee_amount',
      ),
      'min_spend_topup_amount': left(
        PosMoney.parse(order['min_spend_topup_amount']),
        'min_spend_topup_amount',
      ),
      'service_charge_amount': left(
        PosMoney.parse(order['service_charge_amount']),
        'service_charge_amount',
      ),
      'place_fee_amount': left(
        PosMoney.parse(order['place_fee_amount']),
        'place_fee_amount',
      ),
      'total': total.toApiString(),
      'paid_amount': '0.00',
      'due_amount': total.toApiString(),
      'guests_share': guests,
      'per_guest_amount': guests != null && guests > 0
          ? PosMoney(((total.tiyin + guests * 100 - 1) ~/ (guests * 100)) * 100)
                .toApiString()
          : null,
      'printed_count': 0,
      'printed_at': null,
      'paid_at': null,
      'fiscal': <String, Object?>{'status': null},
      'items': lines,
      'payments': <Object?>[],
      'created_at': op.occurredAt.toUtc().toIso8601String(),
      'offline': true,
    };
    (_bill['bills']! as List<Object?>).add(bill);
    _syncBillingOrder();
  }

  /// Schyot oflayn chop etildi (pre-check): server bilmaydi, faqat ko'rinish.
  void markPrinted(String billUuid, DateTime at) {
    final Map<String, Object?>? b = bill(billUuid);
    if (b == null) return;
    b['printed_count'] = ((b['printed_count'] as num?)?.toInt() ?? 0) + 1;
    b['printed_at'] ??= at.toUtc().toIso8601String();
    if (b['status'] == 'open') b['status'] = 'printed';
    order['bill_locked'] = true;
  }

  void pay(SyncOperation op) {
    final String? ref = op.payload['bill'] as String?;
    final Map<String, Object?>? b = ref == null ? null : bill(ref);
    if (b == null) return;
    final List<Object?> payments =
        (b['payments'] as List<Object?>?) ?? <Object?>[];
    var i = 0;
    for (final Map<String, Object?> t in _maps(op.payload['tenders'])) {
      i++;
      final PosMoney amount = PosMoney.parse(t['amount']);
      final PosMoney? tendered = t['tendered_amount'] == null
          ? null
          : PosMoney.parse(t['tendered_amount']);
      payments.add(<String, Object?>{
        'uuid': '${op.opId}-$i',
        'tender': t['tender'],
        'amount': amount.toApiString(),
        'tendered_amount': tendered?.toApiString(),
        'change_amount': tendered == null
            ? null
            : (tendered - amount).toApiString(),
        'card_type': t['card_type'],
        'transaction_id': t['transaction_id'],
        'card_last4': t['card_last4'],
        'reference': null,
        'status': 'paid',
        'paid_at': op.occurredAt.toUtc().toIso8601String(),
      });
    }
    b['payments'] = payments;
    final PosMoney paid = PosMoney.sum(<PosMoney>[
      for (final Map<String, Object?> p in _maps(payments))
        PosMoney.parse(p['amount']),
    ]);
    final PosMoney due = PosMoney.parse(b['total']) - paid;
    b['paid_amount'] = paid.toApiString();
    b['due_amount'] = (due.isPositive ? due : PosMoney.zero).toApiString();
    if (!due.isPositive) {
      // The money is taken; the sale receipt comes once the till is back online (the status stays open/printed, as
      // the server keeps it until the receipt is issued)
      b['fiscal'] = <String, Object?>{
        'status': 'offline',
        'receipt_uuid': null,
        'error': null,
      };
    }
    closeIfSettled(op.occurredAt);
    _syncBillingOrder();
  }

  /// Har bir schyot to'liq to'langan va schyotga tushmagan narsa qolmagan bo'lsa — buyurtma lokal yopiladi (stol
  /// bo'shaydi); sotuv cheki sinxronlashdan keyin chiqadi.
  void closeIfSettled(DateTime at) {
    final List<Map<String, Object?>> live = bills
        .where((Map<String, Object?> b) => b['status'] != 'void')
        .toList();
    if (live.isEmpty || live.any((Map<String, Object?> b) => !_covered(b))) {
      return;
    }
    final PosMoney billed = PosMoney.sum(<PosMoney>[
      for (final Map<String, Object?> b in live) PosMoney.parse(b['total']),
    ]);
    if (billed < PosMoney.parse(order['total_price'])) return;
    order['status'] = 'delivered';
    final Map<String, Object?> bo = _bill['order']! as Map<String, Object?>;
    bo['closed_at'] = at.toUtc().toIso8601String();
    bo['fiscal_pending'] = true;
  }

  void _syncBillingOrder() {
    final Map<String, Object?>? billing = _billing;
    if (billing == null) return;
    final Map<String, Object?> bo =
        (billing['order'] as Map?)?.cast<String, Object?>() ??
        <String, Object?>{};
    final List<Map<String, Object?>> live = bills
        .where((Map<String, Object?> b) => b['status'] != 'void')
        .toList();
    final PosMoney total = PosMoney.parse(order['total_price']);
    final PosMoney billed = PosMoney.sum(<PosMoney>[
      for (final Map<String, Object?> b in live) PosMoney.parse(b['total']),
    ]);
    final PosMoney paid = PosMoney.sum(<PosMoney>[
      for (final Map<String, Object?> b in live)
        PosMoney.parse(b['paid_amount']),
    ]);
    final PosMoney unbilled = total - billed;
    bo
      ..['uuid'] = order['uuid']
      ..['status'] = order['status']
      ..['version'] = order['version']
      ..['total_price'] = total.toApiString()
      ..['billed_amount'] = billed.toApiString()
      ..['paid_amount'] = paid.toApiString()
      ..['unbilled_amount'] = (unbilled.isPositive ? unbilled : PosMoney.zero)
          .toApiString()
      ..['bills_out_of_date'] =
          live.any((Map<String, Object?> b) => !_taken(b)) &&
          unbilled.isPositive;
    if (order['bill_locked'] == true) {
      bo['bill_locked_at'] ??= order['updated_at'];
    }
    billing['order'] = bo;
  }

  Map<String, Object?> billingJson() {
    _bill;
    _syncBillingOrder();
    return _bill;
  }

  static List<Map<String, Object?>> _payments(Map<String, Object?> b) =>
      _maps(b['payments']);

  // --- ro'yxatlar --------------------------------------------------------------------------------------------------

  Map<String, Object?> floorEntry() => <String, Object?>{
    'uuid': order['uuid'],
    'short_code': order['short_code'],
    'status': order['status'],
    'channel': order['channel'],
    'version': order['version'],
    'opened_at': order['opened_at'],
    'open_minutes': 0,
    'overdue': false,
    'bill_printed': order['bill_locked'] == true,
    'guests_count': order['guests_count'],
    'waiter': order['waiter'],
    'total_price': order['total_price'],
    'items_count': items
        .where((Map<String, Object?> i) => i['kitchen_status'] != 'void')
        .length,
    'unsent_count': items
        .where((Map<String, Object?> i) => i['kitchen_status'] == 'pending')
        .length,
    'ready_count': items
        .where((Map<String, Object?> i) => i['kitchen_status'] == 'ready')
        .length,
    'bill_requested': false,
    'bill_requested_at': null,
  };

  Map<String, Object?> queueRow() => <String, Object?>{
    'uuid': order['uuid'],
    'short_code': order['short_code'],
    'status': order['status'],
    'channel': order['channel'],
    'type': order['type'],
    'branch_id': order['branch_id'],
    'version': order['version'],
    'hall': order['hall'],
    'table': order['table'],
    'hall_ref': order['hall_ref'],
    'table_ref': order['table_ref'],
    'guests_count': order['guests_count'],
    'waiter': order['waiter'],
    'items_count': items
        .where((Map<String, Object?> i) => i['kitchen_status'] != 'void')
        .length,
    'subtotal': order['subtotal'],
    'total_price': order['total_price'],
    'payment_status': 'pending',
    'bill_locked': order['bill_locked'] == true,
    'bill_requested': false,
    'fiscal_pending': false,
    'booking_id': null,
    'business_date': _settings.businessDate,
    'opened_at': order['opened_at'],
    'closed_at': null,
    'updated_at': order['updated_at'],
    'online': null,
  };

  static List<Map<String, Object?>> _maps(Object? v) => <Map<String, Object?>>[
    for (final Object? e in (v as List<Object?>?) ?? const <Object?>[])
      if (e is Map) e.cast<String, Object?>(),
  ];
}
