import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Uzbek texts of the update notice (M5 S5).
abstract final class PosUpdateTexts {
  static const String update = 'Yangilash';
  static const String later = 'Keyinroq';
  static const String availableTitle = 'Yangi versiya mavjud';
  static const String requiredTitle = 'Ilovani yangilash kerak';
  static const String requiredBody =
      'Bu versiya endi qo\'llab-quvvatlanmaydi. Kassa ishlashda davom etadi: '
      'qulay vaqtda yangilang.';
  static const String mark = 'Yangilash kerak';

  /// "1.4.0+7 versiyasi chiqdi" with the notes after it, when there are any.
  static String available(PosAppRelease r) {
    final String head = '${r.latest} versiyasi chiqdi.';
    final String? notes = r.notes;
    return notes == null ? head : '$head $notes';
  }

  static String required(PosAppRelease r) {
    final String? min = r.minimum;
    return min == null ? requiredBody : '$requiredBody Eng kam versiya: $min.';
  }
}

/// The update notice (M5 S5). `update_available`: quiet, with "Keyinroq" when [onDismiss] is given. `update_required`:
/// red, never dismissible (no close button even if [onDismiss] is given). Only a notice: nothing here stops a sale.
///
/// "Yangilash" calls [onUpdate] (the app opens `release.url` with `openLink`) and is disabled, with the reason shown
/// under it, while [gate] is not allowed (queued operations or an order mid-payment, see `canUpdateNow`).
/// A release in the `ok` state shows nothing.
class PosUpdateBanner extends StatelessWidget {
  const PosUpdateBanner({
    super.key,
    required this.release,
    required this.gate,
    required this.onUpdate,
    this.onDismiss,
  });

  final PosAppRelease release;
  final PosUpdateGate gate;
  final VoidCallback onUpdate;

  /// Only used for `update_available`.
  final VoidCallback? onDismiss;

  @override
  Widget build(BuildContext context) {
    if (release.state == PosReleaseState.ok) return const SizedBox.shrink();
    final RzColors c = context.rz;
    final bool red = release.isRequired;
    final Color bg = red ? c.danger : Color.alphaBlend(c.infoSoft, c.surface);
    final Color fg = red ? c.onDanger : c.infoSoftFg;
    final String title = red
        ? PosUpdateTexts.requiredTitle
        : PosUpdateTexts.availableTitle;
    final String text = red
        ? PosUpdateTexts.required(release)
        : PosUpdateTexts.available(release);
    final bool dismissible = !red && onDismiss != null;
    final Widget textColumn = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          title,
          style: RzType.body.copyWith(color: fg, fontWeight: FontWeight.w700),
        ),
        Text(text, style: RzType.footnote.copyWith(color: fg)),
        if (!gate.allowed && gate.reason != null)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(
              gate.reason!,
              key: const ValueKey<String>('pos-update-reason'),
              style: RzType.footnote.copyWith(
                color: fg,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
      ],
    );
    final Widget icon = Icon(
      red ? Icons.system_update_rounded : Icons.info_outline_rounded,
      color: fg,
      size: 22,
    );
    final List<Widget> buttons = <Widget>[
      RzButton(
        label: PosUpdateTexts.update,
        variant: red ? RzButtonVariant.secondary : RzButtonVariant.primary,
        onPressed: gate.allowed ? onUpdate : null,
      ),
      if (dismissible)
        RzButton(
          label: PosUpdateTexts.later,
          variant: RzButtonVariant.ghost,
          onPressed: onDismiss,
        ),
    ];
    return Semantics(
      liveRegion: true,
      container: true,
      label: title,
      child: Material(
        key: const ValueKey<String>('pos-update-banner'),
        color: bg,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
          child: LayoutBuilder(
            builder: (BuildContext context, BoxConstraints box) {
              // A phone (under 600 px): the buttons go under the text, so it keeps the whole width
              if (box.maxWidth + 32 < narrowWidth) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        icon,
                        const SizedBox(width: 12),
                        Expanded(child: textColumn),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      alignment: WrapAlignment.end,
                      spacing: 8,
                      runSpacing: 8,
                      children: buttons,
                    ),
                  ],
                );
              }
              return Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: <Widget>[
                  icon,
                  const SizedBox(width: 12),
                  Expanded(child: textColumn),
                  const SizedBox(width: 12),
                  for (int i = 0; i < buttons.length; i++) ...<Widget>[
                    if (i > 0) const SizedBox(width: 8),
                    buttons[i],
                  ],
                ],
              );
            },
          ),
        ),
      ),
    );
  }

  /// Below this width the buttons sit under the text.
  static const double narrowWidth = 600;
}

/// A small corner mark for screens without room for a banner (KDS, customer display): an icon, red for
/// `update_required`. Shows nothing for the `ok` state. The notice itself is on the cashier till.
class PosUpdateMark extends StatelessWidget {
  const PosUpdateMark({super.key, required this.release});

  final PosAppRelease release;

  @override
  Widget build(BuildContext context) {
    if (release.state == PosReleaseState.ok) return const SizedBox.shrink();
    final RzColors c = context.rz;
    final bool red = release.isRequired;
    return Semantics(
      label: PosUpdateTexts.mark,
      excludeSemantics: true,
      child: Tooltip(
        message: PosUpdateTexts.mark,
        child: DecoratedBox(
          key: const ValueKey<String>('pos-update-mark'),
          decoration: BoxDecoration(
            color: red ? c.danger : c.infoSoft,
            shape: BoxShape.circle,
          ),
          child: Padding(
            padding: const EdgeInsets.all(6),
            child: Icon(
              Icons.system_update_rounded,
              size: 16,
              color: red ? c.onDanger : c.infoSoftFg,
            ),
          ),
        ),
      ),
    );
  }
}
