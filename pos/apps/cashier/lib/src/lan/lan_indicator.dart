import 'package:flutter/material.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'cashier_lan_hub.dart';
import 'windows_firewall.dart';

/// P6 lokal ko'prik: yuqori paneldagi "Lokal tarmoq: 3 qurilma" belgisi (faqat markaz kassada). Bosilsa — ulangan
/// qurilmalar va qo'lda kiritiladigan manzil (IP/QR zaxirasi).
class LanIndicator extends StatelessWidget {
  const LanIndicator({super.key, required this.hub, this.compact = false});

  final CashierLanHub hub;
  final bool compact;

  static String label(int count) => 'Lokal tarmoq: $count qurilma';

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: hub,
    builder: (BuildContext context, _) {
      final RzColors c = context.rz;
      if (hub.error != null) {
        return Tooltip(
          message: hub.error!,
          child: Icon(Icons.wifi_off_rounded, color: c.warningSoftFg),
        );
      }
      if (!hub.running) return const SizedBox.shrink();
      final int count = hub.peers.length;
      return Tooltip(
        message: label(count),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () => showDialog<void>(
            context: context,
            builder: (BuildContext context) => LanPeersDialog(hub: hub),
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Icon(Icons.lan_outlined, size: 20, color: c.fgMuted),
                const SizedBox(width: 4),
                Text(
                  compact ? '$count' : label(count),
                  style: RzType.footnote.copyWith(color: c.fgMuted),
                ),
              ],
            ),
          ),
        ),
      );
    },
  );
}

/// Ulangan qurilmalar ro'yxati va markaz manzili.
class LanPeersDialog extends StatelessWidget {
  const LanPeersDialog({super.key, required this.hub});

  final CashierLanHub hub;

  static String _app(LanPeerInfo p) => switch (p.app) {
    LanApp.waiter => 'Ofitsiant',
    LanApp.kds => 'Oshxona ekrani',
    LanApp.display => 'Mijoz ekrani',
    LanApp.cashier => 'Kassa',
    null => p.type,
  };

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: hub,
    builder: (BuildContext context, _) {
      final List<LanPeerInfo> peers = hub.peers;
      return AlertDialog(
        scrollable: true,
        title: Text(LanIndicator.label(peers.length)),
        content: SizedBox(
          width: 420,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              if (peers.isEmpty) ...<Widget>[
                const Text("Hozircha hech qanday qurilma ulanmagan."),
                // P6 Windows: same Wi-Fi, manual address, the firewall rule
                const SizedBox(height: 8),
                LanReachHint(hub: hub),
              ] else
                for (final LanPeerInfo p in peers)
                  ListTile(
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.devices_other_outlined),
                    title: Text(p.name ?? _app(p)),
                    subtitle: Text(
                      p.staff?.name == null
                          ? _app(p)
                          : '${_app(p)} · ${p.staff!.name}',
                    ),
                  ),
              if (hub.link != null) ...<Widget>[
                const SizedBox(height: 12),
                const Text(
                  "Qurilma markazni topmasa, sozlamalarda shu manzilni kiriting:",
                ),
                const SizedBox(height: 4),
                SelectableText(hub.link!),
              ],
            ],
          ),
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Yopish'),
          ),
        ],
      );
    },
  );
}
