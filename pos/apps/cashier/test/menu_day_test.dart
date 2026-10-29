import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_cashier/src/features/order/order_page.dart';
import 'package:rizqim_cashier/src/features/shift/z_close_page.dart';
import 'package:rizqim_cashier/src/shell/staff_sign_in_screen.dart';

import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';

/// P6-15: kassa menyusida bugungi menyu, "keyingi partiya" va kompleks; hisobdan chiqarish oynasi va X/Z da ko'rinishi.
const List<String> _choyxona = <String>[
  'rooms',
  'room_fee',
  'guest_items',
  'bookings',
  'customer_accounts',
  'service_charge',
  'kds',
  'quick_counter',
];

Map<String, Object?> _format(List<String> extra) => <String, Object?>{
  'profile': 'stolovaya',
  'profile_label': 'Stolovaya',
  'mode': 'hybrid',
  'first_screen': 'hall_map',
  'modules': <String>[..._choyxona, ...extra],
  'locks': <String, Object?>{'service_charge_zero': false},
};

/// Menyuga: 5 — bugungi menyuda yo'q kunlik taom, 6 — tugagan, keyingi partiya 12:30, 7 — kompleks.
void _seedDay(P22 p, {bool menuSet = true}) {
  final Map<String, Object?> off = p.state.sampleProduct(
    5,
    2,
    'Mastava',
    '20000.00',
  );
  final Map<String, Object?> sold =
      p.state.sampleProduct(6, 2, 'Lagman', '30000.00')
        ..['is_available'] = false
        ..['unavailable_reason'] = 'sold_out'
        ..['next_batch'] = <String, Object?>{
          'time': '12:30',
          'planned_at': '2026-10-22T07:30:00Z',
          'quantity': '20.000',
          'label': null,
        };
  final Map<String, Object?> set =
      p.state.sampleProduct(7, 2, 'Kompleks 1', '45000.00')
        ..['set_parts'] = <Object?>[
          <String, Object?>{
            'variant_id': 5,
            'variant_uuid': 'v5',
            'product_id': 5,
            'name': 'Mastava',
            'variant_name': null,
            'quantity': '1.000',
            'unit': 'piece',
            'label': 'Birinchi',
          },
          <String, Object?>{
            'variant_id': 2,
            'variant_uuid': 'v2',
            'product_id': 2,
            'name': 'Non',
            'variant_name': null,
            'quantity': '1.000',
            'unit': 'piece',
            'label': null,
          },
        ];
  p.state.products.addAll(<Map<String, Object?>>[off, sold, set]);
  final Map<String, Object?> stopped =
      p.state.availability['stopped']! as Map<String, Object?>;
  (stopped['products']! as List<int>).add(5);
  ((p.state.availability['sold_out']! as Map<String, Object?>)['variants']!
          as List<int>)
      .add(6);
  (p.state.availability['stop_list']! as List<Object?>).add(<String, Object?>{
    'id': null,
    'type': 'product',
    'subject_id': 5,
    'reason': "Bugungi menyuda yo'q",
    'until': null,
  });
  p.state.format = _format(<String>['daily_menu', 'combo_meal', 'batches']);
  p.state.bootstrapExtras['daily_menu'] = <String, Object?>{
    'business_date': '2026-10-22',
    'enforced': true,
    'set': menuSet,
    'product_ids': <int>[6, 7],
  };
}

void main() {
  testWidgets(
    'off-menu dishes are hidden (a search shows them greyed), a sold-out dish tells its next batch, a kompleks lists its parts in the cart',
    (WidgetTester tester) async {
      final P22 p = P22();
      _seedDay(p);
      p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h);
      await openPlace(tester, '1-xona');
      expect(find.byType(OrderPage), findsOneWidget);

      // Hidden: not on today's menu
      expect(menuTile(5), findsNothing);
      expect(
        find.byKey(const ValueKey<String>('daily-menu-missing')),
        findsNothing,
      );

      // Sold out, next batch planned
      expect(menuTile(6), findsOneWidget);
      expect(
        find.descendant(
          of: menuTile(6),
          matching: find.text('Keyingi partiya 12:30'),
        ),
        findsOneWidget,
      );
      expect(
        find.descendant(of: menuTile(6), matching: find.text('Tugadi')),
        findsOneWidget,
      );

      // Kompleks: one tile, its parts on it; one cart line with the parts listed
      expect(
        find.descendant(
          of: menuTile(7),
          matching: find.text('Birinchi: Mastava · Non'),
        ),
        findsOneWidget,
      );
      await tester.tap(menuTile(7));
      await tester.pumpAndSettle();
      expect(find.text('Yangi bosqich (2) · ${som(45000)}'), findsOneWidget);
      expect(find.text('· Birinchi: Mastava'), findsOneWidget);
      expect(find.text('· Non'), findsOneWidget);

      // A search finds the hidden dish, greyed out and not sellable
      await tester.enterText(find.byType(TextField).first, 'Mastava');
      await tester.pumpAndSettle();
      expect(menuTile(5), findsOneWidget);
      expect(
        find.descendant(
          of: menuTile(5),
          matching: find.text("Bugungi menyuda yo'q"),
        ),
        findsOneWidget,
      );
      await tester.tap(menuTile(5), warnIfMissed: false);
      await tester.pumpAndSettle();
      expect(find.text('Yangi bosqich (2) · ${som(45000)}'), findsOneWidget);
    },
  );

  testWidgets(
    'the day with no menu sells everything and the cashier is only warned',
    (WidgetTester tester) async {
      final P22 p = P22();
      _seedDay(p, menuSet: false);
      p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h);
      await openPlace(tester, '1-xona');
      expect(
        find.byKey(const ValueKey<String>('daily-menu-missing')),
        findsOneWidget,
      );
      expect(
        find.textContaining('Bugungi menyu belgilanmagan'),
        findsOneWidget,
      );
    },
  );

  testWidgets('without the modules nothing of it shows (the old choyxona)', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    _seedDay(p);
    p.state.format = _format(<String>[]);
    p.state.seedOrder(tableId: 1, guests: 2);
    await pumpHome(tester, p.h);
    await openPlace(tester, '1-xona');
    expect(menuTile(5), findsOneWidget, reason: 'daily_menu is off: no rule');
    expect(find.text('Keyingi partiya 12:30'), findsNothing);
    expect(find.text('Birinchi: Mastava · Non'), findsNothing);
    expect(
      find.byKey(const ValueKey<String>('daily-menu-missing')),
      findsNothing,
    );
  });

  testWidgets(
    'write-off: the manager picks the kazan, the quantity and a reason; it lands on the X report and the shift page',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.format = _format(<String>['batches', 'batch_writeoff']);
      p.state.openShift();
      await pumpHome(tester, p.h, name: 'Malika Rahimova');
      await openSection(tester, 'Smena');
      await tapKey(tester, 'batch-writeoff');
      expect(find.text('Hisobdan chiqarish'), findsWidgets);
      expect(find.text('Osh (Choyxona oshi) · qoldi 8 kg'), findsOneWidget);

      // "Boshqa sabab" needs a note
      await tester.tap(find.widgetWithText(ChoiceChip, 'Boshqa sabab'));
      await tester.pumpAndSettle();
      await enter(tester, 'writeoff-quantity', '2');
      await tapKey(tester, 'writeoff-submit');
      expect(find.text('Boshqa sabab uchun izoh yozing.'), findsOneWidget);
      expect(p.backend.batchWriteOffs, isEmpty);

      // More than what is left
      await tester.tap(find.widgetWithText(ChoiceChip, 'Buzildi'));
      await tester.pumpAndSettle();
      await enter(tester, 'writeoff-quantity', '20');
      await tapKey(tester, 'writeoff-submit');
      expect(find.textContaining('Qoldiqdan ko\'p'), findsOneWidget);

      await enter(tester, 'writeoff-quantity', '2');
      await tapKey(tester, 'writeoff-submit');
      expect(p.backend.batchWriteOffs, hasLength(1));
      expect(p.backend.batchWriteOffs.single['reason'], 'spoiled');
      expect(p.backend.batchWriteOffs.single['quantity'], 2);
      final List<Object?> batches =
          p.state.availability['batches']! as List<Object?>;
      expect((batches.single! as Map<String, Object?>)['quantity_left'], 6);
      expect(find.byType(AlertDialog), findsNothing);

      // On the shift page and in the X report
      await tester.pumpAndSettle();
      expect(find.text('Hisobdan chiqarilgan taomlar'), findsOneWidget);
      expect(find.text('Osh · Buzildi'), findsOneWidget);
      await tapKey(tester, 'x-report');
      expect(find.text('Hisobdan chiqarilgan taomlar'), findsWidgets);
      expect(find.text('2 porsiya'), findsWidgets);
      await tapButton(tester, 'Yopish');

      // ... and the Z page lists them before the shift is closed
      await tapKey(tester, 'z-open');
      expect(find.byType(ZClosePage), findsOneWidget);
      expect(find.text('Hisobdan chiqarilgan taomlar'), findsOneWidget);
      expect(find.text('Osh · Buzildi'), findsOneWidget);
    },
  );

  testWidgets(
    'a cashier writes off with a manager\'s PIN on the spot: the PIN pad opens on submit, cancel keeps the form',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.format = _format(<String>['batches', 'batch_writeoff']);
      p.state.openShift();
      await pumpHome(tester, p.h);
      await openSection(tester, 'Smena');
      await tapKey(tester, 'batch-writeoff');
      // The cashier fills the form; the banner says the manager approves
      expect(
        find.byKey(const ValueKey<String>('writeoff-needs-manager')),
        findsOneWidget,
      );
      expect(
        find.byKey(const ValueKey<String>('writeoff-quantity')),
        findsOneWidget,
      );
      await enter(tester, 'writeoff-quantity', '3');
      await tapKey(tester, 'writeoff-submit');

      // The server asks for the approval: the manager's PIN pad opens over the dialog
      expect(find.text("Menejer tasdig'i"), findsOneWidget);
      expect(find.text('Taomni hisobdan chiqarish'), findsOneWidget);
      await tester.tap(buttonLabeled('Bekor qilish').last);
      await tester.pumpAndSettle();
      expect(find.text("Menejer tasdig'i"), findsNothing);
      expect(p.backend.batchWriteOffs, isEmpty);
      expect(
        find.byKey(const ValueKey<String>('writeoff-quantity')),
        findsOneWidget,
        reason: 'the form is still there',
      );

      // Again, and the manager types the PIN: written off once, on the cashier's shift
      await tapKey(tester, 'writeoff-submit');
      expect(find.text("Menejer tasdig'i"), findsOneWidget);
      await typePin(tester, '0000');
      expect(find.text("PIN noto'g'ri. Qayta urinib ko'ring."), findsOneWidget);
      await typePin(tester, '5926');
      await tester.pumpAndSettle();
      expect(p.backend.batchWriteOffs, hasLength(1));
      expect(p.backend.batchWriteOffs.single['quantity'], 3);
      expect(find.byType(AlertDialog), findsNothing);
      expect(find.byType(StaffSignInScreen), findsNothing);
      expect(find.text('Hisobdan chiqarilgan taomlar'), findsOneWidget);
    },
  );

  testWidgets('the button is not there when the batch_writeoff module is off', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    p.state.format = _format(<String>['batches']);
    p.state.openShift();
    await pumpHome(tester, p.h, name: 'Malika Rahimova');
    await openSection(tester, 'Smena');
    expect(find.byKey(const ValueKey<String>('x-report')), findsOneWidget);
    expect(find.byKey(const ValueKey<String>('batch-writeoff')), findsNothing);
    expect(find.byType(ZClosePage), findsNothing);
  });
}
