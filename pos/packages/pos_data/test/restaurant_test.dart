import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'offline_test.dart' show OfflineHarness;
import 'support/harness.dart';

/// P6-21 / P6-25 in pos_data: courses (a later course held, "N-kursni yuborish"), "weight later" (no bill while a line
/// waits, the real kg), the table plate, the catalog's skewer buttons and grill time, the course-ready event, and the
/// same rules offline (the till's own view and the queued operations).
void main() {
  PosLineInput line(
    int variantId,
    String quantity, {
    int? course,
    bool? awaitingWeight,
  }) => PosLineInput(
    variantUuid: FakePosState.variantUuid(variantId),
    quantity: PosQuantity.parse(quantity),
    courseNo: course,
    awaitingWeight: awaitingWeight,
  );

  /// A fish sold by the kg and weighed after it is chosen (product 9), 245 000 so'm per kg.
  void addFish(FakePosState state) {
    final Map<String, Object?> fish = state.sampleProduct(
      9,
      1,
      'Sazan',
      '245000.00',
    );
    final Map<String, Object?> variant =
        (fish['variants']! as List<Object?>).single! as Map<String, Object?>;
    variant
      ..['unit'] = 'kg'
      ..['min_quantity'] = '0.5'
      ..['quantity_step'] = '0.1';
    fish
      ..['weight_mode'] = 'raw'
      ..['quick_multipliers'] = <int>[]
      ..['wait_minutes'] = 20;
    state.products.add(fish);
  }

  group('online', () {
    late Harness h;

    setUp(() async {
      h = Harness();
      addFish(h.state);
      await h.signIn();
    });

    test('a later course is held; "2-kursni yuborish" sends it', () async {
      final PosOrder opened = await h.pos.orders.open(
        channel: PosChannel.hall,
        tableId: 1,
        guestsCount: 2,
        items: <PosLineInput>[line(1, '1', course: 1), line(4, '3', course: 2)],
        send: true,
      );
      expect(
        (Harness.bodyOf(h.last)['items']! as List<Object?>).last,
        <String, Object?>{
          'variant_id': FakePosState.variantUuid(4),
          'quantity': '3.000',
          'course_no': 2,
        },
      );
      expect(opened.items.map((PosOrderItem i) => i.courseNo), <int?>[1, 2]);
      expect(opened.items.last.fireMode, PosFireMode.hold);
      expect(opened.unsentItems.single.courseNo, 2);

      final PosOrder sent = await h.pos.orders.sendCourse(
        opened.uuid,
        2,
        version: opened.version,
      );
      expect(h.last.path, endsWith('/courses/2/send'));
      expect(Harness.bodyOf(h.last), <String, Object?>{
        'version': opened.version,
      });
      expect(sent.unsentItems, isEmpty);

      await expectLater(
        h.pos.orders.sendCourse(sent.uuid, 2, version: sent.version),
        throwsA(
          isA<PosRejected>().having(
            (PosRejected e) => e.reason,
            'reason',
            'nothing_to_send',
          ),
        ),
      );
    });

    test('a fish waits for its weight, then carries the real kg', () async {
      final PosOrder opened = await h.pos.orders.open(
        channel: PosChannel.hall,
        tableId: 2,
        guestsCount: 2,
        items: <PosLineInput>[line(9, '1.5')],
      );
      final PosOrderItem fish = opened.items.single;
      expect(fish.awaitingWeight, isTrue);
      expect(fish.weightBasis, PosWeightBasis.raw);

      final PosOrder weighed = await h.pos.orders.setWeight(
        opened.uuid,
        fish.id,
        version: opened.version,
        quantity: PosQuantity.parse('1.35'),
      );
      expect(h.last.path, endsWith('/items/${fish.id}/weight'));
      expect(Harness.bodyOf(h.last)['quantity'], '1.350');
      expect(weighed.items.single.weightStatus, PosWeightStatus.weighed);
      expect(weighed.items.single.totalPrice, const PosMoney.som(330750));
      expect(weighed.items.single.weighedBy?.id, 3);

      final PosOrder labelled = await h.pos.orders.setTableLabel(
        weighed.uuid,
        version: weighed.version,
        tableLabel: '12',
      );
      expect(labelled.tableLabel, '12');
    });

    test('models: courses, weight, table plate, catalog fields and the course-ready event', () {
      final PosOrder order = PosOrder.fromJson(
        const PosJson(<String, Object?>{
          'uuid': 'o-1',
          'status': 'preparing',
          'channel': 'pos_takeaway',
          'version': 4,
          'table_label': 'A7',
          'weight_pending': 1,
          'courses': <Object?>[
            <String, Object?>{
              'course_no': 1,
              'status': 'ready',
              'items': 2,
              'sent': 2,
              'ready': 2,
              'served': 0,
            },
            <String, Object?>{
              'course_no': 2,
              'status': 'held',
              'items': 1,
              'sent': 0,
              'ready': 0,
              'served': 0,
            },
          ],
          'items': <Object?>[
            <String, Object?>{
              'id': 5,
              'product_name': 'Sazan',
              'quantity': 1.5,
              'kitchen_status': 'pending',
              'course_no': 2,
              'weight_status': 'awaiting',
              'weight_basis': 'cooked',
            },
          ],
        }),
      );
      expect(order.tableLabel, 'A7');
      expect(order.weightPending, 1);
      expect(order.course(1)!.status, PosCourseStatus.ready);
      expect(order.course(2)!.canSend, isTrue);
      expect(order.course(2)!.label, '2-kurs');
      expect(order.items.single.awaitingWeight, isTrue);
      expect(order.items.single.weightBasis, PosWeightBasis.cooked);

      final PosProduct product = PosProduct.fromJson(
        const PosJson(<String, Object?>{
          'id': 1,
          'name': 'Shashlik',
          'variants': <Object?>[],
          'quick_multipliers': <Object?>[2, 3, 5, 10],
          'wait_minutes': 15,
          'weight_mode': null,
        }),
      );
      expect(product.quickMultipliers, <int>[2, 3, 5, 10]);
      expect(product.waitMinutes, 15);
      expect(product.weighedLater, isFalse);

      final PosEvent event = PosEvent.fromWire(
        'order.course_ready',
        <String, Object?>{
          'order': <String, Object?>{'uuid': 'o-1', 'status': 'preparing'},
          'version': 6,
          'course_no': 2,
          'item_ids': <Object?>[7, 8],
          'table_id': 3,
          'waiter_id': 5,
        },
      );
      expect(event, isA<PosCourseReady>());
      final PosCourseReady ready = event as PosCourseReady;
      expect(ready.orderUuid, 'o-1');
      expect(ready.courseNo, 2);
      expect(ready.itemIds, <int>[7, 8]);
      expect(ready.isFor(5), isTrue);
      expect(ready.isFor(6), isFalse);
    });
  });

  /// A restaurant branch's `format` block; `weightLater`: the branch's `weight_later` module.
  Map<String, Object?> restaurantFormat({bool weightLater = true}) =>
      <String, Object?>{
        'profile': 'restoran',
        'profile_label': 'Restoran',
        'mode': 'hybrid',
        'first_screen': 'hall_map',
        'modules': <String>[
          'quick_counter',
          'service_charge',
          'courses',
          'table_service',
          if (weightLater) 'weight_later',
        ],
        'locks': <String, Object?>{'service_charge_zero': false},
      };

  group('offline', () {
    late OfflineHarness h;
    late FakeSyncBackend sync;

    setUp(() async {
      h = await OfflineHarness.create();
      addFish(h.state);
      h.state.format = restaurantFormat();
      FakeCashierBackend(h.state);
      sync = FakeSyncBackend(h.state);
      h.state.openShift();
      await h.signIn();
    });

    tearDown(() => h.pos.dispose());

    test('courses and the weight entered offline: the till holds the later course and blocks the bill', () async {
      await h.warmUp();
      await h.goOffline();

      final PosOrder opened = await h.pos.orders.open(
        channel: PosChannel.takeaway,
        tableLabel: '12',
        items: <PosLineInput>[
          line(1, '1', course: 1),
          line(4, '2', course: 2),
          line(9, '1.5', course: 2),
        ],
        send: true,
      );
      expect(opened.tableLabel, '12');
      expect(
        opened.items.map((PosOrderItem i) => i.kitchenStatus),
        <PosKitchenStatus>[
          PosKitchenStatus.sent,
          PosKitchenStatus.pending,
          PosKitchenStatus.pending,
        ],
      );
      expect(opened.course(2)!.status, PosCourseStatus.held);
      expect(opened.weightPending, 1);

      await expectLater(
        h.pos.billing.createBills(opened.uuid, version: opened.version),
        throwsA(
          isA<PosOfflineUnavailable>().having(
            (PosOfflineUnavailable e) => e.reason,
            'reason',
            'weight_pending',
          ),
        ),
      );

      final PosOrder fired = await h.pos.orders.sendCourse(
        opened.uuid,
        2,
        version: opened.version,
      );
      expect(fired.unsentItems, isEmpty);
      expect(fired.course(2)!.status, PosCourseStatus.sent);

      final PosOrderItem fish = fired.items.last;
      final PosOrder weighed = await h.pos.orders.setWeight(
        fired.uuid,
        fish.id,
        version: fired.version,
        quantity: PosQuantity.parse('1.35'),
      );
      expect(weighed.items.last.weightStatus, PosWeightStatus.weighed);
      expect(weighed.items.last.totalPrice, const PosMoney.som(330750));
      expect(weighed.weightPending, 0);

      h.adapter.offline = false;
      final SyncDrainResult result = await h.offline.syncNow();
      expect(result.drained, isTrue);
      expect(sync.received.map((Map<String, Object?> o) => o['type']), <String>[
        SyncOpType.orderOpen,
        SyncOpType.orderCourseSend,
        SyncOpType.orderWeight,
      ]);
      expect(
        sync.results.values.map(
          (Map<String, Object?> r) => '${r['status']} ${r['reason']}',
        ),
        everyElement('applied null'),
      );
      final Map<String, Object?> server =
          h.state.orders[h.offline.store.resolve(opened.uuid)]!;
      expect(server['table_label'], '12');
      final List<Object?> items = server['items']! as List<Object?>;
      expect(
        items.every(
          (Object? i) => (i! as Map<String, Object?>)['sent_at'] != null,
        ),
        isTrue,
      );
      expect((items.last! as Map<String, Object?>)['weight_status'], 'weighed');
    });

    test('without the weight_later module the till sells the fish by the quantity typed (review C6)', () async {
      // A catalog cached before the owner switched the module off still names the weight rule
      h.state.format = restaurantFormat(weightLater: false);
      await h.signIn();
      await h.warmUp();
      await h.goOffline();

      final PosOrder opened = await h.pos.orders.open(
        channel: PosChannel.takeaway,
        items: <PosLineInput>[
          line(9, '1.5'),
          line(9, '1', awaitingWeight: true),
        ],
        send: true,
      );
      expect(opened.items.map((PosOrderItem i) => i.awaitingWeight), <bool>[
        false,
        false,
      ]);
      expect(opened.weightPending, 0);
      final PosBilling billing = await h.pos.billing.createBills(
        opened.uuid,
        version: opened.version,
      );
      expect(billing.bills, isNotEmpty);
    });
  });
}
