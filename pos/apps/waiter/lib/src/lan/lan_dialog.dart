import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'waiter_lan.dart';

/// P6 lokal ko'prik oynasi (aloqa belgisi bosilganda): holat, markaz kassa, xato va qo'lda manzil (IP yoki kassadagi
/// `rizqim-lan://` havolasi) — telefon kassani beacon orqali topmasa.
class LanDialog extends StatefulWidget {
  const LanDialog({super.key, required this.lan});

  final WaiterLan lan;

  static Future<void> show(BuildContext context, WaiterLan lan) =>
      showDialog<void>(
        context: context,
        builder: (BuildContext _) => LanDialog(lan: lan),
      );

  static String describe(LanMode mode) => switch (mode) {
    LanMode.cloud => 'Internet bor: odatdagi ish.',
    LanMode.lan => "Internet yo'q: buyurtmalar Wi-Fi orqali markaz kassaga boradi. Schyot va to'lov faqat kassada.",
    LanMode.alone =>
      "Internet ham, markaz kassa ham yo'q: faqat ko'rish mumkin.",
  };

  @override
  State<LanDialog> createState() => _LanDialogState();
}

class _LanDialogState extends State<LanDialog> {
  late final TextEditingController _address = TextEditingController(
    text: widget.lan.link.manualEndpoint?.toString() ?? '',
  );
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _address.dispose();
    super.dispose();
  }

  Future<void> _connect() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    final String? error = await widget.lan.setManual(_address.text);
    if (error == null) await widget.lan.link.retryNow();
    if (!mounted) return;
    setState(() {
      _busy = false;
      _error = error;
    });
  }

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: widget.lan,
    builder: (BuildContext context, _) {
      final RzColors c = context.rz;
      final LanLink link = widget.lan.link;
      final String? hub = link.hubName;
      return AlertDialog(
        title: const Text('Lokal tarmoq'),
        content: SizedBox(
          width: 420,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(LanDialog.describe(link.mode)),
              const SizedBox(height: 8),
              if (!link.available)
                const Text(
                  "Bu telefon uchun lokal tarmoq sozlanmagan (filialda markaz kassa yo'q yoki internet bilan qayta kiring).",
                )
              else ...<Widget>[
                Text(
                  link.connected
                      ? 'Markaz: ${hub ?? 'kassa'} (${link.endpoint})'
                      : 'Markaz: ${hub ?? 'kassa'} — ulanmagan',
                  style: RzType.body.copyWith(color: c.fg),
                ),
                if (link.connected && link.hubPending > 0)
                  Text(
                    'Kassada serverga yuborilmagan amallar: ${link.hubPending}',
                    style: RzType.footnote.copyWith(color: c.fgMuted),
                  ),
                if (link.lastError != null && !link.connected) ...<Widget>[
                  const SizedBox(height: 4),
                  Text(
                    link.lastError!,
                    style: RzType.footnote.copyWith(color: c.dangerSoftFg),
                  ),
                ],
                const SizedBox(height: 12),
                TextField(
                  controller: _address,
                  keyboardType: TextInputType.url,
                  decoration: InputDecoration(
                    labelText: 'Markaz manzili (ixtiyoriy)',
                    hintText: '192.168.1.20 yoki rizqim-lan://…',
                    errorText: _error,
                  ),
                ),
              ],
            ],
          ),
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Yopish'),
          ),
          if (link.available)
            FilledButton(
              onPressed: _busy ? null : () => unawaited(_connect()),
              child: const Text('Ulanish'),
            ),
        ],
      );
    },
  );
}
