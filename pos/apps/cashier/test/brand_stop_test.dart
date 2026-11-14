import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/features/order/order_page.dart';

import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';

// P6-55 / wave 5: the brand filter on the menu, "tugadi" by an ingredient, the "limited" marker and the quick stop
// from the till. A store without the modules looks as before.
const List<String> _base = <String>[
  'rooms',
  'room_fee',
  'guest_items',
  'bookings',
  'customer_accounts',
  'service_charge',
  'kds',
  'quick_counter',
];

void _format(P22 p, List<String> extra) {
  p.state.format = <String, Object?>{
    'profile': 'dark_kitchen',
    'profile_label': 'Dark kitchen',
    'mode': 'hybrid',
    'first_screen': 'hall_map',
    'modules': <String>[..._base, ...extra],
    'locks': <String, Object?>{'service_charge_zero': false},
  };
}

void _brands(P22 p) {
  p.state.brands.addAll(<Map<String, Object?>>[
    <String, Object?>{
      'id': 1,
      'name': 'Bek Burger',
      'code': 'BB',
      'color': '#F59E0B',
    },
    <String, Object?>{
      'id': 2,
      'name': 'Osh Markazi',
      'code': 'OM',
      'color': '#0F766E',
    },
  ]);
  p.state.products[0]['brand_id'] = 1; // Choy
  p.state.products[1]['brand_id'] = 1; // Non
  p.state.products[2]['brand_id'] = 2; // Osh
  p.state.products[3]['brand_id'] = 2; // Shashlik
}

/// A tiny server of the ingredient routes on top of the fake backend: Tovuq closes Osh (id 3).
class _Ingredients {
  _Ingredients(this.p) {
    final prev = p.state.extraRoutes;
    p.state.extraRoutes = (FakeRequest r) {
      if (r.path.startsWith('/v1/owner/ingredients')) return _handle(r);
      return prev?.call(r);
    };
  }

  final P22 p;
  final List<String> calls = <String>[];
  Object? lastBody;
  bool stopped = false;

  FakeResponse _ok(Object? data, {int status = 200}) => FakeResponse(
    status,
    <String, Object?>{'success': true, 'message': 'OK', 'data': data},
  );

  FakeResponse _handle(FakeRequest r) {
    calls.add('${r.method} ${r.path}');
    final Map<String, Object?> av = p.state.availability;
    final Map<String, Object?> st = av['stopped']! as Map<String, Object?>;
    if (r.path.endsWith('/stop')) {
      if (r.method == 'POST') {
        lastBody = r.data;
        stopped = true;
        (st['products']! as List<int>).add(3);
        (av['stop_list']! as List<Object?>).add(<String, Object?>{
          'id': 1,
          'type': 'product',
          'subject_id': 3,
          'reason': 'Tovuq tugadi',
          'until': null,
        });
        av['stopped_ingredients'] = <Object?>[
          <String, Object?>{
            'id': 7,
            'ingredient_id': 1,
            'name': 'Tovuq',
            'reason': 'Tovuq tugadi',
            'until': null,
          },
        ];
        return _ok(<String, Object?>{
          'entry_id': 7,
          'branch_id': 1,
          'ingredient_id': 1,
          'closed_product_ids': <int>[3],
        }, status: 201);
      }
      stopped = false;
      (st['products']! as List<int>).remove(3);
      (av['stop_list']! as List<Object?>).clear();
      av['stopped_ingredients'] = <Object?>[];
      return _ok(null);
    }
    return _ok(<String, Object?>{
      'branch_id': 1,
      'ingredients': <Object?>[
        <String, Object?>{
          'id': 1,
          'uuid': 'i1',
          'name': 'Tovuq',
          'unit': 'kg',
          'links': <Object?>[
            <String, Object?>{
              'product_id': 3,
              'name': 'Osh',
              'is_critical': true,
            },
          ],
          'stopped': stopped
              ? <Object?>[
                  <String, Object?>{
                    'branch_id': 1,
                    'entry_id': 7,
                    'reason': 'Tovuq tugadi',
                    'until': null,
                  },
                ]
              : <Object?>[],
          'stopped_here': stopped,
        },
      ],
    });
  }
}

Future<void> _open(WidgetTester tester, P22 p) async {
  p.state.seedOrder(tableId: 1, guests: 2);
  await pumpHome(tester, p.h);
  await openPlace(tester, '1-xona');
  expect(find.byType(OrderPage), findsOneWidget);
}

void main() {
  testWidgets('brand chips filter the menu; "Hamma brend" shows all', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    _format(p, <String>['multi_brand']);
    _brands(p);
    await _open(tester, p);
    expect(find.byKey(const ValueKey<String>('menu-brands')), findsOneWidget);
    expect(menuTile(1), findsOneWidget);
    expect(menuTile(3), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey<String>('brand-chip-2')));
    await tester.pumpAndSettle();
    expect(menuTile(1), findsNothing);
    expect(menuTile(3), findsOneWidget);
    expect(menuTile(4), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey<String>('brand-chip-all')));
    await tester.pumpAndSettle();
    expect(menuTile(1), findsOneWidget);
    expect(menuTile(3), findsOneWidget);
  });

  testWidgets(
    'a dish closed by an ingredient says why; a limited one is marked',
    (WidgetTester tester) async {
      final P22 p = P22();
      _format(p, <String>['multi_brand', 'ingredient_stoplist']);
      _brands(p);
      final Map<String, Object?> av = p.state.availability;
      (((av['stopped']!) as Map<String, Object?>)['products']! as List<int>)
          .add(3);
      (av['stop_list']! as List<Object?>).add(<String, Object?>{
        'id': null,
        'type': 'product',
        'subject_id': 3,
        'reason': 'Tovuq tugadi',
        'until': null,
      });
      av['stopped_ingredients'] = <Object?>[
        <String, Object?>{
          'id': 7,
          'ingredient_id': 1,
          'name': 'Tovuq',
          'reason': 'Tovuq tugadi',
          'until': null,
        },
        <String, Object?>{
          'id': 8,
          'ingredient_id': 2,
          'name': 'Piyoz',
          'reason': null,
          'until': null,
        },
      ];
      av['limited_products'] = <Object?>[
        <String, Object?>{
          'product_id': 4,
          'ingredient_ids': <int>[2],
        },
      ];
      await _open(tester, p);
      expect(
        find.descendant(of: menuTile(3), matching: find.text('Tovuq tugadi')),
        findsOneWidget,
      );
      expect(
        find.descendant(of: menuTile(4), matching: find.text('Piyozsiz')),
        findsOneWidget,
      );
    },
  );

  testWidgets(
    'quick stop from the till: stop closes the dishes, reopen opens',
    (WidgetTester tester) async {
      final P22 p = P22();
      _format(p, <String>['multi_brand', 'ingredient_stoplist']);
      _brands(p);
      p.state.rolePermissions['cashier']!.add('stoplist.manage');
      final _Ingredients server = _Ingredients(p);
      await _open(tester, p);
      expect(
        find.descendant(of: menuTile(3), matching: find.text('Tovuq tugadi')),
        findsNothing,
      );

      await tester.tap(
        find.byKey(const ValueKey<String>('menu-ingredient-stop')),
      );
      await tester.pumpAndSettle();
      expect(find.text('1 ta taom yopiladi'), findsOneWidget);
      await tester.tap(find.text('Tugadi'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const ValueKey<String>('ingredient-reason')),
        'Yetkazilmadi',
      );
      await tester.tap(
        find.byKey(const ValueKey<String>('ingredient-confirm')),
      );
      await tester.pumpAndSettle();

      expect(server.calls, contains('POST /v1/owner/ingredients/1/stop'));
      expect(
        (server.lastBody! as Map<Object?, Object?>)['reason'],
        'Yetkazilmadi',
      );
      expect(find.textContaining('Tugagan'), findsOneWidget);

      await tester.tap(find.byTooltip('Yopish'));
      await tester.pumpAndSettle();
      expect(
        find.descendant(of: menuTile(3), matching: find.text('Tovuq tugadi')),
        findsOneWidget,
      );

      await tester.tap(
        find.byKey(const ValueKey<String>('menu-ingredient-stop')),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Qayta ochish'));
      await tester.pumpAndSettle();
      expect(server.calls, contains('DELETE /v1/owner/ingredients/1/stop'));
      await tester.tap(find.byTooltip('Yopish'));
      await tester.pumpAndSettle();
      expect(
        find.descendant(of: menuTile(3), matching: find.text('Tovuq tugadi')),
        findsNothing,
      );
    },
  );

  testWidgets('no modules or no permission: nothing of it shows', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    _format(p, <String>[]);
    _brands(p);
    p.state.rolePermissions['cashier']!.add('stoplist.manage');
    await _open(tester, p);
    // The catalogue has brands (the server sends them only with the module, but the till still shows what it got)
    expect(
      find.byKey(const ValueKey<String>('menu-ingredient-stop')),
      findsNothing,
    );
  });

  testWidgets('the module is on but the cashier has no stop-list permission', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    _format(p, <String>['ingredient_stoplist']);
    await _open(tester, p);
    expect(
      find.byKey(const ValueKey<String>('menu-ingredient-stop')),
      findsNothing,
    );
    expect(find.byKey(const ValueKey<String>('menu-brands')), findsNothing);
  });
}
