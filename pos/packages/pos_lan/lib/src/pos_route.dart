import 'dart:async';

import 'package:pos_data/pos_data.dart';

import 'client.dart';
import 'link.dart';
import 'messages.dart';

/// pos_data's [PosLanRoute] over a [LanLink] (the waiter app): in `lan` mode the floor, the open orders, an order and
/// the operations on it go to the hub (README §6) instead of the server. The hub's refusal becomes
/// [PosOfflineUnavailable] with its reason and Uzbek message; no answer — [PosNetworkException] with the operation's
/// id as its `idempotencyKey` (sent again with it, the hub applies it once).
class LanPosRoute implements PosLanRoute {
  LanPosRoute(this.link) {
    _sub = link.events.listen(_onEvent);
  }

  final LanLink link;
  late final StreamSubscription<LanEvent> _sub;
  final StreamController<PosEvent> _events =
      StreamController<PosEvent>.broadcast();

  /// The hub's id of an order (the device's for one opened on the LAN) → the server's uuid, once the hub learnt it.
  final Map<String, String> _resolved = <String, String>{};

  @override
  bool get active => link.viaHub;

  @override
  Stream<PosEvent> get events => _events.stream;

  @override
  String? resolve(String ref) => _resolved[ref.toLowerCase()];

  @override
  Future<Map<String, Object?>> floor() async {
    final Object? floor = (await _state())['floor'];
    if (floor is! Map) {
      throw const PosOfflineUnavailable(
        "Kassada zal xaritasi hali yo'q: internet tiklangach yangilanadi.",
        reason: 'lan_no_floor',
      );
    }
    return floor.cast<String, Object?>();
  }

  @override
  Future<List<Map<String, Object?>>> openOrders() async {
    final Object? list = (await _state())['open_orders'];
    return <Map<String, Object?>>[
      if (list is List)
        for (final Object? o in list)
          if (o is Map) o.cast<String, Object?>(),
    ];
  }

  @override
  Future<Map<String, Object?>> order(String ref) async {
    Map<String, Object?>? order;
    try {
      order = await link.order(ref);
    } on LanUnreachable catch (e) {
      throw _network(e);
    }
    if (order == null) {
      throw const PosOfflineUnavailable(
        "Buyurtma kassada topilmadi: internet tiklangach ko'rinadi.",
        reason: 'not_found',
      );
    }
    _learn(ref, order);
    return order;
  }

  @override
  Future<Map<String, Object?>> op(
    String type,
    Map<String, Object?> payload, {
    String? opId,
  }) async {
    final String id = opId ?? newUuid();
    LanOpResult r;
    try {
      r = await link.send(type, payload, opId: id);
    } on LanUnreachable catch (e) {
      throw _network(e, opId: id);
    }
    if (!r.accepted) {
      throw PosOfflineUnavailable(
        r.message ?? "Kassa amalni qabul qilmadi.",
        reason: r.reason ?? 'refused',
      );
    }
    final String ref = '${payload['order']}';
    final Map<String, Object?>? order = r.order ?? await link.order(ref);
    if (order == null) {
      throw const PosOfflineUnavailable(
        "Buyurtma kassada topilmadi: internet tiklangach ko'rinadi.",
        reason: 'not_found',
      );
    }
    _learn(ref, order);
    return order;
  }

  Future<Map<String, Object?>> _state() async {
    try {
      return await link.state();
    } on LanUnreachable catch (e) {
      throw _network(e);
    }
  }

  static PosNetworkException _network(LanUnreachable e, {String? opId}) =>
      PosNetworkException(
        message: e.message ?? "Markaz kassa bilan aloqa yo'q.",
        cause: e,
        idempotencyKey: opId,
      );

  void _learn(String ref, Map<String, Object?> order) {
    final Object? uuid = order['uuid'];
    if (uuid is String && uuid.toLowerCase() != ref.toLowerCase()) {
      _resolved[ref.toLowerCase()] = uuid;
    }
  }

  void _onEvent(LanEvent e) {
    final Map<String, Object?> p = e.payload;
    final Object? orderRef = p['order'] is Map
        ? (p['order']! as Map)['uuid']
        : p['order'];
    void add(String name, Map<String, Object?> data) {
      if (!_events.isClosed) _events.add(PosEvent.fromWire(name, data));
    }

    switch (e.type) {
      case LanMsg.orderChanged:
        final Map<String, Object?> order = p['order'] is Map
            ? (p['order']! as Map).cast<String, Object?>()
            : const <String, Object?>{};
        final Object? ref = p['ref'];
        final Set<String> ids = <String>{
          if (ref is String) ref,
          if (order['uuid'] is String) order['uuid']! as String,
        };
        if (ref is String) _learn(ref, order);
        for (final String id in ids) {
          add('order.items_changed', <String, Object?>{
            'order': <String, Object?>{'uuid': id, 'status': order['status']},
            'version': order['version'],
            'change': 'lan',
          });
        }
      case LanMsg.kitchenTicket:
        if (orderRef is String) {
          add('order.items_changed', <String, Object?>{
            'order': <String, Object?>{'uuid': orderRef},
            'change': 'sent',
          });
        }
      case LanMsg.kitchenItemStatus:
        if (orderRef is String) {
          add('kitchen.item_status_changed', <String, Object?>{
            'order': <String, Object?>{'uuid': orderRef},
            'item': <String, Object?>{
              'id': p['line'],
              'kitchen_status': p['status'],
            },
          });
        }
      case LanMsg.queueReady || LanMsg.queueCalled || LanMsg.queueHanded:
        if (orderRef is String) {
          add(
            e.type == LanMsg.queueCalled ? LanMsg.queueReady : e.type,
            <String, Object?>{
              'order': <String, Object?>{'uuid': orderRef},
              'number': p['number'],
              'called': e.type == LanMsg.queueCalled,
            },
          );
        }
      case LanMsg.cartDisplay:
        add('display.cart', p);
      default:
        break;
    }
  }

  Future<void> dispose() async {
    await _sub.cancel();
    await _events.close();
  }
}
