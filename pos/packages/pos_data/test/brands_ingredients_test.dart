import 'package:dio/dio.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P6-55: brendlar (katalog, taom, buyurtma qatori), ingredient stop-list (mavjudlik) va tez stop repozitoriysi.
void main() {
  group('brands and ingredient availability', () {
    test('catalog brands, product brand and the brand of an order line', () {
      final PosCatalog catalog = PosCatalog.fromJson(
        const PosJson(<String, Object?>{
          'channel': 'pos_takeaway',
          'menu_version': 'abc',
          'branch': <String, Object?>{'id': 3, 'name': 'Asosiy'},
          'categories': <Object?>[],
          'products': <Object?>[
            <String, Object?>{
              'id': 10,
              'name': 'Lavash',
              'brand_id': 7,
              'variants': <Object?>[],
            },
            <String, Object?>{
              'id': 11,
              'name': "Sho'rva",
              'variants': <Object?>[],
            },
          ],
          'availability': <String, Object?>{
            'stopped': <String, Object?>{
              'products': <Object?>[10],
              'variants': <Object?>[],
              'modifiers': <Object?>[],
            },
            'sold_out': <String, Object?>{'variants': <Object?>[]},
            'stop_list': <Object?>[
              <String, Object?>{
                'id': null,
                'type': 'product',
                'subject_id': 10,
                'reason': 'Tovuq tugadi',
                'until': null,
              },
            ],
            'stopped_ingredients': <Object?>[
              <String, Object?>{
                'id': 5,
                'ingredient_id': 2,
                'name': 'Tovuq',
                'reason': null,
                'until': '2026-11-21T12:00:00.000000Z',
              },
            ],
            'limited_products': <Object?>[
              <String, Object?>{
                'product_id': 11,
                'ingredient_ids': <Object?>[2],
              },
            ],
          },
          'brands': <Object?>[
            <String, Object?>{
              'id': 7,
              'uuid': 'u7',
              'name': 'Lavash Lab',
              'code': 'LL',
              'color': '#16A34A',
              'logo_url': null,
            },
          ],
        }),
      );

      expect(catalog.brands.single.code, 'LL');
      expect(catalog.brands.single.argb, 0xFF16A34A);
      expect(catalog.brandOf(catalog.product(10)!)!.name, 'Lavash Lab');
      expect(catalog.brandOf(catalog.product(11)!), isNull);
      expect(catalog.productsOfBrand(null).single.id, 11);

      final PosAvailability a = catalog.availability;
      expect(a.stopReasons[10], 'Tovuq tugadi');
      expect(a.stoppedIngredients.single.label, 'Tovuq tugadi');
      expect(a.stoppedIngredients.single.until, isNotNull);
      expect(a.limitedProducts, <int, List<int>>{
        11: <int>[2],
      });
      // withAvailability keeps the brands
      expect(
        catalog.withAvailability(const PosAvailability()).brands.length,
        1,
      );

      final PosOrderItem line = PosOrderItem.fromJson(
        const PosJson(<String, Object?>{
          'id': 1,
          'product_name': 'Lavash',
          'quantity': 1,
          'kitchen_status': 'pending',
          'unit_price': '40000.00',
          'total_price': '40000.00',
          'modifiers_total': '0.00',
          'brand': <String, Object?>{
            'id': 7,
            'name': 'Lavash Lab',
            'code': 'LL',
            'color': '#16A34A',
          },
        }),
      );
      expect(line.brand?.code, 'LL');
      final PosOrderItem house = PosOrderItem.fromJson(
        const PosJson(<String, Object?>{
          'id': 2,
          'product_name': "Sho'rva",
          'quantity': 1,
          'kitchen_status': 'pending',
          'unit_price': '1.00',
          'total_price': '1.00',
          'modifiers_total': '0.00',
        }),
      );
      expect(house.brand, isNull);
      expect(
        const PosBrand(id: 1, name: 'X', code: 'X', color: 'bad').argb,
        0xFF64748B,
      );
    });

    test('a catalog without brands keeps empty lists', () {
      final PosAvailability a = PosAvailability.fromJson(
        const PosJson(<String, Object?>{
          'stopped': <String, Object?>{'products': <Object?>[]},
        }),
      );
      expect(a.stoppedIngredients, isEmpty);
      expect(a.limitedProducts, isEmpty);
    });
  });

  group('PosIngredientRepository', () {
    late Harness h;

    setUp(() async {
      h = Harness();
      await h.signIn();
    });

    test('list, stop with Idempotency-Key, reopen', () async {
      h.adapter.respond = (RequestOptions o) =>
          const FakeResponse(200, <String, Object?>{
            'success': true,
            'data': <String, Object?>{
              'branch_id': 3,
              'ingredients': <Object?>[
                <String, Object?>{
                  'id': 2,
                  'uuid': 'i2',
                  'name': 'Tovuq',
                  'unit': 'kg',
                  'links': <Object?>[
                    <String, Object?>{
                      'product_id': 10,
                      'name': 'Lavash',
                      'is_critical': true,
                    },
                    <String, Object?>{
                      'product_id': 11,
                      'name': 'Salat',
                      'is_critical': false,
                    },
                  ],
                  'stopped': <Object?>[
                    <String, Object?>{
                      'branch_id': 3,
                      'entry_id': 5,
                      'reason': null,
                      'until': null,
                    },
                  ],
                  'stopped_here': true,
                },
              ],
            },
          });
      final List<PosIngredient> list = await h.pos.ingredients.list(
        branchId: 3,
      );
      expect(h.last.uri.path, '/api/v1/owner/ingredients');
      expect(h.last.uri.queryParameters, <String, String>{'branch_id': '3'});
      expect(list.single.criticalCount, 1);
      expect(list.single.stoppedHere, isTrue);
      expect(list.single.stopped.single.entryId, 5);

      h.adapter.respond = (RequestOptions o) =>
          const FakeResponse(201, <String, Object?>{
            'success': true,
            'data': <String, Object?>{
              'entry_id': 5,
              'branch_id': 3,
              'ingredient_id': 2,
              'reason': 'Kelmadi',
              'until': null,
              'closed_product_ids': <Object?>[10, 12],
            },
          });
      final PosIngredientStopResult stopped = await h.pos.ingredients.stop(
        2,
        reason: ' Kelmadi ',
      );
      expect(h.last.method, 'POST');
      expect(h.last.uri.path, '/api/v1/owner/ingredients/2/stop');
      expect(h.last.headers['Idempotency-Key'], matches(uuidV4));
      expect(Harness.bodyOf(h.last), <String, Object?>{'reason': 'Kelmadi'});
      expect(stopped.closedProductIds, <int>[10, 12]);

      h.adapter.respond = (RequestOptions o) =>
          const FakeResponse(200, <String, Object?>{
            'success': true,
            'data': <String, Object?>{'branch_id': 3, 'ingredient_id': 2},
          });
      await h.pos.ingredients.reopen(2, branchId: 3);
      expect(h.last.method, 'DELETE');
      expect(h.last.uri.path, '/api/v1/owner/ingredients/2/stop');
      expect(h.last.uri.queryParameters, <String, String>{'branch_id': '3'});
    });
  });
}
