import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'kds_lan.dart';

/// P6 lokal ko'prik: yuqori paneldagi kichik belgi — "Lokal tarmoq" (internet yo'q, doska markaz kassadan), "Internet
/// yo'q" (markaz ham yo'q); odatdagi rejimda ko'rinmaydi. Bosilsa — markaz va qo'lda manzil.
class KdsLanBadge extends StatelessWidget {
  const KdsLanBadge({super.key, required this.lan, this.compact = false});

  final KdsLan lan;
  final bool compact;

  static String? label(LanMode mode) => switch (mode) {
    LanMode.lan => 'Lokal tarmoq',
    LanMode.alone => "Internet yo'q",
    LanMode.cloud => null,
  };

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: lan,
    builder: (BuildContext context, _) {
      final String? text = lan.link.available ? label(lan.mode) : null;
      if (text == null) return const SizedBox.shrink();
      final RzColors c = context.rz;
      final bool viaHub = lan.mode == LanMode.lan;
      final Color color = viaHub ? c.warningSoftFg : c.dangerSoftFg;
      return Tooltip(
        message: viaHub
            ? "Internet yo'q: chiptalar Wi-Fi orqali markaz kassadan keladi."
            : "Internet ham, markaz kassa ham yo'q.",
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () => unawaited(
            showDialog<void>(
              context: context,
              builder: (BuildContext _) => KdsLanDialog(lan: lan),
            ),
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Icon(
                  viaHub ? Icons.lan_outlined : Icons.cloud_off_rounded,
                  color: color,
                  size: 22,
                ),
                if (!compact) ...<Widget>[
                  const SizedBox(width: 6),
                  Text(text, style: RzType.body.copyWith(color: color)),
                ],
              ],
            ),
          ),
        ),
      );
    },
  );
}

/// Markaz kassa, xato va qo'lda manzil (IP yoki `rizqim-lan://` havolasi), beacon topmasa.
class KdsLanDialog extends StatefulWidget {
  const KdsLanDialog({super.key, required this.lan});

  final KdsLan lan;

  @override
  State<KdsLanDialog> createState() => _KdsLanDialogState();
}

class _KdsLanDialogState extends State<KdsLanDialog> {
  late final TextEditingController _address = TextEditingController(
    text: widget.lan.link.manualEndpoint?.toString() ?? '',
  );
  String? _error;

  @override
  void dispose() {
    _address.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: widget.lan,
    builder: (BuildContext context, _) {
      final LanLink link = widget.lan.link;
      final RzColors c = context.rz;
      return AlertDialog(
        title: const Text('Lokal tarmoq'),
        content: SizedBox(
          width: 420,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                link.connected
                    ? 'Markaz: ${link.hubName ?? 'kassa'} (${link.endpoint})'
                    : 'Markaz: ${link.hubName ?? 'kassa'} — ulanmagan',
              ),
              if (link.lastError != null && !link.connected)
                Text(
                  link.lastError!,
                  style: RzType.footnote.copyWith(color: c.dangerSoftFg),
                ),
              const SizedBox(height: 12),
              TextField(
                controller: _address,
                decoration: InputDecoration(
                  labelText: 'Markaz manzili (ixtiyoriy)',
                  hintText: '192.168.1.20 yoki rizqim-lan://…',
                  errorText: _error,
                ),
              ),
            ],
          ),
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Yopish'),
          ),
          FilledButton(
            onPressed: () {
              final String? error = link.setManual(_address.text);
              setState(() => _error = error);
              if (error == null) unawaited(link.retryNow());
            },
            child: const Text('Ulanish'),
          ),
        ],
      );
    },
  );
}
