import 'dart:async';

import 'package:pos_data/pos_data.dart';
import 'package:pos_lan/pos_lan.dart';

import 'cashier_lan_hub.dart';

/// P6 B bosqichi: markaz kassa o'z hodisalarini LAN orqali ulangan qurilmalarga yuboradi (docs/POS_SPEC.md 5.5,
/// pos/packages/pos_lan/README.md 6):
///
/// * `cart.display` — kassadagi joriy chek ([DisplayFeed.publish] bilan bir vaqtda, serverni kutmasdan); yangi ulangan
///   mijoz ekraniga oxirgi chek darhol yuboriladi (ekran qayta ulanganda bo'sh turmasin).
/// * `queue.ready` / `queue.called` / `queue.handed` — kassadagi "Tayyor", "Qayta chaqirish", "Berildi" ([queue]); va
///   internetsiz paytda oshxona hamma taomni tayyor qilganda ([CashierLanHub.events] dagi `order.changed`) navbat
///   raqami bor buyurtma uchun `queue.ready` (server internetda o'zi e'lon qiladi; mijoz ekrani bir raqamni bir marta
///   e'lon qiladi).
/// * `kitchen.*` va `order.changed` — kassaning internetsiz amallari ham navbat orqali o'tadi, ularni
///   [PosOfflineLanApplier] o'zi tarqatadi.
///
/// Hech biri kassa ishini to'smaydi: markaz ishlamasa yoki qurilma uzilgan bo'lsa jim o'tadi.
class LanPublisher {
  LanPublisher({required this.hub, required this.feed, this.offline});

  final CashierLanHub hub;
  final DisplayFeed feed;

  /// Internetsiz rejimni bilish uchun (`queue.ready` ni markaz o'zi chiqaradi); `null` — hech qachon.
  final PosOffline? offline;

  PosDisplayCart? _last;
  int _seq = 0;
  final Set<String> _displays = <String>{};
  final Set<String> _ready = <String>{};
  StreamSubscription<LanEvent>? _sub;
  bool _started = false;

  /// Oxirgi yuborilgan chek (testlar).
  PosDisplayCart? get lastCart => _last;

  void start() {
    if (_started) return;
    _started = true;
    feed.addLocalListener(_onCart);
    hub.addListener(_onPeers);
    _sub = hub.events.listen(_onEvent);
  }

  void _onCart(PosDisplayCart cart) {
    final PosDisplayCart numbered = cart.copyWith(
      seq: ++_seq,
      session: feed.session,
    );
    _last = numbered;
    hub.publish(LanMsg.cartDisplay, numbered.toJson());
  }

  /// A display that joined (again) gets the cart on the till now. A connection is its device and when it joined (a
  /// display that reconnects replaces its old connection without a gap in between).
  void _onPeers() {
    final Map<String, String> now = <String, String>{
      for (final LanPeerInfo p in hub.peers)
        if (p.app == LanApp.display || p.type == 'display')
          '${p.device}@${p.since.microsecondsSinceEpoch}': p.device,
    };
    final PosDisplayCart? last = _last;
    if (last != null) {
      for (final String key in now.keys.toSet().difference(_displays)) {
        hub.publish(LanMsg.cartDisplay, last.toJson(), to: now[key]);
      }
    }
    _displays
      ..clear()
      ..addAll(now.keys);
  }

  /// Kassadagi navbat amali ([CounterReadyBoard]): [type] — [LanMsg.queueReady], [LanMsg.queueCalled] yoki
  /// [LanMsg.queueHanded].
  void queue(String type, PosQueueEntry entry, {String? shortCode}) {
    if (type == LanMsg.queueHanded) _ready.remove(entry.orderUuid);
    if (type == LanMsg.queueReady) _ready.add(entry.orderUuid);
    hub.publish(type, <String, Object?>{
      'order': entry.orderUuid,
      'number': entry.number,
      'short_code': shortCode,
      'call_count': entry.callCount,
      'called': type == LanMsg.queueCalled,
    });
  }

  void _onEvent(LanEvent e) {
    if (e.type != LanMsg.orderChanged) return;
    final Object? raw = e.payload['order'];
    if (raw is! Map) return;
    final Map<String, Object?> order = raw.cast<String, Object?>();
    final String? ref = (e.payload['ref'] ?? order['uuid']) as String?;
    final Object? queue = order['queue'];
    if (ref == null || queue is! Map || queue['number'] is! num) return;
    if (!readyForPickup(order)) {
      _ready.remove(ref);
      return;
    }
    // Online the server announces it (the display hears it there); offline only the hub knows
    if (offline?.connectivity.state.online ?? true) return;
    if (!_ready.add(ref)) return;
    hub.publish(LanMsg.queueReady, <String, Object?>{
      'order': ref,
      'number': (queue['number']! as num).toInt(),
      'short_code': order['short_code'],
      'call_count': 0,
      'called': false,
    });
  }

  /// Hamma (bekor qilinmagan) taom tayyor yoki berilgan va buyurtma ochiq.
  static bool readyForPickup(Map<String, Object?> order) {
    if (order['status'] == 'closed' || order['status'] == 'canceled') {
      return false;
    }
    final List<Map<Object?, Object?>> items = <Map<Object?, Object?>>[
      for (final Object? i
          in (order['items'] as List<Object?>?) ?? const <Object?>[])
        if (i is Map && i['kitchen_status'] != 'void') i,
    ];
    return items.isNotEmpty &&
        items.every(
          (Map<Object?, Object?> i) =>
              i['kitchen_status'] == 'ready' || i['kitchen_status'] == 'served',
        );
  }

  void dispose() {
    if (!_started) return;
    feed.removeLocalListener(_onCart);
    hub.removeListener(_onPeers);
    unawaited(_sub?.cancel());
  }
}
