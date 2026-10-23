import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P3-26: the data layer on the wave 2 backend gaps — the waiter's bill request, ready/unsent counts and the
/// reservation's guest on the floor, the bootstrap's quick items, the closed orders of the day, an order's booking
/// deposit, and the receipts that come with a deposit.
void main() {
  late Harness h;
  late FakeCashierBackend backend;

  setUp(() async {
    h = Harness();
    backend = FakeCashierBackend(h.state);
    await h.signIn();
  });

  test('request-bill marks the order once and the floor shows it', () async {
    final String uuid = h.state.seedOrder(tableId: 1);
    final PosOrder order = await h.pos.orders.get(uuid);

    final PosOrder asked = await h.pos.orders.requestBill(
      uuid,
      version: order.version,
    );
    expect(h.last.uri.path, '/api/v1/pos/orders/$uuid/request-bill');
    expect(Harness.bodyOf(h.last), <String, Object?>{'version': order.version});
    expect(h.last.headers['Idempotency-Key'], isNotNull);
    expect(asked.billRequestedAt, isNotNull);
    expect(asked.version, order.version + 1);

    // Again with the old version: unchanged (no conflict, no second event)
    final PosOrder again = await h.pos.orders.requestBill(uuid);
    expect(again.version, asked.version);

    final PosFloor floor = await h.pos.floor.load();
    final FloorTable room = floor.halls.first.tables.firstWhere(
      (FloorTable t) => t.id == 1,
    );
    expect(room.billRequested, isTrue);
    expect(room.orders.single.billRequested, isTrue);
    expect(room.orders.single.billRequestedAt, isNotNull);
    expect(
      (await h.pos.orders.openOrders())
          .firstWhere((PosOpenOrder o) => o.uuid == uuid)
          .billRequested,
      isTrue,
    );
  });

  test('the floor counts ready lines and names the reservation', () async {
    final String uuid = h.state.seedOrder(tableId: 1);
    h.state.seedRound(uuid, <(int, String)>[(1, '2')], ready: true);
    h.state.reservations[2] = <String, Object?>{
      'booking_uuid': 'b-1',
      'starts_at': h.now.add(const Duration(minutes: 40)).toIso8601String(),
      'guests': 12,
      'contact_name': 'Jamshid aka',
      'contact_phone': '+998901112233',
    };

    final PosFloor floor = await h.pos.floor.load();
    final FloorOrder order = floor.halls.first.tables
        .firstWhere((FloorTable t) => t.id == 1)
        .orders
        .single;
    expect(order.readyCount, 1);
    expect(order.unsentCount, 0);
    final FloorReservation? reservation = floor.halls.first.tables
        .firstWhere((FloorTable t) => t.id == 2)
        .reservation;
    expect(reservation?.contactName, 'Jamshid aka');
    expect(reservation?.contactPhone, '+998901112233');
  });

  test(
    'quick items come from the bootstrap; the name guess only without them',
    () async {
      PosBootstrap bootstrap = await h.pos.bootstrap.load();
      expect(bootstrap.quickItems, isEmpty);
      final PosCatalog catalog = await h.pos.catalog.load(PosChannel.hall);
      // No list: guessed by name (Choy, Non)
      expect(
        quickProducts(
          catalog,
          bootstrap.quickItems,
        ).map((PosProduct p) => p.name),
        <String>['Choy', 'Non'],
      );

      h.state.quickItems.addAll(<Map<String, Object?>>[
        <String, Object?>{
          'product_id': 2,
          'variant_id': 2,
          'variant_uuid': FakePosState.variantUuid(2),
          'name': 'Non',
          'variant_name': 'Dona',
          'source': 'quick_add',
          'hall_id': null,
          'quantity_per_guest': null,
          'sort_order': 0,
        },
        <String, Object?>{
          'product_id': 1,
          'variant_id': 1,
          'variant_uuid': FakePosState.variantUuid(1),
          'name': 'Choy',
          'variant_name': 'Choynak',
          'source': 'auto_guest',
          'hall_id': 9,
          'quantity_per_guest': '0.500',
          'sort_order': 1,
        },
      ]);
      bootstrap = await h.pos.bootstrap.load();
      expect(bootstrap.quickItems.map((PosQuickItem i) => i.source), <String>[
        'quick_add',
        'auto_guest',
      ]);
      expect(bootstrap.quickItems.last.quantityPerGuest, '0.500');
      // The server's order; another hall's choy-non row is left out there
      expect(
        quickProducts(
          catalog,
          bootstrap.quickItems,
        ).map((PosProduct p) => p.name),
        <String>['Non', 'Choy'],
      );
      expect(
        quickProducts(
          catalog,
          bootstrap.quickItems,
          hallId: 1,
        ).map((PosProduct p) => p.name),
        <String>['Non'],
      );
      final PosProduct bread = catalog.product(2)!;
      expect(
        quickVariantOf(bread, bootstrap.quickItems)?.uuid,
        FakePosState.variantUuid(2),
      );
    },
  );

  test('closed orders of the day for refunds', () async {
    final String uuid = h.state.seedOrder(tableId: 1);
    h.state.orders[uuid]!
      ..['status'] = 'delivered'
      ..['closed_at'] = h.now.toIso8601String();
    h.state.seedOrder(tableId: 2);

    final List<PosOpenOrder> closed = await h.pos.orders.closedOrders(
      businessDate: '2026-10-22',
    );
    expect(h.last.uri.queryParameters, <String, String>{
      'scope': 'closed',
      'business_date': '2026-10-22',
    });
    expect(closed.map((PosOpenOrder o) => o.uuid), <String>[uuid]);
    expect(closed.single.closedAt, isNotNull);
    expect(closed.single.online, isNull);
  });

  test(
    'an order from a booking names its deposit; a deposit brings its receipt',
    () async {
      h.state.openShift();
      final String booking = backend.seedBooking(
        tableId: 2,
        start: h.now.add(const Duration(minutes: 10)),
        deposit: '50000.00',
      );
      final PosDepositResult taken = await h.pos.bookings.takeDeposit(
        booking,
        amount: const PosMoney.som(30000),
        tender: 'cash',
      );
      expect(taken.fiscal.receipt?.receiptType, FiscalReceiptType.advance);
      expect(taken.fiscal.receipt?.total, const PosMoney.som(30000));
      expect(taken.fiscal.receipt?.receiptNumber, isNotNull);

      final PosArrival arrival = await h.pos.bookings.arrive(booking);
      final PosBilling billing = await h.pos.billing.bills(arrival.orderUuid);
      expect(billing.order.bookingUuid, booking);
      expect(billing.order.depositAvailable, const PosMoney.som(80000));
    },
  );
}
