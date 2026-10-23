import 'package:flutter/material.dart';

import '../features/billing/billing_page.dart';
import '../features/order/order_page.dart';
import 'pos_scope.dart';

/// Bo'limlar ustidan ochiladigan sahifalar (buyurtma muharriri, schyot va to'lov).
///
/// Ochilgan sahifa ham [PosScope] ichida bo'ladi (yangi route Navigator'da qobiqdan tashqarida turadi, shuning uchun
/// joriy scope qayta beriladi). Xodim chiqqanda (qulf, harakatsizlik) hamma ochilgan sahifa yopiladi.
///
/// **Kelishuv:** `OrderPage` (P3-21, `features/order/order_page.dart`) — `OrderPage({orderUuid, tableId})`;
/// `BillingPage` (P3-22, `features/billing/billing_page.dart`) — `BillingPage({required orderUuid})`.
abstract final class CashierRoutes {
  /// Mavjud buyurtma ([orderUuid]) yoki joyda yangisi ([tableId]; ikkalasi `null` — stolsiz / olib ketish).
  static Future<T?> openOrder<T>(
    BuildContext context, {
    String? orderUuid,
    int? tableId,
  }) => push<T>(context, OrderPage(orderUuid: orderUuid, tableId: tableId));

  /// Buyurtmaning schyoti va to'lovi.
  static Future<T?> openBilling<T>(
    BuildContext context, {
    required String orderUuid,
  }) => push<T>(context, BillingPage(orderUuid: orderUuid));

  /// Istalgan sahifani joriy [PosScope] bilan ochadi.
  static Future<T?> push<T>(BuildContext context, Widget page) {
    final PosScope scope = PosScope.of(context);
    return Navigator.of(context).push<T>(
      MaterialPageRoute<T>(
        builder: (BuildContext _) => PosScope(
          services: scope.services,
          realtime: scope.realtime,
          navigate: scope.navigate,
          child: page,
        ),
      ),
    );
  }
}
