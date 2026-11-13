import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/foundation.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_api/rizqim_api.dart';

/// P6 lokal ko'prik (docs/POS_SPEC.md 5.5) oshxona ekranida: internet uzilsa, doska Wi-Fi orqali filialning markaz
/// kassasidan ([LanLink]): yangi chipta `kitchen.ticket` / `order.changed` bilan keladi, "Tayyor", "Qaytarish" va
/// "Yig'ildi" markaz navbatiga boradi (oshpaz granti bilan), kassa navbati serverga yetgach odatdagi rejim.
///
/// * Kalitlar va grant — PIN bilan kirilgandagi `GET /pos/bootstrap` `lan` qismidan ([LanSetupService]); faqat xotirada.
///   Oshpaz chiqsa — ko'prik to'xtaydi.
/// * Doska ([LanKitchenBoard]): internet uzilgan paytdagi oxirgi ro'yxat + markaz hodisalari; taymerlar markaz soati
///   bo'yicha (qurilma soati farq qilsa ham to'g'ri).
class KdsLan extends ChangeNotifier {
  KdsLan({
    required KitchenSetupService setup,
    LanLink? link,
    DateTime Function()? clock,
  }) : _clock = clock ?? DateTime.now,
       link =
           link ??
           LanLink(
             app: LanApp.kds,
             probeCloud: setup.reachable,
             multicast: _HardwareLock(WifiMulticastLock()),
           ) {
    board = LanKitchenBoard(link: this.link, clock: _clock);
    _subs
      ..add(this.link.changes.listen((_) => _notify()))
      ..add(this.link.events.listen(board.onEvent));
  }

  final LanLink link;
  final DateTime Function() _clock;
  late final LanKitchenBoard board;
  final List<StreamSubscription<Object?>> _subs =
      <StreamSubscription<Object?>>[];
  bool _disposed = false;

  LanMode get mode => link.mode;

  /// Chiptalar va amallar markaz orqali.
  bool get viaHub => link.viaHub;

  void start() => link.start();

  /// Bootstrap'ning `lan` qismi (oshpaz kirdi) yoki `null` (chiqdi, LAN yo'q).
  Future<void> setSection(Map<String, Object?>? section) => link.setCredentials(
    section == null ? null : LanCredentials.fromJson(section),
  );

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    for (final StreamSubscription<Object?> s in _subs) {
      unawaited(s.cancel());
    }
    unawaited(link.dispose());
    super.dispose();
  }
}

/// `GET /pos/bootstrap` (sexlar) va undagi `lan` qismi [onLan] ga.
class LanSetupService extends KitchenSetupService {
  LanSetupService(super.dio, {super.clock});

  /// Har muvaffaqiyatli bootstrap'dan keyin (`null` — bu terminalda LAN yo'q).
  void Function(Map<String, Object?>? lan)? onLan;

  @override
  Future<KitchenSetup> setup({int? branchId}) async {
    final KitchenSetup s = await super.setup(branchId: branchId);
    onLan?.call(s.lan);
    return s;
  }
}

/// Oshxona doskasi `lan` rejimida: internet uzilgan paytdagi oxirgi ro'yxat ([seed]) va markaz hodisalari —
/// `order.changed` (buyurtmaning markazdagi to'liq ko'rinishi: chipta shundan qayta yasaladi), `kitchen.item_status`,
/// `kitchen.ticket` (buyurtma so'raladi). Hammasi tayyor (yoki o'chirilgan) chipta doskadan ketadi.
class LanKitchenBoard {
  LanKitchenBoard({required this.link, DateTime Function()? clock})
    : _clock = clock ?? DateTime.now;

  final LanLink link;
  final DateTime Function() _clock;
  final Map<String, KitchenTicket> _tickets = <String, KitchenTicket>{};
  final Set<String> _asking = <String>{};

  /// Server bergan oxirgi ro'yxat (onlayn paytda har yuklashda).
  void seed(List<KitchenTicket> tickets) {
    _tickets
      ..clear()
      ..addEntries(<MapEntry<String, KitchenTicket>>[
        for (final KitchenTicket t in tickets) MapEntry(t.uuid, t),
      ]);
  }

  KitchenTicketList list() {
    final List<KitchenTicket> out = _tickets.values.toList()
      ..sort((KitchenTicket a, KitchenTicket b) {
        final DateTime? x = a.waitingStart, y = b.waitingStart;
        if (x == null || y == null) return 0;
        return x.compareTo(y);
      });
    return KitchenTicketList(
      tickets: out,
      truncated: false,
      skipped: const <KitchenTicketSkip>[],
      serverTime: link.hubNow(),
      receivedAt: _clock(),
    );
  }

  KitchenTicket? ticket(String uuid) => _tickets[uuid];

  void onEvent(LanEvent e) {
    final Map<String, Object?> p = e.payload;
    switch (e.type) {
      case LanMsg.orderChanged:
        final Object? order = p['order'];
        if (order is Map) {
          take(
            order.cast<String, Object?>(),
            ref: p['ref'] is String ? p['ref']! as String : null,
          );
        }
      case LanMsg.kitchenTicket:
        final Object? ref = p['order'];
        // The hub sends `order.changed` right after; ask only if it does not know the order here yet
        if (ref is String && !_tickets.containsKey(ref) && _asking.add(ref)) {
          unawaited(_ask(ref));
        }
      case LanMsg.kitchenItemStatus:
        final Object? ref = p['order'];
        final KitchenTicket? t = ref is String ? _tickets[ref] : null;
        final Object? line = p['line'];
        final Object? status = p['status'];
        if (t == null || line is! num || status is! String) return;
        final KitchenTicket next = t.copyWith(
          lines: <KitchenLine>[
            for (final KitchenLine l in t.lines)
              l.id == line.toInt() ? l.copyWith(status: status) : l,
          ],
        );
        _put(next);
      default:
        break;
    }
  }

  Future<void> _ask(String ref) async {
    try {
      final Map<String, Object?>? order = await link.order(ref);
      if (order != null) take(order, ref: ref);
    } on Object {
      // The next event brings it
    } finally {
      _asking.remove(ref);
    }
  }

  /// The order as the hub shows it (the server's POS order JSON) → its kitchen ticket; returns it (`null`: nothing for
  /// the kitchen any more, taken off the board).
  KitchenTicket? take(Map<String, Object?> order, {String? ref}) {
    final String uuid = ref ?? '${order['uuid']}';
    final KitchenTicket? t = ticketFromOrder(
      order,
      uuid: uuid,
      hubNow: link.hubNow(),
      receivedAt: _clock(),
      previous: _tickets[uuid],
    );
    if (t == null) {
      _tickets.remove(uuid);
      return null;
    }
    return _put(t);
  }

  KitchenTicket? _put(KitchenTicket t) {
    final List<KitchenLine> live = <KitchenLine>[
      for (final KitchenLine l in t.lines)
        if (!l.isVoid) l,
    ];
    if (live.isEmpty || live.every((KitchenLine l) => l.isDone)) {
      _tickets.remove(t.uuid);
      return t;
    }
    _tickets[t.uuid] = t;
    return t;
  }

  /// A POS order (hub's JSON) as a kitchen ticket: the lines sent to the kitchen, the wait counted on the hub's clock
  /// from the first send (or the opening). `null` for a closed or canceled order, or one with nothing sent.
  static KitchenTicket? ticketFromOrder(
    Map<String, Object?> order, {
    required String uuid,
    required DateTime hubNow,
    required DateTime receivedAt,
    KitchenTicket? previous,
  }) {
    final String status = '${order['status'] ?? ''}';
    if (status == 'closed' || status == 'canceled' || status == 'cancelled') {
      return null;
    }
    final Map<int, KitchenLine> was = <int, KitchenLine>{
      for (final KitchenLine l in previous?.lines ?? const <KitchenLine>[])
        l.id: l,
    };
    final List<KitchenLine> lines = <KitchenLine>[];
    DateTime? firstSent;
    for (final Object? raw
        in (order['items'] as List<Object?>?) ?? const <Object?>[]) {
      if (raw is! Map) continue;
      final Map<String, Object?> i = raw.cast<String, Object?>();
      final String kitchen = '${i['kitchen_status'] ?? 'pending'}';
      final DateTime? sent = DateTime.tryParse('${i['sent_at']}');
      if (sent == null || kitchen == KitchenLineStatus.pending) continue;
      if (firstSent == null || sent.isBefore(firstSent)) firstSent = sent;
      final num qty = num.tryParse('${i['quantity']}') ?? 1;
      final KitchenLine line = KitchenLine.fromJson(<String, Object?>{
        ...i,
        'quantity': qty,
        'quantity_label': i['quantity_label'] ?? '$qty',
        'kitchen_status': kitchen,
      });
      final KitchenLine? old = was[line.id];
      // The hub's POS order has no product/category ids for the station routes: keep the server's
      lines.add(
        old == null
            ? line
            : line.copyWith(
                productId: old.productId,
                categoryId: old.categoryId,
              ),
      );
    }
    if (lines.isEmpty) return null;
    final DateTime start =
        previous?.waitingStart ??
        DateTime.tryParse('${order['opened_at']}') ??
        firstSent!;
    final bool allDone = lines.every((KitchenLine l) => l.isDone || l.isVoid);
    return KitchenTicket(
      uuid: uuid,
      shortCode: order['short_code'] as String?,
      status: allDone ? 'ready' : 'preparing',
      type: previous?.type ?? '${order['type'] ?? 'dine_in'}',
      channel: '${order['channel'] ?? previous?.channel ?? ''}',
      branchId: (order['branch_id'] as num?)?.toInt() ?? previous?.branchId,
      hallRef: order['hall_ref'] as String? ?? previous?.hallRef,
      tableRef: order['table_ref'] as String? ?? previous?.tableRef,
      guestsCount: (order['guests_count'] as num?)?.toInt(),
      notes: order['note'] as String? ?? previous?.notes,
      lines: lines,
      createdAt: previous?.createdAt ?? start,
      acceptedAt: previous?.acceptedAt ?? start,
      tableLabel: order['table_label'] as String? ?? previous?.tableLabel,
      queueNumber: previous?.queueNumber,
      pickupStatus: previous?.pickupStatus,
      assembly: previous?.assembly,
      // The wait on the hub's clock, counted on from now on this device's own
      waitingSeconds: math.max(0, hubNow.difference(start).inSeconds),
      receivedAt: receivedAt,
    );
  }
}

/// [KitchenService] with the bridge (README §6): online — the server (and the list is kept for an outage); `lan` —
/// the board from the hub ([LanKitchenBoard]), "Tayyor" / "Qaytarish" / "Yig'ildi" as hub operations; "Buyurtma
/// tayyor" (a whole order) has no LAN operation: plainly said.
class LanKitchenService implements KitchenService {
  LanKitchenService({required this.cloud, required this.lan});

  final KitchenService cloud;
  final KdsLan lan;

  LanKitchenBoard get _board => lan.board;

  @override
  Future<KitchenTicketList> tickets({
    int? branchId,
    bool includeReady = false,
    int? canceledWithin,
  }) async {
    if (!lan.viaHub) {
      try {
        final KitchenTicketList list = await cloud.tickets(
          branchId: branchId,
          includeReady: includeReady,
          canceledWithin: canceledWithin,
        );
        lan.link.reportCloud(true);
        if (lan.mode == LanMode.cloud) {
          _board.seed(<KitchenTicket>[
            for (final KitchenTicket t in list.tickets)
              if (!t.isCanceled) t,
          ]);
        }
        return list;
      } on ApiException catch (e) {
        if (!e.isNetworkError) rethrow;
        lan.link.reportCloud(false);
        if (!lan.viaHub) rethrow;
      }
    }
    return _board.list();
  }

  Future<KitchenTicket?> _op(
    String type,
    Map<String, Object?> payload,
    String ref,
  ) async {
    final LanOpResult r;
    try {
      r = await lan.link.send(type, payload);
    } on LanUnreachable catch (e) {
      throw ApiException(
        message: e.message ?? "Markaz kassa bilan aloqa yo'q.",
        cause: e,
      );
    }
    if (!r.accepted) {
      throw ApiException(
        message: r.message ?? 'Kassa amalni qabul qilmadi.',
        statusCode: 422,
        reason: r.reason,
      );
    }
    final Map<String, Object?>? order = r.order;
    return order == null ? _board.ticket(ref) : _board.take(order, ref: ref);
  }

  @override
  Future<KitchenTicket?> bumpLine(String orderUuid, int lineId) => lan.viaHub
      ? _op(LanOpType.kitchenBump, <String, Object?>{
          'order': orderUuid,
          'line': lineId,
        }, orderUuid)
      : cloud.bumpLine(orderUuid, lineId);

  @override
  Future<KitchenTicket?> recallLine(String orderUuid, int lineId) => lan.viaHub
      ? _op(LanOpType.kitchenRecall, <String, Object?>{
          'order': orderUuid,
          'line': lineId,
        }, orderUuid)
      : cloud.recallLine(orderUuid, lineId);

  @override
  Future<KitchenTicket?> assembled(
    String orderUuid, {
    String? idempotencyKey,
  }) => lan.viaHub
      ? _op(LanOpType.kitchenAssembled, <String, Object?>{
          'order': orderUuid,
        }, orderUuid)
      : cloud.assembled(orderUuid, idempotencyKey: idempotencyKey);

  static const ApiException _wholeOrder = ApiException(
    message: "Internet yo'q: buyurtmani butunlay tayyor qilish faqat internet bilan. Taomlarni birma-bir \"Tayyor\" qiling.",
    statusCode: 422,
    reason: 'not_allowed_on_lan',
  );

  @override
  Future<void> markOrderReady(String orderUuid) => lan.viaHub
      ? Future<void>.error(_wholeOrder)
      : cloud.markOrderReady(orderUuid);

  @override
  Future<void> markQueueReady(String orderUuid, {String? idempotencyKey}) =>
      lan.viaHub
      ? Future<void>.error(_wholeOrder)
      : cloud.markQueueReady(orderUuid, idempotencyKey: idempotencyKey);
}

class _HardwareLock implements LanMulticastLock {
  _HardwareLock(this.lock);

  final WifiMulticastLock lock;

  @override
  Future<void> acquire() => lock.acquire();

  @override
  Future<void> release() => lock.release();
}
