import 'package:flutter/material.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Mehmonlar soni: [−] son [+] (katta tugmalar, bir qo'l bilan).
class GuestsStepper extends StatelessWidget {
  const GuestsStepper({
    super.key,
    required this.value,
    required this.onChanged,
    this.min = 1,
    this.max = 99,
  });

  final int value;
  final ValueChanged<int> onChanged;
  final int min;
  final int max;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    Widget button(IconData icon, String label, VoidCallback? onPressed) =>
        Semantics(
          button: true,
          label: label,
          enabled: onPressed != null,
          excludeSemantics: true,
          child: IconButton.filledTonal(
            onPressed: onPressed,
            iconSize: 30,
            style: IconButton.styleFrom(
              minimumSize: const Size.square(60),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(RzRadius.card),
              ),
            ),
            icon: Icon(icon),
          ),
        );
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: <Widget>[
        button(
          Icons.remove,
          'Mehmonni kamaytirish',
          value > min ? () => onChanged(value - 1) : null,
        ),
        SizedBox(
          width: 120,
          child: Semantics(
            liveRegion: true,
            label: '$value mehmon',
            excludeSemantics: true,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Text('$value', style: RzType.display.copyWith(color: c.fg)),
                Text(
                  'mehmon',
                  style: RzType.footnote.copyWith(color: c.fgMuted),
                ),
              ],
            ),
          ),
        ),
        button(
          Icons.add,
          "Mehmonni ko'paytirish",
          value < max ? () => onChanged(value + 1) : null,
        ),
      ],
    );
  }
}
