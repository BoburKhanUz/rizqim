import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_kds/src/widgets/brand_badge.dart';
import 'package:rizqim_kds/src/widgets/ingredient_stop.dart';

import 'support/fake_kds.dart';
import 'support/fake_kitchen_state.dart';
import 'support/helpers.dart';

// P6-55 / wave 5: the brand badge (short code on the brand colour) on ticket header and lines, and the kitchen's
// "Ingredient tugadi" quick stop. A store without brands or the permission looks exactly as before.
const Map<String, Object?> bek = <String, Object?>{
  'id': 1,
  'name': 'Bek Burger',
  'code': 'BB',
  'color': '#F59E0B',
};
const Map<String, Object?> osh = <String, Object?>{
  'id': 2,
  'name': 'Osh Markazi',
  'code': 'OM',
  'color': '#0F766E',
};

typedef Item = (String, String, String, List<String>);

KdsHarness branded() {
  final KdsHarness h = KdsHarness(realtime: false);
  h.state
    ..addOrder(
      age: const Duration(minutes: 5),
      items: const <Item>[
        ('Burger', '2', 'pcs', <String>[]),
        ('Osh', '1', 'pcs', <String>[]),
      ],
    )
    ..addOrder(
      age: const Duration(minutes: 3),
      items: const <Item>[('Somsa', '3', 'pcs', <String>[])],
    );
  final List<Map<String, Object?>> first =
      (h.state.orders[0]['items']! as List<Object?>)
          .cast<Map<String, Object?>>();
  first[0]['brand'] = bek;
  first[1]['brand'] = osh;
  h.state.orders[0]['brands'] = <Map<String, Object?>>[bek, osh];
  return h;
}

/// A tiny server of the ingredient routes: Tovuq (2 dishes) and Piyoz.
class IngredientServer {
  final Set<int> stopped = <int>{};
  final Map<int, String?> reasons = <int, String?>{};
  int status = 200;
  final List<String> calls = <String>[];
  Object? lastBody;

  FakeResponse? call(dynamic options) {
    final String path = options.uri.path as String;
    if (!path.contains('/v1/owner/ingredients')) return null;
    final String method = options.method as String;
    calls.add('$method ${path.replaceFirst('/api', '')}');
    if (status != 200) {
      return FakeResponse(status, <String, Object?>{
        'success': false,
        'message': 'Modul yoqilmagan.',
        'data': <String, Object?>{'reason': 'module_disabled'},
      });
    }
    final RegExpMatch? m = RegExp(r'/ingredients/(\d+)/stop$').firstMatch(path);
    if (m != null) {
      final int id = int.parse(m.group(1)!);
      if (method == 'POST') {
        lastBody = options.data;
        stopped.add(id);
        reasons[id] = (options.data as Map)['reason'] as String?;
        return const FakeResponse(201, <String, Object?>{
          'success': true,
          'data': <String, Object?>{
            'entry_id': 9,
            'closed_product_ids': <int>[],
          },
        });
      }
      stopped.remove(id);
      return const FakeResponse(200, <String, Object?>{
        'success': true,
        'data': null,
      });
    }
    Map<String, Object?> ing(int id, String name, int links) =>
        <String, Object?>{
          'id': id,
          'name': name,
          'links': <Map<String, Object?>>[
            for (var i = 0; i < links; i++)
              <String, Object?>{
                'product_id': i + 1,
                'name': 'Taom $i',
                'is_critical': true,
              },
          ],
          'stopped': stopped.contains(id)
              ? <Map<String, Object?>>[
                  <String, Object?>{
                    'branch_id': 1,
                    'entry_id': 9,
                    'reason': reasons[id],
                    'until': null,
                  },
                ]
              : <Map<String, Object?>>[],
          'stopped_here': stopped.contains(id),
        };
    return FakeResponse(200, <String, Object?>{
      'success': true,
      'data': <String, Object?>{
        'ingredients': <Map<String, Object?>>[
          ing(1, 'Tovuq', 2),
          ing(2, 'Piyoz', 1),
        ],
      },
    });
  }
}

void main() {
  testWidgets('branded ticket: header and line badges', (tester) async {
    final KdsHarness h = branded();
    await pumpBoard(tester, h);
    expect(find.byKey(const ValueKey<String>('brand-1')), findsNWidgets(2));
    expect(find.byKey(const ValueKey<String>('brand-2')), findsNWidgets(2));
    expect(find.text('BB'), findsNWidgets(2));
    // The second ticket has no brands: its card stays plain
    expect(find.byType(BrandBadge), findsNWidgets(4));
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile('goldens/brand_tablet_tickets.png'),
    );
  });

  testWidgets('no brands: no badges anywhere', (tester) async {
    final KdsHarness h = KdsHarness(realtime: false);
    h.state.addOrder();
    await pumpBoard(tester, h);
    expect(find.byType(BrandBadge), findsNothing);
    expect(find.byType(IngredientStopBar), findsNothing);
  });

  test('badge text colour stays readable', () {
    expect(BrandBadge.textOn(const Color(0xFFF59E0B)), const Color(0xFF111111));
    expect(BrandBadge.textOn(const Color(0xFF0F766E)), Colors.white);
  });

  testWidgets('quick stop: stop with a reason, shown on the header, reopen', (
    tester,
  ) async {
    final KdsHarness h = KdsHarness(realtime: false);
    h.state
      ..permissions = <String>[
        'orders.view',
        'kitchen.manage',
        'stoplist.manage',
      ]
      ..addOrder();
    final IngredientServer server = IngredientServer();
    h.backend.respond = server.call;
    await pumpBoard(tester, h);

    expect(find.text('Hamma ingredient bor'), findsOneWidget);
    await tester.tap(
      find.byKey(const ValueKey<String>('ingredient-stop-open')),
    );
    await tester.pumpAndSettle();
    expect(find.text('2 ta taom yopiladi'), findsOneWidget);

    await tester.tap(
      find.descendant(
        of: find.byKey(const ValueKey<String>('ingredient-1')),
        matching: find.text('Tugadi'),
      ),
    );
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const ValueKey<String>('ingredient-reason')),
      'Yetkazilmadi',
    );
    await tester.tap(find.text('1 soat'));
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey<String>('ingredient-confirm')));
    await tester.pumpAndSettle();

    expect(server.calls, contains('POST /v1/owner/ingredients/1/stop'));
    final Map<Object?, Object?> body =
        server.lastBody! as Map<Object?, Object?>;
    expect(body['reason'], 'Yetkazilmadi');
    expect(body['until'], isNotNull);
    expect(find.textContaining('Tugagan · Yetkazilmadi'), findsOneWidget);

    await tester.tap(find.byTooltip('Yopish'));
    await tester.pumpAndSettle();
    expect(find.text('Tugadi: Tovuq'), findsOneWidget);

    await tester.tap(
      find.byKey(const ValueKey<String>('ingredient-stop-open')),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Qayta ochish'));
    await tester.pumpAndSettle();
    expect(server.calls, contains('DELETE /v1/owner/ingredients/1/stop'));
    await tester.tap(find.byTooltip('Yopish'));
    await tester.pumpAndSettle();
    expect(find.text('Hamma ingredient bor'), findsOneWidget);
  });

  testWidgets('hidden without the permission', (tester) async {
    // No permission: the list is never even requested
    final KdsHarness a = KdsHarness(realtime: false);
    a.state.addOrder();
    final IngredientServer s1 = IngredientServer();
    a.backend.respond = s1.call;
    await pumpBoard(tester, a);
    expect(find.byType(IngredientStopBar), findsNothing);
    expect(s1.calls, isEmpty);
  });

  testWidgets('hidden when the module is off (403)', (tester) async {
    final KdsHarness b = KdsHarness(realtime: false);
    b.state
      ..permissions = <String>[
        'orders.view',
        'kitchen.manage',
        'stoplist.manage',
      ]
      ..addOrder();
    final IngredientServer s2 = IngredientServer()..status = 403;
    b.backend.respond = s2.call;
    await pumpBoard(tester, b);
    expect(s2.calls, isNotEmpty);
    expect(find.text('Ingredient tugadi'), findsNothing);
  });
}
