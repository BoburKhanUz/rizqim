import 'package:rizqim_core/rizqim_core.dart';
import 'package:test/test.dart';

void main() {
  group('OrderStatus', () {
    test('has exactly the backend values (App\\Enums\\OrderStatus)', () {
      expect(
        OrderStatus.values.map((OrderStatus s) => s.wire).toList(),
        <String>[
          'new',
          'accepted',
          'preparing',
          'ready',
          'courier_assigned',
          'on_the_way',
          'delivery',
          'delivered',
          'canceled',
          'rejected',
          'refunded',
        ],
      );
    });

    test('colour groups follow docs/DESIGN.md', () {
      expect(OrderStatus.newOrder.tone, StatusTone.warning);
      for (final OrderStatus s in <OrderStatus>[
        OrderStatus.accepted,
        OrderStatus.preparing,
        OrderStatus.ready,
        OrderStatus.courierAssigned,
        OrderStatus.onTheWay,
        OrderStatus.delivery,
      ]) {
        expect(s.tone, StatusTone.info, reason: s.wire);
      }
      expect(OrderStatus.delivered.tone, StatusTone.success);
      expect(OrderStatus.canceled.tone, StatusTone.danger);
      expect(OrderStatus.rejected.tone, StatusTone.danger);
      expect(OrderStatus.refunded.tone, StatusTone.neutral);
    });

    test('an unknown status is neutral and never another status', () {
      expect(OrderStatus.fromWire('teleported'), isNull);
      expect(OrderStatus.fromWire(null), isNull);
      expect(OrderStatus.toneOf('teleported'), StatusTone.neutral);
      expect(OrderStatus.toneOf('delivered'), StatusTone.success);
      expect(OrderStatus.fromWire('on_the_way'), OrderStatus.onTheWay);
    });

    test('the two names of "en route" look the same', () {
      expect(OrderStatus.onTheWay.tone, OrderStatus.delivery.tone);
      expect(OrderStatus.onTheWay.label, OrderStatus.delivery.label);
    });

    test('labels: customer and staff words', () {
      expect(OrderStatus.newOrder.label, 'Kutilmoqda');
      expect(OrderStatus.newOrder.staffLabel, 'Yangi');
      expect(OrderStatus.courierAssigned.staffLabel, 'Kuryer kutilmoqda');
      for (final OrderStatus s in OrderStatus.values) {
        expect(s.label, isNotEmpty);
        expect(s.staffLabel, isNotEmpty);
      }
    });

    test('flags', () {
      expect(OrderStatus.canceled.isCancellation, isTrue);
      expect(OrderStatus.rejected.isCancellation, isTrue);
      expect(OrderStatus.refunded.isCancellation, isFalse);
      expect(
        OrderStatus.values.where((OrderStatus s) => s.isWithCourier),
        <OrderStatus>[
          OrderStatus.courierAssigned,
          OrderStatus.onTheWay,
          OrderStatus.delivery,
        ],
      );
      expect(OrderStatus.preparing.isInKitchen, isTrue);
      expect(OrderStatus.ready.isInKitchen, isFalse);
    });

    test('transitions match App\\Enums\\OrderStatus::allowedTransitions', () {
      expect(OrderStatus.newOrder.allowedTransitions(null), <OrderStatus>[
        OrderStatus.accepted,
        OrderStatus.rejected,
        OrderStatus.canceled,
      ]);
      expect(
        OrderStatus.preparing.allowedTransitions(OrderType.delivery),
        <OrderStatus>[
          OrderStatus.ready,
          OrderStatus.courierAssigned,
          OrderStatus.onTheWay,
          OrderStatus.delivery,
          OrderStatus.canceled,
        ],
      );
      expect(
        OrderStatus.preparing.allowedTransitions(OrderType.dineIn),
        <OrderStatus>[
          OrderStatus.ready,
          OrderStatus.delivered,
          OrderStatus.canceled,
        ],
      );
      expect(
        OrderStatus.ready.canTransitionTo(
          OrderStatus.delivered,
          OrderType.pickup,
        ),
        isTrue,
      );
      expect(
        OrderStatus.ready.canTransitionTo(
          OrderStatus.delivered,
          OrderType.delivery,
        ),
        isFalse,
      );
      expect(
        OrderStatus.delivered.allowedTransitions(OrderType.delivery),
        <OrderStatus>[OrderStatus.refunded],
      );
      for (final OrderStatus s in <OrderStatus>[
        OrderStatus.canceled,
        OrderStatus.rejected,
        OrderStatus.refunded,
      ]) {
        expect(s.allowedTransitions(OrderType.delivery), isEmpty);
      }
    });
  });

  test('OrderType', () {
    expect(OrderType.fromWire('dine_in'), OrderType.dineIn);
    expect(OrderType.fromWire('x'), isNull);
    expect(OrderType.values.map((OrderType t) => t.wire), <String>[
      'delivery',
      'pickup',
      'dine_in',
    ]);
  });
}
