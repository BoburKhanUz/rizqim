import 'package:flutter/material.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Katta tugma (menyu taomi, kategoriya, tez amal): planshetda barmoq bilan tez bosiladi. Kamida 96×88.
///
/// [badge] — burchakdagi belgi ("Qoldi: 12", "Tugadi"); [unavailable] bo'lsa tugma o'chiriladi va "Tugadi" bilan
/// chiziladi (stop-list, qozon tugadi). Rang [accent] chap chetda chiziq sifatida: kategoriya rangi.
class PosTileButton extends StatelessWidget {
  const PosTileButton({
    super.key,
    required this.label,
    this.subtitle,
    this.icon,
    this.badge,
    this.accent,
    this.selected = false,
    this.unavailable = false,
    this.unavailableLabel = 'Tugadi',
    this.onTap,
    this.onLongPress,
    this.minWidth = 96,
    this.minHeight = 88,
  });

  final String label;

  /// Narx yoki qisqa izoh ("160 000 so'm / kg").
  final String? subtitle;
  final IconData? icon;
  final String? badge;
  final Color? accent;
  final bool selected;
  final bool unavailable;
  final String unavailableLabel;
  final VoidCallback? onTap;

  /// Uzoq bosish: modifikatorlar yoki miqdor oynasi.
  final VoidCallback? onLongPress;
  final double minWidth;
  final double minHeight;

  bool get _enabled => !unavailable && (onTap != null || onLongPress != null);

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final Color bg = selected
        ? Color.alphaBlend(c.brandSoft, c.surface)
        : c.surface;
    final Color border = selected ? c.brand : c.line;
    final Color fg = unavailable ? c.fgSubtle : c.fg;
    final String? corner = unavailable ? unavailableLabel : badge;

    return Semantics(
      button: true,
      enabled: _enabled,
      selected: selected,
      label: <String>[label, ?subtitle, ?corner].join(', '),
      excludeSemantics: true,
      child: ConstrainedBox(
        constraints: BoxConstraints(minWidth: minWidth, minHeight: minHeight),
        child: Material(
          color: bg,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(RzRadius.card),
            side: BorderSide(color: border, width: selected ? 2 : 1),
          ),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: _enabled ? onTap : null,
            onLongPress: _enabled ? onLongPress : null,
            child: Stack(
              children: <Widget>[
                if (accent != null)
                  Positioned(
                    left: 0,
                    top: 0,
                    bottom: 0,
                    width: 6,
                    child: ColoredBox(
                      color: unavailable ? c.fgSubtle : accent!,
                    ),
                  ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      if (icon != null) ...<Widget>[
                        Icon(icon, size: 26, color: fg),
                        const SizedBox(height: 6),
                      ],
                      Text(
                        label,
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                        style: RzType.headline.copyWith(
                          color: fg,
                          decoration: unavailable
                              ? TextDecoration.lineThrough
                              : null,
                        ),
                      ),
                      if (subtitle != null) ...<Widget>[
                        const SizedBox(height: 4),
                        Text(
                          subtitle!,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: RzType.footnote.copyWith(
                            color: unavailable ? c.fgSubtle : c.fgMuted,
                          ),
                        ),
                      ],
                      // burchakdagi belgi uchun joy
                      if (corner != null) const SizedBox(height: 18),
                    ],
                  ),
                ),
                if (corner != null)
                  Positioned(
                    right: 8,
                    bottom: 8,
                    child: _CornerBadge(text: corner, danger: unavailable),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _CornerBadge extends StatelessWidget {
  const _CornerBadge({required this.text, required this.danger});

  final String text;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: danger ? c.dangerSoft : c.infoSoft,
        borderRadius: BorderRadius.circular(RzRadius.control),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
        child: Text(
          text,
          style: RzType.caption.copyWith(
            color: danger ? c.dangerSoftFg : c.infoSoftFg,
          ),
        ),
      ),
    );
  }
}
