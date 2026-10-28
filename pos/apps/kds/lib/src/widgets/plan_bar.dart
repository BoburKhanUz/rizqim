import 'package:flutter/material.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../billing/kds_plan.dart';

/// P4-21: doska tepasidagi lenta, oddiy so'zlar bilan: to'lanmagan hisob-faktura ("To'lov muddati o'tdi — X kundan keyin
/// sozlamalar yopiladi" / "Sozlamalar yopiq — to'lov qiling") va tarifda KDS yo'qligi ("Oshxona ekrani (KDS) Asosiy
/// tarifda bor"). Ekran bunda ham ishlashda davom etadi: chiptalar, "tayyor" va qaytarish tarifga bog'lanmagan.
class KdsPlanBar extends StatelessWidget {
  const KdsPlanBar({super.key, required this.controller, required this.now});

  final KdsPlanController controller;
  final DateTime Function() now;

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: controller,
    builder: (BuildContext context, _) {
      final KdsPlan plan = controller.plan;
      final KdsPlanNotice? notice = plan.notice;
      if (notice == null && plan.kdsIncluded) return const SizedBox.shrink();
      final RzColors c = context.rz;
      final bool locked = notice?.locked ?? false;
      final Color bg = notice == null
          ? c.infoSoft
          : locked
          ? c.dangerSoft
          : c.warningSoft;
      final Color fg = notice == null
          ? c.infoSoftFg
          : locked
          ? c.dangerSoftFg
          : c.warningSoftFg;
      final String title = notice?.title(now()) ?? plan.kdsLockLabel;
      final String text =
          notice?.text ??
          "Ekran ishlashda davom etadi. Tarifni ega hamkor panelidagi «Tarif va to'lov» sahifasida ko'radi.";
      return Semantics(
        liveRegion: true,
        container: true,
        label: title,
        child: Material(
          color: Color.alphaBlend(bg, c.surface),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Padding(
                  padding: const EdgeInsets.only(top: 2),
                  child: Icon(
                    notice == null
                        ? Icons.lock_outline_rounded
                        : locked
                        ? Icons.lock_outline_rounded
                        : Icons.warning_amber_rounded,
                    color: fg,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        title,
                        style: RzType.headline.copyWith(
                          color: fg,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      Text(text, style: RzType.footnote.copyWith(color: fg)),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    },
  );
}
