import 'package:flutter/material.dart';
import 'package:rizqim_core/rizqim_core.dart';

import '../theme/rz_colors.dart';
import '../theme/rz_theme.dart';

/// Kim o'qiydi: mijoz ("Kutilmoqda") yoki xodim ("Yangi").
enum StatusAudience { customer, staff }

/// Buyurtma holati belgisi (docs/DESIGN.md, "Holat ranglari"). Rang guruhdan, ikonka va yorliq holatdan: holat
/// faqat rang bilan ajratilmaydi. Noma'lum holat neytral rangda va o'z nomi bilan chiqadi.
class StatusBadge extends StatelessWidget {
  const StatusBadge({
    super.key,
    required this.status,
    this.audience = StatusAudience.customer,
  });

  /// API'dagi holat nomi (`new`, `preparing`, ...).
  final String status;
  final StatusAudience audience;

  /// Holat ikonkasi. `delivery` va `on_the_way` bir xil.
  static IconData iconOf(OrderStatus? status) => switch (status) {
    OrderStatus.newOrder => Icons.schedule,
    OrderStatus.accepted => Icons.check_circle_outline,
    OrderStatus.preparing => Icons.soup_kitchen_outlined,
    OrderStatus.ready => Icons.inventory_2_outlined,
    OrderStatus.courierAssigned => Icons.person_outline,
    OrderStatus.onTheWay || OrderStatus.delivery => Icons.pedal_bike,
    OrderStatus.delivered => Icons.check_circle,
    OrderStatus.canceled => Icons.cancel_outlined,
    OrderStatus.rejected => Icons.block,
    OrderStatus.refunded => Icons.undo,
    null => Icons.help_outline,
  };

  /// Guruhning (soft fon, matn) juftligi.
  static (Color background, Color foreground) colorsOf(
    RzColors c,
    StatusTone tone,
  ) => switch (tone) {
    StatusTone.warning => (c.warningSoft, c.warningSoftFg),
    StatusTone.info => (c.infoSoft, c.infoSoftFg),
    StatusTone.success => (c.successSoft, c.successSoftFg),
    StatusTone.danger => (c.dangerSoft, c.dangerSoftFg),
    StatusTone.neutral => (c.surface2, c.fgMuted),
  };

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final OrderStatus? known = OrderStatus.fromWire(status);
    final StatusTone tone = known?.tone ?? StatusTone.neutral;
    final (Color bg, Color fg) = colorsOf(c, tone);
    final String label = known == null
        ? status
        : (audience == StatusAudience.staff ? known.staffLabel : known.label);

    return Semantics(
      label: label,
      excludeSemantics: true,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(RzRadius.control),
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(iconOf(known), size: 14, color: fg),
              const SizedBox(width: 6),
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
