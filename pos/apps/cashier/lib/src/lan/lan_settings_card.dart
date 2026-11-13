import 'package:flutter/material.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:pos_ui/customer_display.dart' show QrView;
import 'package:rizqim_ui/rizqim_ui.dart';

import 'package:rizqim_core/rizqim_core.dart' show StatusTone;

import '../features/billing/cashier_ui.dart' show PosCard, TonePill;
import 'cashier_lan_hub.dart';
import 'lan_indicator.dart' show LanPeersDialog;
import 'windows_firewall.dart' show LanReachHint, WindowsFirewall;

/// P6 B bosqichi: kassa sozlamalaridagi "Lokal tarmoq" bo'limi (docs/POS_SPEC.md 5.5): markaz ishlayaptimi, ulangan
/// qurilmalar, qo'lda ulanish uchun IP, port va QR (`rizqim-lan://...`). Markazni ega boshqaruv panelida tanlaydi
/// (Kassa sozlamalari → Lokal tarmoq); bu yerda faqat holat.
class LanSettingsCard extends StatelessWidget {
  const LanSettingsCard({super.key, required this.hub, this.firewall});

  final CashierLanHub hub;

  /// [LanReachHint] uchun (`null`: haqiqiy Windows xavfsizlik devori; testlar o'rnini bosadi).
  final WindowsFirewall? firewall;

  static String appLabel(LanPeerInfo p) => switch (p.app) {
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
      final RzColors c = context.rz;
      final TextStyle muted = RzType.body.copyWith(color: c.fgMuted);
      final List<Widget> children;
      if (!hub.configured) {
        children = <Widget>[
          Text(
            "Lokal tarmoq bu kassada sozlanmagan: kassa filialga ulangan bo'lishi va internet bilan bir marta kirish "
            'kerak.',
            style: muted,
          ),
        ];
      } else if (!hub.isHub) {
        children = <Widget>[
          _status(c, StatusTone.neutral, "O'chiq: bu kassa markaz emas"),
          const SizedBox(height: 8),
          Text(
            'Filialning markaz kassasi: ${hub.hubName ?? "tanlanmagan"}. Markazni ega boshqaruv panelida tanlaydi '
            "(Kassa sozlamalari → Lokal tarmoq).",
            style: muted,
          ),
        ];
      } else if (hub.error != null) {
        children = <Widget>[
          _status(c, StatusTone.danger, 'Ishga tushmadi'),
          const SizedBox(height: 8),
          Text(hub.error!, style: muted),
        ];
      } else if (!hub.running) {
        children = <Widget>[_status(c, StatusTone.warning, 'Ishga tushmoqda…')];
      } else {
        final List<LanPeerInfo> peers = hub.peers;
        final String? link = hub.link;
        children = <Widget>[
          _status(c, StatusTone.success, 'Yoqilgan: bu kassa markaz'),
          const SizedBox(height: 8),
          Text(
            "Internet uzilsa, ofitsiant, oshxona ekrani va mijoz ekrani shu kassa orqali Wi-Fi'da ishlaydi. To'lov "
            'faqat kassada.',
            style: muted,
          ),
          const SizedBox(height: 12),
          _row(c, 'IP manzil', hub.host ?? '—'),
          _row(c, 'Port', '${hub.port ?? '—'}'),
          _row(
            c,
            'Ulangan qurilmalar',
            peers.isEmpty ? "Hozircha yo'q" : '${peers.length} ta',
          ),
          // P6 Windows (B3): nobody reached the hub yet: same Wi-Fi, the manual address, the firewall rule
          if (peers.isEmpty) ...<Widget>[
            const SizedBox(height: 8),
            LanReachHint(hub: hub, firewall: firewall),
          ],
          for (final LanPeerInfo p in peers)
            Padding(
              padding: const EdgeInsets.only(left: 12, top: 2),
              child: Text(
                '• ${p.name ?? appLabel(p)} — ${appLabel(p)}'
                '${p.staff?.name == null ? '' : ' · ${p.staff!.name}'}',
                key: ValueKey<String>('lan-peer-${p.device}'),
                style: RzType.body.copyWith(color: c.fg),
              ),
            ),
          if (link != null) ...<Widget>[
            const SizedBox(height: 12),
            Text(
              "Qurilma markazni o'zi topmasa: uning sozlamalarida shu manzilni kiriting yoki QR'ni skanerlang.",
              style: muted,
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 16,
              runSpacing: 8,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: <Widget>[
                QrView(
                  key: const ValueKey<String>('lan-qr'),
                  payload: link,
                  size: 160,
                ),
                SelectableText(
                  link,
                  key: const ValueKey<String>('lan-link'),
                  style: RzType.body.copyWith(color: c.fg),
                ),
              ],
            ),
          ],
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: () => showDialog<void>(
                context: context,
                builder: (BuildContext context) => LanPeersDialog(hub: hub),
              ),
              icon: const Icon(Icons.devices_other_outlined),
              label: const Text("Qurilmalarni ko'rish"),
            ),
          ),
        ];
      }
      return PosCard(
        key: const ValueKey<String>('lan-settings'),
        title: 'Lokal tarmoq',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: children,
        ),
      );
    },
  );

  Widget _status(RzColors c, StatusTone tone, String text) => Align(
    alignment: Alignment.centerLeft,
    child: TonePill(
      key: const ValueKey<String>('lan-status'),
      label: text,
      tone: tone,
      icon: Icons.lan_outlined,
    ),
  );

  Widget _row(RzColors c, String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 2),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        SizedBox(
          width: 180,
          child: Text(label, style: RzType.body.copyWith(color: c.fgMuted)),
        ),
        Expanded(
          child: SelectableText(
            value,
            style: RzType.body.copyWith(color: c.fg),
          ),
        ),
      ],
    ),
  );
}
