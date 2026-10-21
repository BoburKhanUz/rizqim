import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../realtime/branch_realtime.dart';

/// Yuqori paneldagi real-time belgisi: "Jonli" (ulangan va kanal obunasi tasdiqlangan), "Ulanmoqda", "Qayta ulanmoqda"
/// yoki "Har 15 soniyada" (real-time sozlanmagan yoki kanal obunasi rad etilgan: ekran polling bilan ishlaydi).
class ConnectionStatus extends StatelessWidget {
  const ConnectionStatus({
    super.key,
    required this.realtime,
    this.pollSeconds = 15,
    this.compact = false,
  });

  final BranchRealtime? realtime;
  final int pollSeconds;

  /// Tor ekranda faqat ikonka.
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final BranchRealtime? rt = realtime;
    if (rt == null) {
      return _chip(
        context,
        RealtimeStatus.idle,
        polling: true,
        tooltip:
            "Real-time sozlanmagan: chiptalar har $pollSeconds soniyada yangilanadi",
      );
    }
    return ListenableBuilder(
      listenable: rt.changes,
      builder: (BuildContext context, _) {
        if (rt.subscriptionFailed) {
          return _chip(
            context,
            rt.status.value,
            polling: true,
            tooltip:
                "Real-time kanaliga ulanib bo'lmadi (qayta urinilmoqda): chiptalar har $pollSeconds soniyada yangilanadi",
          );
        }
        return _chip(context, rt.status.value, live: rt.isLive);
      },
    );
  }

  Widget _chip(
    BuildContext context,
    RealtimeStatus s, {
    bool polling = false,
    bool live = false,
    String? tooltip,
  }) {
    final RzColors c = context.rz;
    final (IconData icon, Color color, String label) = polling
        ? (Icons.sync_rounded, c.fgMuted, 'Har $pollSeconds soniyada')
        : switch (s) {
            // Ulangan, obuna hali tasdiqlanmagan
            RealtimeStatus.connected when !live => (
              Icons.sync_rounded,
              c.fgMuted,
              'Ulanmoqda',
            ),
            RealtimeStatus.connected => (
              Icons.sensors_rounded,
              c.successSoftFg,
              'Jonli',
            ),
            RealtimeStatus.connecting => (
              Icons.sync_rounded,
              c.fgMuted,
              'Ulanmoqda',
            ),
            RealtimeStatus.reconnecting || RealtimeStatus.idle => (
              Icons.sync_problem_rounded,
              c.warningSoftFg,
              'Qayta ulanmoqda',
            ),
          };
    return Tooltip(
      message: tooltip ?? 'Real-time: $label',
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, color: color, size: 22),
          if (!compact) ...<Widget>[
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
    );
  }
}
