import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'order_test.dart' show openOwnOrder;
import 'support/harness.dart';
import 'support/restaurant.dart';

/// P6-43: "Promo-kod" ofitsiantning buyurtmasida: modul yoqilganda menyuda, onlayn qo'llanadi va olib tashlanadi,
/// internetsiz oddiy xabar. Soxta server promo yo'llarini shu yerda biladi (pos_data soxtasida yo'q).
Future<void> openMenu(WidgetTester tester) async {
  await tester.tap(find.byTooltip('Menyu').first);
  await tester.pumpAndSettle();
}

void main() {
  void enable(WaiterHarness h) {
    h.state.format = <String, Object?>{
      'profile': 'restoran',
      'profile_label': 'Restoran',
      'mode': 'table',
      'first_screen': 'hall_map',
      'modules': <String>[...restaurantModules, 'vouchers'],
      'locks': <String, Object?>{'service_charge_zero': false},
    };
    h.adapter.respond = (RequestOptions o) {
      if (!o.uri.path.endsWith('/promo')) return null;
      final String uuid = RegExp(r'orders/([^/]+)/promo')
          .firstMatch(o.uri.path)!
          .group(1)!;
      final Map<String, Object?> order = h.state.orders[uuid]!;
      if (o.method == 'POST') {
        final String code = (o.data as Map)['code'] as String;
        order
          ..['discount'] = '10000.00'
          ..['discount_kind'] = 'percent'
          ..['discount_value'] = '10.00'
          ..['discount_reason'] = 'promo:$code';
      } else {
        order
          ..['discount'] = '0.00'
          ..['discount_kind'] = null
          ..['discount_value'] = null
          ..['discount_reason'] = null;
      }
      order['version'] = (order['version'] as int) + 1;
      final String? auth = o.headers['Authorization'] as String?;
      return h.state.handle(
        'GET',
        '/v1/pos/orders/$uuid',
        const <String, String>{},
        null,
        auth?.replaceFirst('Bearer ', ''),
      );
    };
  }

  testWidgets('module off: no promo item in the menu', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    await openOwnOrder(tester, h);
    await openMenu(tester);
    expect(find.text('Promo-kod'), findsNothing);
  });

  testWidgets('apply a promo code, see it on the totals, remove it', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    enable(h);
    await openOwnOrder(tester, h);

    await openMenu(tester);
    await tester.tap(find.text('Promo-kod'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'bahor10');
    await tester.tap(find.text("Qo'llash"));
    await tester.pumpAndSettle();

    final Map<String, Object?> body = bodies(h, 'POST', '/promo').single;
    expect(body['code'], 'BAHOR10');
    expect(find.text('Promo-kod BAHOR10'), findsOneWidget);

    await openMenu(tester);
    expect(find.text('Promo-kodni olib tashlash'), findsOneWidget);
    await tester.tap(find.text('Promo-kodni olib tashlash'));
    await tester.pumpAndSettle();
    expect(h.adapter.to('DELETE', '/promo'), hasLength(1));
    expect(find.text('Promo-kod BAHOR10'), findsNothing);
  });

  testWidgets('offline: a plain message and no request', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    enable(h);
    await openOwnOrder(tester, h);
    h.adapter.offline = true;
    await tester.tap(find.byTooltip('Yangilash'));
    await tester.pumpAndSettle();

    await openMenu(tester);
    await tester.tap(find.text('Promo-kod'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'BAHOR10');
    await tester.tap(find.text("Qo'llash"));
    await tester.pumpAndSettle();

    expect(find.textContaining('faqat server bilan ishlaydi'), findsOneWidget);
    h.adapter.offline = false;
    expect(h.adapter.to('POST', '/promo'), isEmpty);
  });
}
