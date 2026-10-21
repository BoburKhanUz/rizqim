import 'package:flutter/material.dart';

import '../theme/rz_colors.dart';
import '../theme/rz_theme.dart';

/// Ekran tepasidagi "Aloqa yo'q" lentasi: ma'lumot eskirgan bo'lishi mumkinligini aytadi, ekran esa oxirgi holatni
/// ko'rsatishda davom etadi. Matnlar ilovadan (paket tilni bilmaydi).
class RzOfflineBanner extends StatelessWidget {
  const RzOfflineBanner({
    super.key,
    required this.title,
    this.message,
    this.actionLabel,
    this.onAction,
  });

  final String title;
  final String? message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Semantics(
      liveRegion: true,
      container: true,
      child: Material(
        color: c.danger,
        child: SafeArea(
          bottom: false,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              children: <Widget>[
                Icon(Icons.wifi_off_rounded, color: c.onDanger, size: 28),
                const SizedBox(width: 12),
                Expanded(
                  child: Text.rich(
                    TextSpan(
                      children: <InlineSpan>[
                        TextSpan(
                          text: title,
                          style: RzType.headline.copyWith(color: c.onDanger),
                        ),
                        if (message != null)
                          TextSpan(
                            text: '  $message',
                            style: RzType.body.copyWith(color: c.onDanger),
                          ),
                      ],
                    ),
                  ),
                ),
                if (actionLabel != null && onAction != null)
                  TextButton(
                    onPressed: onAction,
                    style: TextButton.styleFrom(
                      foregroundColor: c.onDanger,
                      textStyle: RzType.headline,
                    ),
                    child: Text(actionLabel!),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
