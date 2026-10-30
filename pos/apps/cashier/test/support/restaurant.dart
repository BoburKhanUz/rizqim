import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/shell/routes.dart';

import 'counter.dart';
import 'p22.dart';

/// P6-21 … P6-25 restoran modullari (bootstrap `format.modules`).
const List<String> restaurantModules = <String>[
  'rooms',
  'room_fee',
  'bookings',
  'customer_accounts',
  'service_charge',
  'kds',
  'courses',
  'tips',
  'staff_pay',
  'guest_split',
  'waiter_transfer',
  'hostess',
  'table_booking',
  'skewer_buttons',
  'weight_later',
  'table_service',
  'service_charge_rules',
];

/// Restoran filiali (kassa testlari uchun): `restoran` formati, choy puli sozlamasi (bootstrap `tips`), ochiq smena va
/// soxta backend'ning restoran qismi ([FakeRestaurantBackend]: choy puli, mehmon bo'yicha bo'lish, og'irlik, mehmon
/// raqami, ish vaqti).
class Restaurant {
  Restaurant({
    List<String> modules = restaurantModules,
    bool tips = true,
    String tipMode = 'personal',
    bool clock = false,
    P22? p,
  }) : p = p ?? P22() {
    restaurant = FakeRestaurantBackend(this.p.backend);
    this.p.state.format = formatBlock(
      profile: 'restoran',
      label: 'Restoran',
      mode: 'table',
      firstScreen: 'hall_map',
      modules: modules,
    );
    if (tips) {
      this.p.state.bootstrapExtras['tips'] = <String, Object?>{
        'enabled': modules.contains('tips'),
        'mode': tipMode,
        'pool_rule': 'equal',
        'pool_period': 'day',
      };
    }
    if (clock) {
      this.p.state.bootstrapExtras['staff_pay'] = <String, Object?>{
        'clock': true,
        'session': null,
      };
    }
    this.p.state.openShift();
  }

  final P22 p;
  late final FakeRestaurantBackend restaurant;

  /// Tapchan 1 (`Hovli`, xona pulisiz) dagi buyurtma: osh 1.5 kg + 2 choy (209 000, xizmat haqi 10% bilan).
  /// [seats] — qatorlar mehmoni, [courses] — kurslari (1-kursdan keyingisi ushlab turilgan, yuborilmagan),
  /// [awaitingWeight] — birinchi qator og'irlik kutadi (xom og'irlik).
  String order({
    int? waiterId,
    int guests = 2,
    List<(int, String)> lines = const <(int, String)>[(3, '1.500'), (1, '2')],
    List<int?> seats = const <int?>[],
    List<int?> courses = const <int?>[],
    bool awaitingWeight = false,
  }) {
    final String uuid = p.state.seedOrder(
      tableId: 11,
      guests: guests,
      lines: lines,
      waiterId: waiterId,
    );
    final Map<String, Object?> order = p.state.orders[uuid]!;
    final List<Map<String, Object?>> items = (order['items']! as List<Object?>)
        .cast<Map<String, Object?>>();
    for (var i = 0; i < items.length; i++) {
      if (i < seats.length) items[i]['seat_no'] = seats[i];
      if (i < courses.length && courses[i] != null) {
        items[i]['course_no'] = courses[i];
        if (courses[i]! > 1) {
          items[i]
            ..['kitchen_status'] = 'pending'
            ..['fire_mode'] = 'hold'
            ..['sent_at'] = null;
        }
      }
    }
    if (awaitingWeight) {
      items.first
        ..['weight_status'] = 'awaiting'
        ..['weight_basis'] = 'raw';
    }
    FakePosState.restaurantSummary(order);
    return uuid;
  }
}

/// Buyurtma muharririni ochadi (zal xaritasidagi joyni bosish o'rniga).
Future<void> openOrderPage(WidgetTester tester, String orderUuid) async {
  final BuildContext context = tester.element(find.byType(NavigationRail));
  CashierRoutes.openOrder<void>(context, orderUuid: orderUuid).ignore();
  await tester.pumpAndSettle();
}
