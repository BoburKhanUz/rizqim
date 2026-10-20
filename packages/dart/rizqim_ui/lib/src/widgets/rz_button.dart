import 'package:flutter/material.dart';

import '../theme/rz_colors.dart';
import '../theme/rz_theme.dart';

/// [RzButton] ko'rinishi (docs/DESIGN.md, "Button").
enum RzButtonVariant {
  /// Ekran yoki kartochkaning bitta asosiy amali.
  primary,

  /// Boshqa amallar.
  secondary,

  /// Past urg'u.
  ghost,

  /// O'chirish, rad etish.
  danger,

  /// Ijobiy holat o'zgarishi ("Tayyor").
  success,
}

enum RzButtonSize {
  /// 44px
  md(RzTouch.min),

  /// 52px: POS va planshetda asosiy tugmalar
  lg(RzTouch.large);

  const RzButtonSize(this.height);
  final double height;
}

/// Rizqim tugmasi: balandligi kamida 44px, `loading` paytida bosilmaydi va kenglik o'zgarmaydi.
class RzButton extends StatelessWidget {
  const RzButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.variant = RzButtonVariant.primary,
    this.size = RzButtonSize.md,
    this.icon,
    this.loading = false,
    this.fullWidth = false,
  });

  final String label;

  /// `null` bo'lsa tugma o'chirilgan.
  final VoidCallback? onPressed;
  final RzButtonVariant variant;
  final RzButtonSize size;
  final IconData? icon;

  /// Amal bajarilmoqda: aylana ko'rsatiladi, bosish e'tiborsiz.
  final bool loading;
  final bool fullWidth;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final (Color bg, Color fg, BorderSide? side) = switch (variant) {
      RzButtonVariant.primary => (c.brand, c.onBrand, null),
      RzButtonVariant.secondary => (
        Color.alphaBlend(c.surface2, c.surface),
        c.fg,
        null,
      ),
      RzButtonVariant.ghost => (Colors.transparent, c.brandSoftFg, null),
      RzButtonVariant.danger => (c.danger, c.onDanger, null),
      RzButtonVariant.success => (c.success, c.onSuccess, null),
    };
    final bool enabled = onPressed != null && !loading;

    final Widget content = Row(
      mainAxisSize: MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.center,
      children: <Widget>[
        if (loading)
          SizedBox.square(
            dimension: 18,
            child: CircularProgressIndicator(strokeWidth: 2.5, color: fg),
          )
        else if (icon != null)
          Icon(icon, size: 20),
        if (loading || icon != null) const SizedBox(width: 8),
        Flexible(
          child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis),
        ),
      ],
    );

    final ButtonStyle style = FilledButton.styleFrom(
      backgroundColor: bg,
      foregroundColor: fg,
      disabledBackgroundColor: variant == RzButtonVariant.ghost
          ? Colors.transparent
          : c.fgSubtle.withValues(alpha: 0.25),
      disabledForegroundColor: c.fgSubtle,
      minimumSize: Size(RzTouch.min, size.height),
      padding: EdgeInsets.symmetric(
        horizontal: size == RzButtonSize.lg ? 24 : 18,
      ),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(RzRadius.card),
        side: side ?? BorderSide.none,
      ),
      textStyle: size == RzButtonSize.lg ? RzType.headline : RzType.body,
      tapTargetSize: MaterialTapTargetSize.padded,
    );

    final Widget button = FilledButton(
      // loading paytida ham tugma "o'chirilgan" ko'rinmaydi, faqat bosilmaydi
      onPressed: enabled ? onPressed : (loading ? () {} : null),
      style: loading
          ? style.copyWith(
              overlayColor: const WidgetStatePropertyAll<Color>(
                Colors.transparent,
              ),
            )
          : style,
      child: content,
    );
    return Semantics(
      button: true,
      enabled: enabled,
      label: label,
      excludeSemantics: true,
      child: fullWidth
          ? SizedBox(width: double.infinity, child: button)
          : button,
    );
  }
}
