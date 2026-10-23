import 'package:flutter/widgets.dart';
import 'package:pos_data/pos_data.dart';

import '../services.dart';

/// Ish ekrani (PIN bilan kirilgan) ichidagi umumiy bog'liqliklar: sahifalar ularni shu yerdan oladi
/// (`WaiterScope.of(context).data.orders`, `.realtime`). Ustidan ochilgan sahifaga [WaiterScope.push] bilan o'tiladi
/// (scope qayta beriladi).
class WaiterScope extends InheritedWidget {
  const WaiterScope({
    super.key,
    required this.services,
    required this.realtime,
    required super.child,
  });

  final WaiterServices services;

  /// Filial kanali (`null` — real-time sozlanmagan: faqat polling).
  final PosRealtime? realtime;

  PosData get data => services.data;
  PosSession get session => services.data.session;

  static WaiterScope of(BuildContext context) {
    final WaiterScope? scope = context
        .dependOnInheritedWidgetOfExactType<WaiterScope>();
    assert(
      scope != null,
      'WaiterScope topilmadi: sahifa HomeShell ichida ochilishi kerak',
    );
    return scope!;
  }

  /// Sahifani joriy scope bilan ochadi.
  static Future<T?> push<T>(BuildContext context, Widget page) {
    final WaiterScope scope = WaiterScope.of(context);
    return Navigator.of(context).push<T>(
      PageRouteBuilder<T>(
        pageBuilder:
            (BuildContext _, Animation<double> _, Animation<double> _) =>
                WaiterScope(
                  services: scope.services,
                  realtime: scope.realtime,
                  child: page,
                ),
      ),
    );
  }

  @override
  bool updateShouldNotify(WaiterScope oldWidget) =>
      !identical(services, oldWidget.services) ||
      !identical(realtime, oldWidget.realtime);
}
