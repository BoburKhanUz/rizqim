import 'package:flutter/material.dart';

import '../theme/rz_colors.dart';
import '../theme/rz_theme.dart';
import 'rz_button.dart';

/// Ma'lumot yuklanmadi: sahifa ichida (butun ekranli xato emas), navigatsiya ishlab turadi, "Qayta urinish".
class InlineError extends StatelessWidget {
  const InlineError({
    super.key,
    this.message = "Ma'lumotni yuklab bo'lmadi.",
    this.onRetry,
    this.retryLabel = 'Qayta urinish',
  });

  final String message;

  /// `null` bo'lsa tugma ko'rsatilmaydi.
  final VoidCallback? onRetry;
  final String retryLabel;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Semantics(
      liveRegion: true,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: c.dangerSoft,
          borderRadius: BorderRadius.circular(RzRadius.card),
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Wrap(
            spacing: 12,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: <Widget>[
              Icon(Icons.error_outline, color: c.dangerSoftFg),
              ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 480),
                child: Text(
                  message,
                  style: RzType.body.copyWith(color: c.dangerSoftFg),
                ),
              ),
              if (onRetry != null)
                RzButton(
                  label: retryLabel,
                  onPressed: onRetry,
                  variant: RzButtonVariant.secondary,
                ),
            ],
          ),
        ),
      ),
    );
  }
}
