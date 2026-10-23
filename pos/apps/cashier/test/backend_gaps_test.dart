import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/testing.dart';

import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';

/// P3-26: kassada 2-to'lqin backend kamchiliklari tuzatilgandan keyingi ko'rinish — ofitsiant so'ragan schyot zal
/// xaritasida va navbatda, tez qo'shish tugmalari serverdan, bronning mehmoni yangi buyurtma oynasida.
void main() {
  testWidgets("a waiter's bill request shows on the floor and in the queue", (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    final String uuid = p.state.seedOrder(tableId: 1, guests: 4, waiterId: 4);
    p.state.orders[uuid]!['bill_requested_at'] = DateTime.utc(
      2026,
      10,
      22,
      9,
    ).toIso8601String();
    await pumpHome(tester, p.h);

    expect(
      find.bySemanticsLabel(RegExp("^1-xona.*schyot Schyot so'raldi")),
      findsOneWidget,
    );
    expect(find.text("Schyot so'raldi"), findsOneWidget);

    await openSection(tester, 'Navbat');
    expect(find.text("Schyot so'raldi"), findsOneWidget);
  });

  testWidgets('quick buttons come from the bootstrap', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    h.state.quickItems.add(<String, Object?>{
      'product_id': 2,
      'variant_id': 2,
      'variant_uuid': FakePosState.variantUuid(2),
      'name': 'Non',
      'variant_name': 'Dona',
      'source': 'quick_add',
      'hall_id': null,
      'quantity_per_guest': null,
      'sort_order': 0,
    });
    h.state.seedOrder(tableId: 1);
    await pumpHome(tester, h);
    await openPlace(tester, '1-xona');

    // Faqat serverdagi tugma: non; choy (nom bo'yicha taxmin) endi yo'q
    expect(find.byKey(const ValueKey<String>('quick-2')), findsOneWidget);
    expect(find.byKey(const ValueKey<String>('quick-1')), findsNothing);
    await tester.tap(find.byKey(const ValueKey<String>('quick-2')));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey<String>('draft-1')), findsOneWidget);
  });

  testWidgets("a reserved room names the booking's guest", (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    h.state.reservations[2] = <String, Object?>{
      'booking_uuid': 'b-1',
      'starts_at': DateTime.utc(2026, 10, 22, 13, 40).toIso8601String(),
      'guests': 12,
      'contact_name': 'Jamshid aka',
      'contact_phone': '+998901112233',
    };
    await pumpHome(tester, h);
    await openPlace(tester, '2-xona');
    expect(find.textContaining('Jamshid aka (+998901112233)'), findsOneWidget);
  });
}
