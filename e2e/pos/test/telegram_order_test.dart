/// A customer orders from the Telegram bot (the Mini App's `POST /orders`, a pickup order on the cash method); the
/// order appears in the cashier's incoming queue, the cashier accepts it at the till and it goes to the kitchen: the
/// cook marks the lines ready, the order becomes ready and is handed over. A second order is rejected, which needs the
/// manager's PIN. The customer is the only part of this test that does not go through `pos_data`.
@Timeout(Duration(minutes: 3))
library;

import 'dart:async';

import 'package:dio/dio.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/kitchen.dart';
import 'support/menu.dart';
import 'support/stack.dart';

void main() {
  late StackConfig config;
  late Terminal till;
  late Terminal kds;
  late Menu menu;
  late Dio customer;
  late OnlineOrderRepository online;
  late Staff manager;

  setUpAll(() async {
    config = StackConfig.fromEnvironment();
    till = await Terminal.open(config, 'cashier');
    kds = await Terminal.open(config, 'kds');
    manager = config.seed.staff('manager');
    await till.signIn('cashier');
    await kds.signIn('cook');
    await Future.wait(<Future<EventLog>>[till.listen(), kds.listen()]);
    menu = Menu(
      await till.pos.catalog.load(
        PosChannel.hall,
        menuVersion: till.bootstrap.menuVersion,
      ),
    );
    online = OnlineOrderRepository(till.pos.http);
    // The customer of the Telegram bot, signed in (a token with no device is the `telegram` channel)
    customer = Dio(
      BaseOptions(
        baseUrl: config.apiUrl,
        headers: <String, Object?>{
          'Authorization': 'Bearer ${config.seed.customerToken}',
          'Accept': 'application/json',
        },
      ),
    );
  });

  tearDownAll(() async {
    customer.close();
    await till.close();
    await kds.close();
  });

  /// `POST /orders`: what the Mini App sends for a pickup at the branch.
  Future<String> placeOrder(List<Dish> dishes, {required String notes}) async {
    final Response<Object?> response = await customer.post<Object?>(
      '/v1/orders',
      options: Options(
        headers: <String, Object?>{'Idempotency-Key': till.pos.http.newKey()},
      ),
      data: <String, Object?>{
        'store_id': config.seed.storeId,
        'branch_id': config.seed.branchId,
        'type': 'pickup',
        'payment_method': 'cash',
        'notes': notes,
        'items': <Map<String, Object?>>[
          for (final Dish d in dishes)
            <String, Object?>{
              'variant_id': menu.variant(d.name).uuid,
              'quantity': d.quantity.format(),
            },
        ],
      },
    );
    final Map<String, Object?> data =
        ((response.data! as Map<Object?, Object?>)['data']!
                as Map<Object?, Object?>)
            .cast<String, Object?>();
    expect(data['status'], 'new');
    return data['order_uuid']! as String;
  }

  Future<String> customerSees(String uuid) async {
    final Response<Object?> response = await customer.get<Object?>(
      '/v1/orders',
      queryParameters: <String, Object?>{'scope': 'history'},
    );
    final List<Object?> orders =
        ((response.data! as Map<Object?, Object?>)['data']! as List<Object?>);
    for (final Object? o in orders) {
      final Map<Object?, Object?> order = o! as Map<Object?, Object?>;
      if (order['uuid'] == uuid) return order['status']! as String;
    }
    return 'not in history';
  }

  test(
    'a Telegram order is accepted at the till, cooked and handed over',
    () async {
      final List<Dish> dishes = <Dish>[
        Dish.of('Shashlik', 4),
        Dish.of('Achichuk', 1),
        Dish.of('Choy', 1),
      ];
      final String uuid = await placeOrder(
        dishes,
        notes: 'Soat 20:00 ga olib ketaman',
      );

      // The till hears it, and finds it in the incoming queue (not yet among the open orders)
      await till.log!.waitFor(
        (PosEvent e) =>
            e is PosOrderEvent && e.isCreated && e.orderUuid == uuid,
        what: 'order.created',
      );
      final List<PosOnlineOrder> pending = await online.pending();
      final PosOnlineOrder incoming = pending.singleWhere(
        (PosOnlineOrder o) => o.uuid == uuid,
      );
      expect(incoming.isNew, isTrue);
      expect(incoming.channel, 'telegram');
      expect(incoming.type, 'pickup');
      expect(incoming.paymentMethod, 'cash');
      expect(incoming.customerName, 'Aziz Mijoz');
      expect(incoming.items, hasLength(3));
      // No service charge, room fee or place fee on an online order: the lines are the whole price
      expect(incoming.totalPrice, menu.total(dishes));
      expect(incoming.notes, contains('olib ketaman'));
      expect(
        (await till.pos.orders.openOrders()).where(
          (PosOpenOrder o) => o.uuid == uuid,
        ),
        isEmpty,
      );
      // ... and the kitchen does not see it yet
      expect(
        (await kds.client.kitchen.tickets()).tickets.where(
          (t) => t.uuid == uuid,
        ),
        isEmpty,
      );

      // The cashier accepts it
      final PosOnlineOrder accepted = await online.accept(uuid);
      expect(accepted.status, 'accepted');
      final PosOpenOrder queued = (await till.pos.orders.openOrders())
          .singleWhere((PosOpenOrder o) => o.uuid == uuid);
      expect(queued.isPos, isFalse);
      expect(queued.channel, 'telegram');
      expect(
        (await online.pending()).where((PosOnlineOrder o) => o.uuid == uuid),
        isEmpty,
      );
      // Accepting twice changes nothing
      expect((await online.accept(uuid)).status, 'accepted');

      // The kitchen display has the ticket, every line still to cook
      final KitchenTicket ticket = await eventually<KitchenTicket>(() async {
        final KitchenTicketList list = await kds.client.kitchen.tickets();
        for (final KitchenTicket t in list.tickets) {
          if (t.uuid == uuid) return t;
        }
        return null;
      }, what: 'the ticket on the kitchen display');
      expect(ticket.channel, 'telegram');
      expect(ticket.lines, hasLength(3));
      expect(
        ticket.lines.every(
          (KitchenLine l) => l.status == KitchenLineStatus.pending,
        ),
        isTrue,
      );

      // The cook marks the lines ready; the last one makes the order ready (it is a pickup)
      for (final KitchenLine line in ticket.lines) {
        expect(await kds.client.kitchen.bumpLine(uuid, line.id), isNotNull);
      }
      await till.log!.waitFor(
        (PosEvent e) =>
            e is PosOrderEvent &&
            e.orderUuid == uuid &&
            e.order.status == 'ready',
        what: 'order.status_changed ready',
      );
      final KitchenTicket done = (await ticketsIncludingReady(kds))
          .singleWhere((t) => t.uuid == uuid);
      expect(done.lines.every((KitchenLine l) => l.isReady), isTrue);
      expect(
        (await till.pos.orders.openOrders())
            .singleWhere((PosOpenOrder o) => o.uuid == uuid)
            .status,
        'ready',
      );

      // The customer arrives, pays cash and takes it
      await till.client.dio.post<Object?>(
        '/v1/staff/orders/$uuid/status',
        data: <String, Object?>{'status': 'delivered'},
      );
      expect(
        (await till.pos.orders.openOrders()).where(
          (PosOpenOrder o) => o.uuid == uuid,
        ),
        isEmpty,
      );
      expect(await customerSees(uuid), 'delivered');
    },
  );

  test(
    'an order the kitchen cannot make is rejected with the manager\'s approval',
    () async {
      final String uuid = await placeOrder(<Dish>[
        Dish.of('Osh', '1.5'),
      ], notes: 'Osh 1.5 kg');
      await till.log!.waitFor(
        (PosEvent e) =>
            e is PosOrderEvent && e.isCreated && e.orderUuid == uuid,
        what: 'order.created',
      );

      // The cashier may not cancel orders on his own: the server asks for orders.cancel
      var asked = 0;
      final PosOnlineOrder rejected = await runWithApproval<PosOnlineOrder>(
        (String? token) => online.reject(
          uuid,
          reason: PosRejectReason.outOfStock,
          note: 'Osh tugadi',
          approvalToken: token,
        ),
        approve: (String permission, PosException cause) async {
          asked++;
          expect(permission, PosPermissions.ordersCancel);
          return till.pos.approvals.request(
            permission: permission,
            userId: manager.id,
            pin: manager.pin!,
          );
        },
      );
      expect(asked, 1);
      expect(rejected.status, 'rejected');
      expect(
        (await online.pending()).where((PosOnlineOrder o) => o.uuid == uuid),
        isEmpty,
      );
      expect(await customerSees(uuid), 'rejected');
      // It never reached the kitchen
      expect(
        (await ticketsIncludingReady(kds)).where((t) => t.uuid == uuid),
        isEmpty,
      );
    },
  );
}
