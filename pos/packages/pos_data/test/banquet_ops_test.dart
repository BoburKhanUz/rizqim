import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

Map<String, Object?> _settlement({Object? settlement}) => <String, Object?>{
  'event': <String, Object?>{
    'uuid': 'e1',
    'status': 'in_progress',
    'version': 5,
    'order_uuid': 'o1',
  },
  'guests': <String, Object?>{
    'guaranteed': 200,
    'minimum': 150,
    'actual': 180,
    'billed': 200,
  },
  'lines': <Object?>[
    <String, Object?>{
      'uuid': 'l1',
      'kind': 'package',
      'name': 'Standart',
      'unit_price': '150000.00',
      'quantity': '1.000',
      'persons': 200,
      'amount': '30000000.00',
    },
  ],
  'package_total': '30000000.00',
  'hall_total': '5000000.00',
  'extras_total': '1800000.00',
  'orders': <String, Object?>{
    'lines': '60000.00',
    'discount': '0.00',
    'service_charge': '0.00',
    'other_charges': '0.00',
    'total': '60000.00',
  },
  'total': '36860000.00',
  'advances': <String, Object?>{
    'total': '25760000.00',
    'applied': '0.00',
    'rows': <Object?>[],
  },
  'due': '11100000.00',
  'excess': '0.00',
  'settlement': settlement,
  'warnings': <Object?>[],
};

void main() {
  late Harness h;

  setUp(() async {
    h = Harness();
    await h.signIn();
  });

  Future<RequestOptions> call(
    Object? data,
    Future<Object?> Function() action,
  ) async {
    h.adapter.respond = (RequestOptions o) => FakeResponse(
      200,
      <String, Object?>{'success': true, 'message': '', 'data': data},
    );
    await action();
    return h.last;
  }

  test('the production sheet reads stations, kg and the osh kazans', () async {
    late PosProductionSheet sheet;
    final RequestOptions r = await call(<String, Object?>{
      'event': <String, Object?>{
        'uuid': 'e1',
        'title': null,
        'event_type': 'toy',
        'event_type_label': "To'y",
        'event_date': '2027-01-20',
        'slot': 'evening',
        'local_time': '18:00–23:00',
        'status': 'confirmed',
        'halls': <String>['Oq zal'],
      },
      'guests': <String, Object?>{
        'guaranteed': 200,
        'minimum': 150,
        'actual': null,
        'base': 200,
        'reserve_percent': '10.00',
        'planned': 220,
      },
      'stations': <Object?>[
        <String, Object?>{
          'station_id': 3,
          'name': 'Osh sexi',
          'dishes': <Object?>[
            <String, Object?>{
              'product_id': 9,
              'name': "To'y oshi",
              'unit': 'portion',
              'quantity_per_person': '1.000',
              'quantity': '220.000',
              'kg': '77.000',
            },
          ],
        },
      ],
      'osh': <String, Object?>{
        'persons': 220,
        'persons_per_kg': '5.0',
        'osh_kg': '44.0',
        'ingredients': <Object?>[
          <String, Object?>{
            'key': 'rice',
            'name': 'Guruch',
            'unit': 'kg',
            'per_kg': '1.000',
            'quantity': '44.000',
          },
        ],
        'kazans': <Object?>[
          <String, Object?>{'size_kg': 50, 'count': 1},
        ],
        'kazan_capacity_kg': '50.0',
      },
      'warnings': <Object?>[
        <String, Object?>{
          'code': 'guests_over_threshold',
          'message': "Mehmonlar soni 220: 200 dan ko'p.",
        },
      ],
    }, () async => sheet = await h.pos.banquetOps.productionSheet('e1'));

    expect(r.uri.path, '/api/v1/pos/banquets/e1/production-sheet');
    expect(sheet.title, "To'y");
    expect(sheet.guestsPlanned, 220);
    expect(sheet.stations.single.$1, 'Osh sexi');
    final PosBanquetDish dish = sheet.stations.single.$2.single;
    expect(dish.quantity, PosQuantity.parse('220'));
    expect(dish.kg, PosQuantity.parse('77'));
    expect(sheet.osh!.kazans, <(int, int)>[(50, 1)]);
    expect(sheet.osh!.ingredients.single.quantity, PosQuantity.parse('44'));
    expect(sheet.warnings.single.code, 'guests_over_threshold');
  });

  test('the shopping list sends the dates and sums the osh', () async {
    late PosShoppingList list;
    final RequestOptions r = await call(
      <String, Object?>{
        'branch_id': 1,
        'from': '2027-01-20',
        'to': '2027-01-22',
        'events': <Object?>[
          <String, Object?>{
            'uuid': 'e1',
            'title': null,
            'event_type': 'toy',
            'event_date': '2027-01-20',
            'slot': 'evening',
            'status': 'confirmed',
            'planned_guests': 220,
          },
        ],
        'dishes': <Object?>[
          <String, Object?>{
            'product_id': null,
            'name': 'Non',
            'unit': 'piece',
            'quantity_per_person': null,
            'quantity': '330.000',
            'kg': null,
          },
        ],
        'osh': <String, Object?>{
          'persons': 330,
          'osh_kg': '66.0',
          'ingredients': <Object?>[],
        },
      },
      () async => list = await h.pos.banquetOps.shoppingList(
        from: '2027-01-20',
        to: '2027-01-22',
      ),
    );

    expect(r.uri.queryParameters, <String, String>{
      'from': '2027-01-20',
      'to': '2027-01-22',
    });
    expect(list.events.single, ('e1', '2027-01-20', 'toy', 220));
    expect(list.dishes.single.quantityPerPerson, isNull);
    expect(list.oshKg, PosQuantity.parse('66'));
  });

  test(
    'settle sends the shift with an Idempotency-Key and returns the bill',
    () async {
      late PosSettleResult result;
      final RequestOptions r = await call(
        <String, Object?>{
          'settlement': _settlement(
            settlement: <String, Object?>{
              'uuid': 's1',
              'status': 'open',
              'bill_uuid': 'b1',
              'applied_amount': '25760000.00',
              'excess_amount': '0.00',
              'excess_status': 'none',
              'settled_at': null,
            },
          ),
          'bill': <String, Object?>{
            'uuid': 'b1',
            'order_uuid': 'o1',
            'number': 1,
            'status': 'open',
            'total': '36860000.00',
            'paid_amount': '25760000.00',
            'due_amount': '11100000.00',
          },
        },
        () async =>
            result = await h.pos.banquetOps.settle('e1', shiftUuid: 'sh1'),
      );

      expect(r.method, 'POST');
      expect(r.uri.path, '/api/v1/pos/banquets/e1/settle');
      expect(r.headers['Idempotency-Key'], matches(uuidV4));
      expect(Harness.bodyOf(r), <String, Object?>{'shift_uuid': 'sh1'});
      expect(result.settlement.status, PosSettlementStatus.open);
      expect(result.settlement.billUuid, 'b1');
      expect(result.settlement.guestsBilled, 200);
      expect(result.settlement.lines.single.persons, 200);
      expect(result.bill.dueAmount, const PosMoney.som(11100000));
    },
  );

  test('guests, start and the contract number', () async {
    final RequestOptions guests = await call(<String, Object?>{
      'event': <String, Object?>{},
      'settlement': _settlement(),
    }, () async => h.pos.banquetOps.setGuestsActual('e1', 260));
    expect(guests.method, 'PATCH');
    expect(Harness.bodyOf(guests), <String, Object?>{'guests_actual': 260});

    late String? order;
    await call(<String, Object?>{
      'event': <String, Object?>{
        'uuid': 'e1',
        'status': 'in_progress',
        'version': 3,
        'order_uuid': 'o9',
        'started_at': null,
      },
    }, () async => order = await h.pos.banquetOps.start('e1'));
    expect(order, 'o9');

    late PosBanquetContract contract;
    final RequestOptions numbered = await call(<String, Object?>{
      'contract_no': '2026-0001',
      'text': 'SHARTNOMA\nKarimovlar',
      'lines': <String>[],
      'fields': <String, Object?>{},
      'warnings': <Object?>[],
    }, () async => contract = await h.pos.banquetOps.numberContract('e1'));
    expect(numbered.uri.path, '/api/v1/pos/banquets/e1/contract/number');
    expect(contract.contractNo, '2026-0001');
    expect(contract.lines, <String>['SHARTNOMA', 'Karimovlar']);
  });

  test('offline: a plain message, no request', () async {
    final PosBanquetOpsRepository repository = PosBanquetOpsRepository(
      h.pos.http,
      offline: _OfflineAlways(),
    );
    await expectLater(
      repository.settlement('e1'),
      throwsA(isA<PosOfflineUnavailable>()),
    );
  });
}

class _OfflineAlways implements PosOffline {
  @override
  bool get active => true;

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
