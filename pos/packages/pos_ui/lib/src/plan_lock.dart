import 'package:flutter/material.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Tarifda yo'q funksiya yorlig'i: qulf + "Asosiy tarifda bor" (P4-21). Rang bilan birga matn va ikonka: holat faqat
/// rang bilan berilmaydi.
class PosLockChip extends StatelessWidget {
  const PosLockChip({super.key, required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Semantics(
      label: label,
      excludeSemantics: true,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: c.infoSoft,
          borderRadius: BorderRadius.circular(999),
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(Icons.lock_outline_rounded, size: 16, color: c.infoSoftFg),
              const SizedBox(width: 4),
              Flexible(
                child: Text(
                  label,
                  style: RzType.footnote.copyWith(
                    color: c.infoSoftFg,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Tarifga yopiq amal tugmasi: o'chirilgan tugma + sababi ("Asosiy tarifda bor"). Tarif ochiq bo'lsa oddiy tugma.
/// Bu faqat **yangi yozuv** uchun (yangi bron, bo'nak, nasiya hisobi): to'lov, schyot, chek, smena va qaytarish
/// hech qachon shu bilan o'ralmaydi.
class PosPlanButton extends StatelessWidget {
  const PosPlanButton({
    super.key,
    required this.label,
    required this.onPressed,
    required this.locked,
    required this.lockLabel,
    this.icon,
    this.variant = RzButtonVariant.primary,
    this.size = RzButtonSize.md,
    this.fullWidth = false,
  });

  final String label;
  final VoidCallback? onPressed;

  /// Funksiya tarifda yo'q.
  final bool locked;

  /// Yopiq bo'lganda ko'rinadigan yorliq ("Asosiy tarifda bor").
  final String lockLabel;
  final IconData? icon;
  final RzButtonVariant variant;
  final RzButtonSize size;
  final bool fullWidth;

  @override
  Widget build(BuildContext context) {
    if (!locked) {
      return RzButton(
        label: label,
        icon: icon,
        variant: variant,
        size: size,
        fullWidth: fullWidth,
        onPressed: onPressed,
      );
    }
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: fullWidth
          ? CrossAxisAlignment.stretch
          : CrossAxisAlignment.start,
      children: <Widget>[
        RzButton(
          label: label,
          icon: icon ?? Icons.lock_outline_rounded,
          variant: RzButtonVariant.secondary,
          size: size,
          fullWidth: fullWidth,
          onPressed: null,
        ),
        const SizedBox(height: 4),
        PosLockChip(label: lockLabel),
      ],
    );
  }
}

/// To'lanmagan hisob-faktura haqidagi lenta (kassa va ofitsiant bosh sahifasi tepasida): [locked] — sozlamalar
/// yopilgan, aks holda eslatma. Matn serverniki, oddiy so'zlar bilan; kassa ishlashda davom etishi aytiladi.
class PosPlanNoticeBar extends StatelessWidget {
  const PosPlanNoticeBar({
    super.key,
    required this.title,
    required this.text,
    required this.locked,
  });

  final String title;
  final String text;
  final bool locked;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final Color bg = locked ? c.dangerSoft : c.warningSoft;
    final Color fg = locked ? c.dangerSoftFg : c.warningSoftFg;
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
                  locked
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
                    if (text.isNotEmpty)
                      Text(text, style: RzType.footnote.copyWith(color: fg)),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
