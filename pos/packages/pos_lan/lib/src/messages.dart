/// Frame types of the bridge (README §6).
abstract final class LanMsg {
  // device → hub
  static const String hello = 'hello';
  static const String grant = 'grant';
  static const String op = 'op';
  static const String stateGet = 'state.get';
  static const String orderGet = 'order.get';
  static const String ping = 'ping';

  // hub → devices
  static const String welcome = 'welcome';
  static const String opResult = 'op.result';
  static const String state = 'state';
  static const String orderChanged = 'order.changed';
  static const String kitchenTicket = 'kitchen.ticket';
  static const String kitchenItemStatus = 'kitchen.item_status';
  static const String queueReady = 'queue.ready';
  static const String queueCalled = 'queue.called';
  static const String queueHanded = 'queue.handed';
  static const String cartDisplay = 'cart.display';
  static const String lanState = 'lan.state';
  static const String pong = 'pong';
  static const String error = 'error';
}

/// The operations a device may send the hub (README §6) and what each needs: the same permission as on the server
/// (`PosSyncOperationType::permissions`) and the branch's module. Payments, bills, closing, refunds and discounts are
/// not here: they stay on the till itself.
abstract final class LanOpType {
  static const String orderOpen = 'order.open';
  static const String orderRound = 'order.round';
  static const String orderSend = 'order.send';
  static const String orderCourseSend = 'order.course_send';
  static const String orderWeight = 'order.weight';
  static const String itemsVoid = 'order.items.void';
  static const String orderServed = 'order.served';
  static const String kitchenBump = 'kitchen.bump';
  static const String kitchenRecall = 'kitchen.recall';
  static const String kitchenAssembled = 'kitchen.assembled';
  static const String billRequest = 'bill.request';

  static const List<String> all = <String>[
    orderOpen,
    orderRound,
    orderSend,
    orderCourseSend,
    orderWeight,
    itemsVoid,
    orderServed,
    kitchenBump,
    kitchenRecall,
    kitchenAssembled,
    billRequest,
  ];

  static bool allowed(String type) => all.contains(type);

  static String permission(String type) => switch (type) {
    kitchenBump || kitchenRecall || kitchenAssembled => 'kitchen.manage',
    _ => 'orders.manage',
  };

  /// The format module the operation needs (`null`: none).
  static String? module(String type) => switch (type) {
    orderCourseSend => 'courses',
    orderWeight => 'weight_later',
    kitchenAssembled => 'kds_expo',
    _ => null,
  };
}

/// Which app a device runs (the `hello`).
enum LanApp {
  cashier('cashier'),
  waiter('waiter'),
  kds('kds'),
  display('display');

  const LanApp(this.wire);

  final String wire;

  static LanApp? fromWire(Object? value) {
    for (final LanApp a in values) {
      if (a.wire == value) return a;
    }
    return null;
  }
}

/// A device's operation as the hub received it.
class LanRemoteOp {
  const LanRemoteOp({
    required this.opId,
    required this.type,
    required this.device,
    required this.staffId,
    required this.payload,
    this.occurredAt,
  });

  final String opId;
  final String type;

  /// The device it came from (its uuid).
  final String device;
  final int staffId;
  final DateTime? occurredAt;
  final Map<String, Object?> payload;

  /// The order it touches (the id the device knows it by).
  String? get order => payload['order'] as String?;
}

/// The hub's answer to an operation (`op.result`).
class LanOpResult {
  const LanOpResult({
    required this.opId,
    required this.accepted,
    this.duplicate = false,
    this.reason,
    this.message,
    this.order,
  });

  factory LanOpResult.refused(String opId, String reason, String message) =>
      LanOpResult(
        opId: opId,
        accepted: false,
        reason: reason,
        message: message,
      );

  factory LanOpResult.fromJson(Map<String, Object?> j) => LanOpResult(
    opId: j['op_id'] as String? ?? '',
    accepted: j['status'] == 'accepted',
    duplicate: j['duplicate'] == true,
    reason: j['reason'] as String?,
    message: j['message'] as String?,
    order: j['order'] is Map
        ? (j['order']! as Map).cast<String, Object?>()
        : null,
  );

  final String opId;

  /// Written to the hub's queue on disk (it reaches the server later).
  final bool accepted;
  final bool duplicate;
  final String? reason;

  /// Uzbek, for the screen.
  final String? message;

  /// The order as the hub shows it now (the server's JSON shape).
  final Map<String, Object?>? order;

  Map<String, Object?> toJson() => <String, Object?>{
    'op_id': opId,
    'status': accepted ? 'accepted' : 'refused',
    'duplicate': duplicate,
    'reason': reason,
    'message': message,
    'order': order,
  };
}

/// Something the hub tells the devices (README §6).
class LanEvent {
  const LanEvent(this.type, [this.payload = const <String, Object?>{}]);

  final String type;
  final Map<String, Object?> payload;

  @override
  String toString() => 'LanEvent($type)';
}

/// The bridge state of a device (README §1).
enum LanMode { cloud, lan, alone }

/// When a device switches (README §1): back to `cloud` from `lan` only after the hub's queue reached the server.
abstract final class LanModeRule {
  static LanMode next({
    required LanMode current,
    required bool cloudReachable,
    required bool hubReachable,
    required bool hubDrained,
  }) {
    if (current == LanMode.lan && hubReachable && !hubDrained) {
      return LanMode.lan;
    }
    if (cloudReachable) return LanMode.cloud;
    if (hubReachable) return LanMode.lan;
    return LanMode.alone;
  }

  /// The hub's `lan.state` says its queue reached the server.
  static bool drained(Map<String, Object?> lanState) =>
      lanState['cloud'] == true && (lanState['pending'] as num?)?.toInt() == 0;
}

/// What the hub needs from the till to serve the devices: apply an operation by its own rules, show the floor and an
/// order, and tell what changed (README §6). [PosOfflineLanApplier] is the till's.
abstract interface class LanOpApplier {
  Future<LanOpResult> apply(LanRemoteOp op, LanGrantView staff);

  /// `{floor, open_orders}` as the till shows them.
  Map<String, Object?> state();

  Map<String, Object?>? order(String ref);

  /// `order.changed`, `kitchen.ticket`, `kitchen.item_status` for every change (the till's own included).
  Stream<LanEvent> get events;

  /// The till reaches the server.
  bool get cloud;

  /// Operations in the till's queue not on the server yet.
  int get pending;

  Stream<void> get statusChanges;
}

/// Who sent an operation, as the hub took it from the grant.
abstract interface class LanGrantView {
  int get staffId;
  String? get name;
  String? get role;
  String get device;
  Set<String> get permissions;
  bool can(String permission);
  bool get isWaiter;
}
