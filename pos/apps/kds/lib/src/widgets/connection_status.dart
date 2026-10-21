import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../realtime/branch_realtime.dart';

/// Yuqori paneldagi real-time belgisi: "Jonli", "Qayta ulanmoqda" yoki (real-time sozlanmagan) "Har 15 s".
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
    if (rt == null) return _chip(context, RealtimeStatus.idle, polling: true);
    return ValueListenableBuilder<RealtimeStatus>(
      valueListenable: rt.status,
      builder: (BuildContext context, RealtimeStatus s, _) => _chip(context, s),
    );
  }

  Widget _chip(BuildContext context, RealtimeStatus s, {bool polling = false}) {
    final RzColors c = context.rz;
    final (IconData icon, Color color, String label) = polling
        ? (Icons.sync_rounded, c.fgMuted, 'Har $pollSeconds soniyada')
        : switch (s) {
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
      message: polling
          ? "Real-time sozlanmagan: chiptalar har $pollSeconds soniyada yangilanadi"
          : 'Real-time: $label',
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
