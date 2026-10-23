import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/fake_backend.dart';

void main() {
  late FakeAdapter adapter;
  late KitchenSetupService service;

  setUp(() {
    adapter = FakeAdapter((RequestOptions o) => refuse(404, 'unhandled'));
    service = KitchenSetupService(
      Dio(BaseOptions(baseUrl: 'https://api.test/api'))
        ..httpClientAdapter = adapter,
      clock: () => DateTime.utc(2026, 10, 22, 8),
    );
  });

  Map<String, Object?> batch({int id = 1, bool open = true, num left = 12}) =>
      <String, Object?>{
        'id': id,
        'uuid': 'b-$id',
        'branch_id': 3,
        'product_id': 9,
        'product_name': 'Osh',
        'product_variant_id': null,
        'variant_name': null,
        'unit': 'piece',
        'label': '1-qozon',
        'quantity_produced': 40,
        'quantity_left': left,
        'is_open': open,
        'started_at': '2026-10-22T06:00:00.000000Z',
        'sold_out_at': null,
        'closed_at': null,
      };

  test(
    'setup keeps only active stations shown on KDS, in sort order',
    () async {
      adapter.handler = (RequestOptions o) => ok(<String, Object?>{
        'server_time': '2026-10-22T08:00:00Z',
        'stations': <Object?>[
          <String, Object?>{
            'id': 2,
            'name': 'Osh',
            'kind': 'osh',
            'sort_order': 2,
            'is_active': true,
            'show_on_kds': true,
          },
          <String, Object?>{
            'id': 1,
            'name': 'Mangal',
            'kind': 'mangal',
            'kind_label': 'Mangal',
            'sort_order': 1,
            'is_active': true,
            'show_on_kds': true,
          },
          <String, Object?>{
            'id': 3,
            'name': 'Bar',
            'kind': 'tea',
            'sort_order': 3,
            'is_active': true,
            'show_on_kds': false,
          },
          <String, Object?>{
            'id': 4,
            'name': 'Eski',
            'kind': 'other',
            'sort_order': 4,
            'is_active': false,
            'show_on_kds': true,
          },
          'junk',
        ],
      });
      final KitchenSetup setup = await service.setup(branchId: 3);
      expect(setup.stations.map((KitchenStation s) => s.name), <String>[
        'Mangal',
        'Osh',
      ]);
      expect(setup.stations.first.isMangal, isTrue);
      expect(setup.serverTime, DateTime.utc(2026, 10, 22, 8));
      expect(adapter.requests.single.uri.path, '/api/v1/pos/bootstrap');
      expect(adapter.requests.single.queryParameters['branch_id'], 3);
    },
  );

  test(
    'an unreadable bootstrap is "unexpected response", not "offline"',
    () async {
      adapter.handler = (RequestOptions o) => ok('nope');
      await expectLater(
        service.setup(),
        throwsA(
          isA<ApiException>().having(
            (ApiException e) => e.isUnexpectedResponse,
            'unexpected',
            isTrue,
          ),
        ),
      );
    },
  );

  test(
    'batches parse remaining portions; closed ones need include_closed',
    () async {
      adapter.handler = (RequestOptions o) => ok(<String, Object?>{
        'branch_id': 3,
        'batches': <Object?>[batch(left: 0), batch(id: 2, open: false)],
      });
      final List<KitchenBatch> list = await service.batches(
        branchId: 3,
        includeClosed: true,
      );
      expect(list.first.isSoldOut, isTrue);
      expect(list.first.fractionLeft, 0);
      expect(list.last.isOpen, isFalse);
      expect(list.first.title, 'Osh');
      expect(list.first.productKey, '9/0');
      expect(adapter.requests.single.queryParameters['include_closed'], true);
    },
  );

  test('open, refill, recount and close send the documented bodies', () async {
    adapter.handler = (RequestOptions o) => ok(batch());
    await service.openBatch(
      branchId: 3,
      productId: 9,
      productVariantId: 90,
      quantity: 40,
      label: ' 1-qozon ',
    );
    await service.refill(1, 10, reason: 'Yana pishirildi');
    await service.setLeft(1, 7);
    final KitchenBatch? closed = await service.closeBatch(1);
    expect(closed?.id, 1);

    final List<RequestOptions> r = adapter.requests;
    expect(r[0].path, '/v1/owner/batches');
    expect(r[0].data, <String, Object?>{
      'branch_id': 3,
      'product_id': 9,
      'product_variant_id': 90,
      'quantity': 40,
      'label': '1-qozon',
    });
    expect(r[1].path, '/v1/owner/batches/1/adjust');
    expect(r[1].data, <String, Object?>{
      'add': 10,
      'reason': 'Yana pishirildi',
    });
    expect(r[2].data, <String, Object?>{'quantity_left': 7});
    expect(r[3].path, '/v1/owner/batches/1/close');
  });

  test(
    'a refused batch action is an ApiException with the server message',
    () async {
      adapter.handler = (RequestOptions o) =>
          refuse(422, 'Bu taom uchun ochiq qozon bor.');
      await expectLater(
        service.openBatch(productId: 9, quantity: 5),
        throwsA(
          isA<ApiException>()
              .having((ApiException e) => e.statusCode, 'status', 422)
              .having(
                (ApiException e) => e.message,
                'message',
                'Bu taom uchun ochiq qozon bor.',
              ),
        ),
      );
    },
  );

  test(
    'a 2xx the app cannot read still means the action happened (null result)',
    () async {
      adapter.handler = (RequestOptions o) => ok('weird');
      expect(await service.closeBatch(1), isNull);
    },
  );

  test(
    'batchProducts lists only batch-tracked dishes with their variants',
    () async {
      adapter.handler = (RequestOptions o) => ok(<String, Object?>{
        'channel': 'pos_hall',
        'products': <Object?>[
          <String, Object?>{
            'id': 5,
            'name': 'Qozon kabob',
            'batch_tracked': true,
            'variants': <Object?>[
              <String, Object?>{'id': 51, 'name': '1 kg'},
              <String, Object?>{'id': 52, 'name': '2 kg'},
            ],
          },
          <String, Object?>{'id': 6, 'name': 'Somsa', 'batch_tracked': false},
        ],
      });
      final List<BatchProduct> products = await service.batchProducts(
        branchId: 3,
      );
      expect(products.single.name, 'Qozon kabob');
      expect(
        products.single.variants.map((BatchProductVariant v) => v.id),
        <int>[51, 52],
      );
      expect(adapter.requests.single.queryParameters, <String, Object?>{
        'channel': 'pos_hall',
        'branch_id': 3,
      });
    },
  );

  test('orderAsTicket reads a cancelled order with its voided lines', () async {
    adapter.handler = (RequestOptions o) => ok(<String, Object?>{
      'uuid': 'aaaaaaaa-0000-4000-8000-000000000001',
      'short_code': 'P7',
      'status': 'canceled',
      'type': 'dine_in',
      'channel': 'pos_hall',
      'table_ref': 'Xona 3',
      'waiting_seconds': 600,
      'items': <Object?>[
        <String, Object?>{
          'id': 1,
          'product_name': "Qo'y shashlik",
          'quantity': 4,
          'unit': 'piece',
          'quantity_label': '4',
          'kitchen_status': 'void',
          'void_reason': 'Mehmonlar ketdi',
          'voided_at': '2026-10-22T08:00:00Z',
          'round_no': 2,
          'fire_mode': 'now',
          'station_id': 1,
          'sent_at': '2026-10-22T07:50:00Z',
        },
      ],
    });
    final KitchenTicket? t = await service.orderAsTicket(
      'aaaaaaaa-0000-4000-8000-000000000001',
    );
    expect(t!.status, 'canceled');
    expect(t.receivedAt, DateTime.utc(2026, 10, 22, 8));
    final KitchenLine line = t.lines.single;
    expect(line.isVoid, isTrue);
    expect(line.voidReason, 'Mehmonlar ketdi');
    expect(line.roundNo, 2);
    expect(line.stationId, 1);
    expect(line.isPos, isTrue);
    expect(t.activeCount, 0);
  });

  test('POS line fields: held, served, done and piece units', () {
    KitchenLine line(Map<String, Object?> extra) =>
        KitchenLine.fromJson(<String, Object?>{
          'id': 1,
          'product_name': 'Osh',
          'quantity': 1,
          'unit': 'piece',
          'quantity_label': '1',
          'kitchen_status': 'sent',
          ...extra,
        });
    expect(line(<String, Object?>{}).isPos, isFalse);
    expect(line(<String, Object?>{}).isPiece, isTrue);
    expect(
      line(<String, Object?>{'fire_mode': 'hold', 'kitchen_status': 'pending'})
          .isHeld,
      isTrue,
    );
    expect(
      line(<String, Object?>{
        'fire_mode': 'hold',
        'sent_at': '2026-10-22T08:00:00Z',
      }).isHeld,
      isFalse,
    );
    final KitchenLine served = line(<String, Object?>{
      'kitchen_status': 'served',
    });
    expect(served.isDone, isTrue);
    expect(served.isReady, isFalse);
    final KitchenTicket t = KitchenTicket.fromJson(
      jsonDecode(
        jsonEncode(<String, Object?>{
          'uuid': 'u',
          'status': 'preparing',
          'type': 'dine_in',
          'items': <Object?>[
            <String, Object?>{
              'id': 1,
              'product_name': 'A',
              'quantity': 1,
              'unit': 'piece',
              'quantity_label': '1',
              'kitchen_status': 'served',
            },
            <String, Object?>{
              'id': 2,
              'product_name': 'B',
              'quantity': 1,
              'unit': 'piece',
              'quantity_label': '1',
              'kitchen_status': 'void',
            },
          ],
        }),
      ) as Map<String, Object?>,
      receivedAt: DateTime.utc(2026),
    );
    expect(t.readyCount, 1);
    expect(t.activeCount, 1);
    expect(t.allLinesReady, isTrue);
  });
}
