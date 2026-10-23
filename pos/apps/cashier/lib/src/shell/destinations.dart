import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';

import '../features/accounts/accounts_page.dart';
import '../features/bookings/bookings_page.dart';
import '../features/floor/floor_page.dart';
import '../features/queue/queue_page.dart';
import '../features/settings/settings_page.dart';
import '../features/shift/shift_page.dart';

/// Navigatsiya panelidagi bo'limlar va ularning sahifalari (marshrut jadvali).
///
/// **Kelishuv (P3-21, P3-22):** har bo'lim sahifasi o'z papkasida bitta `*_page.dart` faylida, parametrsiz `const`
/// konstruktorli vidjet (`FloorPage`, `QueuePage`, ...). Feature egasi faqat o'z faylini (va papkasini) almashtiradi:
/// bu jadval va qobiq o'zgarmaydi. Bog'liqliklar — `PosScope.of(context)`.
enum CashierDestination {
  /// Zal xaritasi (P3-21: `features/floor`).
  floor('Zal', Icons.table_restaurant_outlined, Icons.table_restaurant),

  /// Barcha kanallar navbati (P3-22: `features/queue`).
  queue('Navbat', Icons.receipt_long_outlined, Icons.receipt_long),

  /// Bron taqvimi (P3-22: `features/bookings`).
  bookings(
    'Bronlar',
    Icons.event_outlined,
    Icons.event,
    anyOf: <String>{PosPermissions.bookingsManage},
  ),

  /// Nasiya hisoblari (P3-22: `features/accounts`).
  accounts(
    'Nasiya',
    Icons.account_balance_wallet_outlined,
    Icons.account_balance_wallet,
    anyOf: <String>{
      PosPermissions.customerAccountsManage,
      PosPermissions.takePayment,
    },
  ),

  /// Smena, kassa, X va Z (P3-22: `features/shift`).
  shift(
    'Smena',
    Icons.point_of_sale_outlined,
    Icons.point_of_sale,
    anyOf: <String>{
      PosPermissions.openShift,
      PosPermissions.closeShift,
      PosPermissions.takePayment,
    },
  ),

  /// Sozlamalar: printerlar, ovoz (P3-22: `features/settings`).
  settings('Sozlamalar', Icons.settings_outlined, Icons.settings);

  const CashierDestination(
    this.label,
    this.icon,
    this.selectedIcon, {
    this.anyOf = const <String>{},
  });

  final String label;
  final IconData icon;
  final IconData selectedIcon;

  /// Shu ruxsatlardan biri bo'lsa ko'rinadi (bo'sh — hammaga).
  final Set<String> anyOf;

  bool visibleFor(PosSession session) =>
      anyOf.isEmpty || anyOf.any(session.can);

  /// Bo'lim sahifasi.
  Widget page() => switch (this) {
    CashierDestination.floor => const FloorPage(),
    CashierDestination.queue => const QueuePage(),
    CashierDestination.bookings => const BookingsPage(),
    CashierDestination.accounts => const AccountsPage(),
    CashierDestination.shift => const ShiftPage(),
    CashierDestination.settings => const SettingsPage(),
  };
}
