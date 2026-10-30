import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/waiter_scope.dart';
import '../order/order_page.dart';

/// Ofitsiantga real-time xabarlar (P6-21, P6-24), ish ekranining hamma joyida (buyurtma sahifasi ochiq bo'lsa ham):
///
/// * `order.course_ready` — "2-kurs tayyor — 5-stol" (faqat o'z buyurtmasi yoki ofitsiantsiz buyurtma, [PosCourseReady.isFor]),
///   tovush va tebranish bilan, "Ochish" — buyurtma ekrani;
/// * `order.transferred` — eski ofitsiantga "… boshqa ofitsiantga o'tkazildi", yangisiga "… sizga berildi".
///
/// [child] — [WaiterScope] ichida (buyurtma sahifasini scope bilan ochish uchun).
class WaiterAlerts extends StatefulWidget {
  const WaiterAlerts({super.key, required this.child, this.onAlert});

  final Widget child;

  /// Testlar uchun: tovush va tebranish o'rniga.
  final VoidCallback? onAlert;

  @override
  State<WaiterAlerts> createState() => _WaiterAlertsState();
}

class _WaiterAlertsState extends State<WaiterAlerts> {
  StreamSubscription<PosEvent>? _sub;
  PosRealtime? _realtime;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final PosRealtime? rt = WaiterScope.of(context).realtime;
    if (identical(rt, _realtime)) return;
    unawaited(_sub?.cancel());
    _realtime = rt;
    _sub = rt?.events.listen(_onEvent);
  }

  @override
  void dispose() {
    unawaited(_sub?.cancel());
    super.dispose();
  }

  void _onEvent(PosEvent e) {
    if (!mounted) return;
    final WaiterScope scope = WaiterScope.of(context);
    final int? me = scope.session.staffId;
    if (me == null) return;
    switch (e) {
      case PosCourseReady():
        if (!e.isFor(me)) return;
        final String? table = _tableName(scope, e.tableId);
        _show(
          icon: Icons.room_service_outlined,
          text: table == null
              ? '${e.courseNo}-kurs tayyor'
              : '${e.courseNo}-kurs tayyor — $table',
          orderUuid: e.order.uuid,
          title: table ?? 'Buyurtma',
          ring: true,
        );
      case PosOrderTransferred():
        final String table = e.tableRef ?? 'Stol';
        if (e.isToMe(me) && !e.isFromMe(me)) {
          _show(
            icon: Icons.badge_outlined,
            text: '$table sizga berildi',
            orderUuid: e.order.uuid,
            title: table,
            ring: true,
          );
        } else if (e.isFromMe(me) && !e.isToMe(me)) {
          final String? to = _staffName(scope, e.toWaiterId);
          _show(
            icon: Icons.swap_horiz_rounded,
            text: to == null
                ? "$table boshqa ofitsiantga o'tkazildi"
                : "$table boshqa ofitsiantga o'tkazildi: $to",
          );
        }
      default:
        return;
    }
  }

  String? _tableName(WaiterScope scope, int? tableId) {
    if (tableId == null) return null;
    for (final PosTable t
        in scope.session.bootstrap?.tables ?? const <PosTable>[]) {
      if (t.id == tableId) return t.name;
    }
    return null;
  }

  String? _staffName(WaiterScope scope, int? id) {
    if (id == null) return null;
    for (final PosStaffRef s
        in scope.session.bootstrap?.staff ?? const <PosStaffRef>[]) {
      if (s.id == id) return s.name;
    }
    return null;
  }

  void _show({
    required IconData icon,
    required String text,
    String? orderUuid,
    String? title,
    bool ring = false,
  }) {
    if (ring) _ring();
    final RzColors c = context.rz;
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: c.brand,
          duration: const Duration(seconds: 8),
          content: Semantics(
            liveRegion: true,
            child: Row(
              children: <Widget>[
                Icon(icon, color: c.onBrand),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    text,
                    style: RzType.headline.copyWith(color: c.onBrand),
                  ),
                ),
              ],
            ),
          ),
          action: orderUuid == null
              ? null
              : SnackBarAction(
                  label: 'Ochish',
                  textColor: c.onBrand,
                  onPressed: () => unawaited(
                    WaiterScope.push<void>(
                      context,
                      OrderPage(orderUuid: orderUuid, title: title ?? ''),
                    ),
                  ),
                ),
        ),
      );
  }

  /// Kassadagi "tayyor" ovozi kabi: tizim ovozi va tebranish (ikkalasi ham bo'lmasa — jim).
  void _ring() {
    final VoidCallback? custom = widget.onAlert;
    if (custom != null) {
      custom();
      return;
    }
    unawaited(SystemSound.play(SystemSoundType.alert));
    unawaited(HapticFeedback.vibrate());
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
