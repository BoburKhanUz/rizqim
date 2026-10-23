import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Belgi rangi (holat faqat rang bilan emas, matn va ikonka bilan ham ajraladi).
enum PosChipTone { neutral, info, success, warning, danger, brand }

/// Kichik holat belgisi: "Oshxonada", "Tayyor", "Ushlab turilgan · 19:00", "O'chirildi".
class PosToneChip extends StatelessWidget {
  const PosToneChip({
    super.key,
    required this.label,
    this.tone = PosChipTone.neutral,
    this.icon,
  });

  final String label;
  final PosChipTone tone;
  final IconData? icon;

  static (Color background, Color foreground) colorsOf(
    RzColors c,
    PosChipTone tone,
  ) => switch (tone) {
    PosChipTone.neutral => (c.surface2, c.fgMuted),
    PosChipTone.info => (c.infoSoft, c.infoSoftFg),
    PosChipTone.success => (c.successSoft, c.successSoftFg),
    PosChipTone.warning => (c.warningSoft, c.warningSoftFg),
    PosChipTone.danger => (c.dangerSoft, c.dangerSoftFg),
    PosChipTone.brand => (c.brandSoft, c.brandSoftFg),
  };

  @override
  Widget build(BuildContext context) {
    final (Color bg, Color fg) = colorsOf(context.rz, tone);
    return Semantics(
      label: label,
      excludeSemantics: true,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: Color.alphaBlend(bg, context.rz.surface),
          borderRadius: BorderRadius.circular(RzRadius.control),
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              if (icon != null) ...<Widget>[
                Icon(icon, size: 14, color: fg),
                const SizedBox(width: 4),
              ],
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: RzType.caption.copyWith(color: fg),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Buyurtma qatori (bosqichlar ro'yxatida, schyotda, ofitsiant ilovasida): miqdor, nom, qo'shimchalar, izoh, o'rin,
/// summa va holat belgisi. O'chirilgan qator ustidan chizilgan va sababi bilan.
class PosOrderLineTile extends StatelessWidget {
  const PosOrderLineTile({
    super.key,
    required this.quantityLabel,
    required this.name,
    this.modifiers = const <String>[],
    this.note,
    this.seat,
    this.amount,
    this.status,
    this.voided = false,
    this.voidReason,
    this.leading,
    this.trailing,
    this.onTap,
    this.onLongPress,
  });

  /// "2", "1.5 kg (guruch)".
  final String quantityLabel;
  final String name;

  /// "Piyoz", "2 × Qazi".
  final List<String> modifiers;
  final String? note;
  final int? seat;
  final PosMoney? amount;

  /// Holat belgisi ([PosToneChip]) yoki boshqa vidjet.
  final Widget? status;
  final bool voided;
  final String? voidReason;

  /// Miqdor o'rnida boshqa vidjet (masalan, stepper).
  final Widget? leading;
  final Widget? trailing;
  final VoidCallback? onTap;
  final VoidCallback? onLongPress;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final Color fg = voided ? c.fgSubtle : c.fg;
    final TextDecoration? strike = voided ? TextDecoration.lineThrough : null;
    final List<String> details = <String>[
      ...modifiers.map((String m) => '+ $m'),
    ];
    return InkWell(
      onTap: onTap,
      onLongPress: onLongPress,
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 48),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              leading ??
                  ConstrainedBox(
                    constraints: const BoxConstraints(
                      minWidth: 44,
                      maxWidth: 104,
                    ),
                    child: Text(
                      quantityLabel,
                      style: RzType.headline.copyWith(
                        color: fg,
                        decoration: strike,
                      ),
                    ),
                  ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      seat == null ? name : "$name · $seat-o'rin",
                      style: RzType.body.copyWith(
                        color: fg,
                        fontWeight: FontWeight.w600,
                        decoration: strike,
                      ),
                    ),
                    for (final String d in details)
                      Text(
                        d,
                        style: RzType.footnote.copyWith(color: c.fgMuted),
                      ),
                    if (note != null && note!.trim().isNotEmpty)
                      Text(
                        '! ${note!.trim()}',
                        style: RzType.footnote.copyWith(
                          color: c.warningSoftFg,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    if (voided && voidReason != null && voidReason!.isNotEmpty)
                      Text(
                        'Sabab: $voidReason',
                        style: RzType.footnote.copyWith(color: c.dangerSoftFg),
                      ),
                    if (status != null) ...<Widget>[
                      const SizedBox(height: 4),
                      status!,
                    ],
                  ],
                ),
              ),
              if (amount != null) ...<Widget>[
                const SizedBox(width: 8),
                ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 130),
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.topRight,
                    child: Text(
                      amount!.format(),
                      style: RzType.body.copyWith(
                        color: fg,
                        decoration: strike,
                        fontFeatures: const <FontFeature>[
                          FontFeature.tabularFigures(),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
              ?trailing,
            ],
          ),
        ),
      ),
    );
  }
}

/// Jami panelidagi bitta qator.
@immutable
class PosTotalsLine {
  const PosTotalsLine({
    required this.label,
    required this.amount,
    this.detail,
    this.waived = false,
    this.actionLabel,
    this.onAction,
    this.negative = false,
  });

  /// "Xizmat haqi 10%", "Xona puli".
  final String label;
  final PosMoney amount;

  /// Kichik izoh ("olib tashlandi: mehmon e'tirozi").
  final String? detail;

  /// Olib tashlangan: summa ustidan chiziladi.
  final bool waived;

  /// Qator amali ("Olib tashlash", "Qaytarish").
  final String? actionLabel;
  final VoidCallback? onAction;

  /// Chegirma kabi ayiriladigan qator ("−" bilan).
  final bool negative;
}

/// Hisob jami: taomlar, xona puli, minimal summaga qo'shimcha, xizmat haqi, joy narxi, chegirma va jami. Olib
/// tashlangan qatorlar ham ko'rinadi (ustidan chizilgan, sababi bilan).
class PosTotalsPanel extends StatelessWidget {
  const PosTotalsPanel({
    super.key,
    required this.lines,
    required this.total,
    this.totalLabel = 'Jami',
  });

  final List<PosTotalsLine> lines;
  final PosMoney total;
  final String totalLabel;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        for (final PosTotalsLine l in lines)
          Semantics(
            label:
                '${l.label}: ${l.waived ? "olib tashlangan" : l.amount.format()}',
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 1),
              child: Row(
                children: <Widget>[
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          l.label,
                          style: RzType.body.copyWith(
                            color: l.waived ? c.fgSubtle : c.fgMuted,
                          ),
                        ),
                        if (l.detail != null)
                          Text(
                            l.detail!,
                            style: RzType.caption.copyWith(
                              color: l.waived ? c.warningSoftFg : c.fgSubtle,
                            ),
                          ),
                      ],
                    ),
                  ),
                  if (l.actionLabel != null)
                    TextButton(
                      onPressed: l.onAction,
                      style: TextButton.styleFrom(
                        minimumSize: const Size(48, 40),
                        visualDensity: VisualDensity.compact,
                      ),
                      child: Text(l.actionLabel!),
                    ),
                  Text(
                    l.negative ? '−${l.amount.format()}' : l.amount.format(),
                    style: RzType.body.copyWith(
                      color: l.waived ? c.fgSubtle : c.fg,
                      decoration: l.waived ? TextDecoration.lineThrough : null,
                    ),
                  ),
                ],
              ),
            ),
          ),
        Divider(color: c.line, height: 16),
        Row(
          children: <Widget>[
            Expanded(
              child: Text(
                totalLabel,
                style: RzType.title.copyWith(color: c.fg),
              ),
            ),
            Text(total.format(), style: RzType.title.copyWith(color: c.fg)),
          ],
        ),
      ],
    );
  }
}
