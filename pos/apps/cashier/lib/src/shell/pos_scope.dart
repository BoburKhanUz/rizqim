import 'package:flutter/widgets.dart';
import 'package:pos_data/pos_data.dart';

import '../printing/printing_service.dart';
import '../services.dart';
import 'destinations.dart';

/// Ish ekrani (PIN bilan kirilgan) ichidagi umumiy bog'liqliklar: feature sahifalari ularni shu yerdan oladi
/// (`PosScope.of(context).data.orders`, `.realtime`, `.printing`, `.go(CashierDestination.shift)`).
///
/// Sahifalar o'z holatini o'zi boshqaradi: masalan zal xaritasi `PosRefreshScheduler(realtime: scope.realtime, ...)`
/// bilan yangilanadi.
class PosScope extends InheritedWidget {
  const PosScope({
    super.key,
    required this.services,
    required this.realtime,
    required this.navigate,
    required super.child,
  });

  final CashierServices services;

  /// Filial kanali (`null` — real-time sozlanmagan: faqat polling).
  final PosRealtime? realtime;

  /// Navigatsiya panelidagi bo'limga o'tish.
  final void Function(CashierDestination destination) navigate;

  PosData get data => services.data;
  PosSession get session => services.data.session;
  CashierPrinting get printing => services.printing;

  /// Bo'limga o'tish (masalan, yuqori paneldagi smena belgisi → Smena).
  void go(CashierDestination destination) => navigate(destination);

  static PosScope of(BuildContext context) {
    final PosScope? scope = context
        .dependOnInheritedWidgetOfExactType<PosScope>();
    assert(
      scope != null,
      'PosScope topilmadi: sahifa HomeShell ichida ochilishi kerak',
    );
    return scope!;
  }

  static PosScope? maybeOf(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<PosScope>();

  @override
  bool updateShouldNotify(PosScope oldWidget) =>
      !identical(services, oldWidget.services) ||
      !identical(realtime, oldWidget.realtime);
}
