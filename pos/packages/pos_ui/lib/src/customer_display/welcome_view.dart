import 'package:flutter/material.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'display_type.dart';

/// Mijoz ekranida chek yo'q payt: do'kon nomi va "Xush kelibsiz!" (mijoz ekrani ilovasi va kassaning ikkinchi ekrani).
class DisplayWelcome extends StatelessWidget {
  const DisplayWelcome({super.key, this.name});

  final String? name;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Icon(Icons.restaurant_rounded, size: 96, color: c.brand),
            const SizedBox(height: 24),
            if (name != null)
              Text(
                name!,
                textAlign: TextAlign.center,
                style: DisplayType.greeting.copyWith(color: c.fg),
              ),
            const SizedBox(height: 12),
            Text(
              'Xush kelibsiz!',
              textAlign: TextAlign.center,
              style: DisplayType.heading.copyWith(color: c.fgMuted),
            ),
          ],
        ),
      ),
    );
  }
}
