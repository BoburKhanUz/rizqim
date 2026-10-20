import 'dart:async';

import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Oshxona ekrani. Hozircha bo'sh: buyurtmalar ro'yxati, xodim PIN kirishi va real-time keyingi qadamlarda
/// (docs/ROADMAP.md, 2-bosqich).
class KitchenScreen extends StatelessWidget {
  const KitchenScreen({super.key, required this.auth, required this.terminal});

  final AuthSession auth;
  final PairedTerminal terminal;

  Future<void> _unpair(BuildContext context) async {
    final bool? confirmed = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: const Text('Terminalni uzish'),
        content: const Text(
          "Bu qurilma do'kondan uziladi. Qayta ulash uchun yangi kod kerak bo'ladi.",
        ),
        actions: <Widget>[
          RzButton(
            label: 'Bekor qilish',
            variant: RzButtonVariant.ghost,
            onPressed: () => Navigator.pop(context, false),
          ),
          RzButton(
            label: 'Uzish',
            variant: RzButtonVariant.danger,
            onPressed: () => Navigator.pop(context, true),
          ),
        ],
      ),
    );
    if (confirmed == true) await auth.forgetTerminal();
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final String place = <String>[
      ?terminal.store?.name,
      ?terminal.branch?.name,
    ].join(' / ');
    return Scaffold(
      appBar: AppBar(
        toolbarHeight: place.isEmpty ? null : 64,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            const Text('Oshxona ekrani'),
            if (place.isNotEmpty)
              Text(
                place,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
          ],
        ),
        actions: <Widget>[
          IconButton(
            tooltip: 'Terminalni uzish',
            icon: const Icon(Icons.link_off),
            onPressed: () => unawaited(_unpair(context)),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: Column(
        children: <Widget>[
          // Maxfiy ombor yozmadi (masalan, Linux'da kalitlar xizmati yo'q): ulanish faqat shu ishga tushirishda ishlaydi
          if (auth.storageError != null)
            const Padding(
              padding: EdgeInsets.all(16),
              child: InlineError(
                message: "Terminal tokeni qurilmada saqlanmadi. Ilova qayta ishga tushirilsa, qayta ulash kerak bo'ladi.",
              ),
            ),
          const Expanded(
            child: EmptyState(
              icon: Icons.soup_kitchen_outlined,
              title: "Hozircha buyurtma yo'q",
              message: 'Yangi buyurtmalar shu yerda chiqadi.',
            ),
          ),
        ],
      ),
    );
  }
}
