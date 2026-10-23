import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Yuqori paneldagi aloqa belgisi: "Aloqa yo'q" (server javob bermayapti), "Jonli" (real-time ulangan va kanal obunasi
/// tasdiqlangan), "Ulanmoqda", "Qayta ulanmoqda" yoki "Har 15 soniyada" (real-time sozlanmagan yoki obuna rad
/// etilgan: ekranlar polling bilan yangilanadi).
class ConnectionIndicator extends StatefulWidget {
  const ConnectionIndicator({
    super.key,
    required this.realtime,
    required this.connectivity,
    this.pollSeconds = 15,
    this.compact = false,
  });

  final PosRealtime? realtime;
  final PosConnectivity connectivity;
  final int pollSeconds;

  /// Tor ekranda faqat ikonka.
  final bool compact;

  @override
  State<ConnectionIndicator> createState() => _ConnectionIndicatorState();
}

class _ConnectionIndicatorState extends State<ConnectionIndicator> {
  final List<StreamSubscription<Object?>> _subs =
      <StreamSubscription<Object?>>[];

  @override
  void initState() {
    super.initState();
    _listen();
  }

  @override
  void didUpdateWidget(ConnectionIndicator oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!identical(oldWidget.realtime, widget.realtime) ||
        !identical(oldWidget.connectivity, widget.connectivity)) {
      _cancel();
      _listen();
    }
  }

  void _listen() {
    void rebuild(Object? _) {
      if (mounted) setState(() {});
    }

    _subs.add(widget.connectivity.changes.listen(rebuild));
    final PosRealtime? rt = widget.realtime;
    if (rt != null) _subs.add(rt.states.listen(rebuild));
  }

  void _cancel() {
    for (final StreamSubscription<Object?> s in _subs) {
      unawaited(s.cancel());
    }
    _subs.clear();
  }

  @override
  void dispose() {
    _cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosConnectivityState net = widget.connectivity.state;
    final PosLiveState live = widget.realtime?.state ?? PosLiveState.off;
    final String polling = 'Har ${widget.pollSeconds} soniyada';
    final (
      IconData icon,
      Color color,
      String label,
      String tooltip,
    ) = !net.online
        ? (
            Icons.cloud_off_rounded,
            c.dangerSoftFg,
            "Aloqa yo'q",
            net.lastContactAt == null
                ? "Server javob bermayapti"
                : "Server javob bermayapti. Oxirgi aloqa: ${formatTime(net.lastContactAt!.toLocal())}",
          )
        : switch (live) {
            PosLiveState.live => (
              Icons.sensors_rounded,
              c.successSoftFg,
              'Jonli',
              'Real-time ulangan',
            ),
            PosLiveState.connecting => (
              Icons.sync_rounded,
              c.fgMuted,
              'Ulanmoqda',
              'Real-time ulanmoqda',
            ),
            PosLiveState.reconnecting => (
              Icons.sync_problem_rounded,
              c.warningSoftFg,
              'Qayta ulanmoqda',
              "Real-time uzildi, qayta ulanmoqda: ekranlar har ${widget.pollSeconds} soniyada yangilanadi",
            ),
            PosLiveState.subscriptionFailed => (
              Icons.sync_rounded,
              c.fgMuted,
              polling,
              "Real-time kanaliga ulanib bo'lmadi (qayta urinilmoqda): ekranlar har ${widget.pollSeconds} soniyada yangilanadi",
            ),
            PosLiveState.off => (
              Icons.sync_rounded,
              c.fgMuted,
              polling,
              "Real-time sozlanmagan: ekranlar har ${widget.pollSeconds} soniyada yangilanadi",
            ),
          };
    return Tooltip(
      message: tooltip,
      child: Semantics(
        label: 'Aloqa: $label',
        excludeSemantics: true,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Icon(icon, color: color, size: 22),
            if (!widget.compact) ...<Widget>[
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: RzType.body.copyWith(color: color),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Sinxronlash nishoni: oflayn navbatda yuborilmagan amallar soni (P3-31 dan; hozir doim 0) — bo'lmasa ko'rinmaydi.
class SyncBadge extends StatelessWidget {
  const SyncBadge({super.key, required this.connectivity});

  final PosConnectivity connectivity;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return StreamBuilder<PosConnectivityState>(
      stream: connectivity.changes,
      initialData: connectivity.state,
      builder:
          (BuildContext context, AsyncSnapshot<PosConnectivityState> snap) {
            final int pending = snap.data?.pendingOperations ?? 0;
            if (pending == 0) return const SizedBox.shrink();
            return Tooltip(
              message: '$pending ta amal serverga hali yuborilmagan',
              child: Badge(
                label: Text('$pending'),
                backgroundColor: c.warning,
                textColor: c.onWarning,
                child: Icon(
                  Icons.cloud_upload_outlined,
                  color: c.warningSoftFg,
                ),
              ),
            );
          },
    );
  }
}
