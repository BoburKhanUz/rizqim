/// The branch's real-time channel (`private-pos-branch.{id}` on Reverb, signed with each terminal's own token): what one
/// waiter does at a table reaches the other waiter's phone, the till and the kitchen display within seconds. Each
/// change is timed from the moment the request is sent to the moment the other terminal hears it.
@Timeout(Duration(minutes: 3))
library;

import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/kitchen.dart';
import 'support/menu.dart';
import 'support/stack.dart';

/// "Within N seconds".
const Duration deadline = Duration(seconds: 5);

void main() {
  late StackConfig config;
  late Terminal till;
  late Terminal waiter1;
  late Terminal waiter2;
  late Terminal kds;
  late Menu menu;
  late PosFloor floor;
  late Staff manager;
  final List<Duration> latencies = <Duration>[];

  setUpAll(() async {
    config = StackConfig.fromEnvironment();
    till = await Terminal.open(config, 'cashier');
    waiter1 = await Terminal.open(config, 'waiter1');
    waiter2 = await Terminal.open(config, 'waiter2');
    kds = await Terminal.open(config, 'kds');
    manager = config.seed.staff('manager');
    await till.signIn('cashier');
    await waiter1.signIn('waiter1');
    await waiter2.signIn('waiter2');
    await kds.signIn('cook');
    menu = Menu(
      await waiter1.pos.catalog.load(
        PosChannel.hall,
        menuVersion: waiter1.bootstrap.menuVersion,
      ),
    );
    floor = await waiter1.pos.floor.load();
    await Future.wait(<Future<EventLog>>[
      till.listen(),
      waiter1.listen(),
      waiter2.listen(),
      kds.listen(),
    ]);
  });

  tearDownAll(() async {
    await Future.wait(<Future<void>>[
      till.close(),
      waiter1.close(),
      waiter2.close(),
      kds.close(),
    ]);
    if (latencies.isNotEmpty) {
      final List<Duration> sorted = List<Duration>.of(latencies)..sort();
      stdout.writeln(
        '\n[pos-e2e] real-time: ${sorted.length} deliveries, median '
        '${sorted[(sorted.length - 1) ~/ 2].inMilliseconds} ms, '
        'max ${sorted.last.inMilliseconds} ms (limit ${deadline.inSeconds} s)',
      );
    }
  });

  int tableId(String name) =>
      floor.tables.firstWhere((FloorTable t) => t.name == name).id;

  /// [who] hears an event that satisfies [test] within [deadline] of [sentAt]; the delay is recorded.
  Future<PosEvent> hears(
    Terminal who,
    bool Function(PosEvent e) test,
    DateTime sentAt,
    String what,
  ) async {
    final HeardEvent heard = await who.log!.waitFor(
      test,
      timeout: deadline,
      what: '${who.label} hears $what',
    );
    final Duration delay = heard.at.difference(sentAt);
    latencies.add(delay.isNegative ? Duration.zero : delay);
    expect(
      delay,
      lessThan(deadline),
      reason: '${who.label} heard $what after $delay',
    );
    return heard.event;
  }

  /// Every other terminal hears [test].
  Future<void> everyoneElseHears(
    Terminal origin,
    bool Function(PosEvent e) test,
    DateTime sentAt,
    String what,
  ) async {
    await Future.wait(<Future<PosEvent>>[
      for (final Terminal t in <Terminal>[till, waiter1, waiter2, kds])
        if (t != origin) hears(t, test, sentAt, what),
    ]);
  }

  Future<void> cancel(Terminal who, PosOrder order) =>
      runWithApproval<PosOrder>(
        (String? token) => who.pos.orders.cancel(
          order.uuid,
          version: order.version,
          reason: 'Sinov tugadi',
          approvalToken: token,
        ),
        // The approval is bound to the terminal it is typed on
        approve: (String permission, PosException cause) =>
            who.pos.approvals.request(
              permission: permission,
              userId: manager.id,
              pin: manager.pin!,
            ),
      ).then((_) {});

  test('each waiter sees the other\'s tables, and the till and the kitchen see both', () async {
    // The second waiter's phone keeps its floor map fresh the way the app does: refresh on a relevant event
    final List<PosRefreshReason> refreshes = <PosRefreshReason>[];
    late PosFloor seenByWaiter2;
    final PosRefreshScheduler scheduler = PosRefreshScheduler(
      realtime: waiter2.realtime,
      eventFilter: (PosEvent e) =>
          e is PosOrderEvent || e is PosOrderItemsChanged,
      onRefresh: (PosRefreshReason reason) async {
        refreshes.add(reason);
        seenByWaiter2 = await waiter2.pos.floor.load();
      },
      debounce: const Duration(milliseconds: 100),
    )..start();
    addTearDown(scheduler.dispose);

    final Staff w1 = config.seed.staff('waiter1');
    final Staff w2 = config.seed.staff('waiter2');

    // 1. Waiter 1 opens a table
    DateTime sent = DateTime.now();
    PosOrder first = await waiter1.pos.orders.open(
      channel: PosChannel.hall,
      tableId: tableId('Stol 2'),
      guestsCount: 3,
      waiterId: w1.id,
      items: menu.inputs(<Dish>[Dish.of('Shashlik', 2)]),
    );
    await everyoneElseHears(
      waiter1,
      (PosEvent e) =>
          e is PosOrderEvent && e.isCreated && e.orderUuid == first.uuid,
      sent,
      'order.created of Stol 2',
    );
    // ... and the second waiter's floor map reloaded by itself and shows it
    await eventually<bool>(
      () async =>
          refreshes.contains(PosRefreshReason.event) &&
              seenByWaiter2.table(tableId('Stol 2'))!.orders.isNotEmpty
          ? true
          : null,
      what: 'waiter 2\'s floor map to show Stol 2',
      timeout: deadline,
    );
    expect(
      seenByWaiter2.table(tableId('Stol 2'))!.orders.single.uuid,
      first.uuid,
    );

    // 2. Waiter 2 opens another one
    sent = DateTime.now();
    PosOrder second = await waiter2.pos.orders.open(
      channel: PosChannel.hall,
      tableId: tableId('Tapchan 3'),
      guestsCount: 2,
      waiterId: w2.id,
    );
    await everyoneElseHears(
      waiter2,
      (PosEvent e) =>
          e is PosOrderEvent && e.isCreated && e.orderUuid == second.uuid,
      sent,
      'order.created of Tapchan 3',
    );

    // 3. Waiter 1 adds a round, then sends it: the other phone and the kitchen hear both
    sent = DateTime.now();
    first = await waiter1.pos.orders.addRound(
      first.uuid,
      version: first.version,
      items: menu.inputs(<Dish>[Dish.of('Achichuk', 2), Dish.of('Osh', 1)]),
    );
    await everyoneElseHears(
      waiter1,
      (PosEvent e) =>
          e is PosOrderItemsChanged &&
          e.orderUuid == first.uuid &&
          e.change == 'round_added',
      sent,
      'round_added',
    );
    sent = DateTime.now();
    first = await waiter1.pos.orders.send(first.uuid, version: first.version);
    final PosEvent sentEvent = await hears(
      waiter2,
      (PosEvent e) =>
          e is PosOrderItemsChanged &&
          e.orderUuid == first.uuid &&
          e.change == 'sent',
      sent,
      'sent',
    );
    expect((sentEvent as PosOrderItemsChanged).itemIds, isNotEmpty);
    expect(sentEvent.tableId, tableId('Stol 2'));
    await hears(
      kds,
      (PosEvent e) =>
          e is PosOrderEvent &&
          e.orderUuid == first.uuid &&
          e.order.status == 'preparing',
      sent,
      'order.status_changed preparing',
    );

    // 4. The cook marks the osh ready: both waiters and the till hear it from the kitchen
    first = await waiter1.pos.orders.get(first.uuid);
    final PosOrderItem osh = first.items.firstWhere(
      (PosOrderItem i) => i.productName == 'Osh',
    );
    final KitchenTicket ticket = (await kds.client.kitchen.tickets()).tickets
        .firstWhere((t) => t.uuid == first.uuid);
    sent = DateTime.now();
    await kds.client.kitchen.bumpLine(ticket.uuid, osh.id);
    for (final Terminal t in <Terminal>[waiter1, waiter2, till]) {
      final PosKitchenItemChanged ready = await hears(
        t,
        (PosEvent e) =>
            e is PosKitchenItemChanged &&
            e.itemId == osh.id &&
            e.kitchenStatus == 'ready',
        sent,
        'kitchen.item_status_changed ready',
      ) as PosKitchenItemChanged;
      expect(ready.orderUuid, first.uuid);
      expect(ready.itemsReady, greaterThanOrEqualTo(1));
      expect(ready.itemsTotal, greaterThanOrEqualTo(ready.itemsReady!));
    }

    // 5. Waiter 1 serves it
    sent = DateTime.now();
    first = await waiter1.pos.orders.markServed(
      first.uuid,
      osh.id,
      version: first.version,
    );
    await everyoneElseHears(
      waiter1,
      (PosEvent e) =>
          e is PosKitchenItemChanged &&
          e.itemId == osh.id &&
          e.kitchenStatus == 'served',
      sent,
      'served',
    );

    // 6. Waiter 2 changes the guests: the choy-non lines follow, and everyone hears
    sent = DateTime.now();
    second = await waiter2.pos.orders.setGuests(
      second.uuid,
      version: second.version,
      guestsCount: 5,
    );
    await everyoneElseHears(
      waiter2,
      (PosEvent e) =>
          e is PosOrderItemsChanged &&
          e.orderUuid == second.uuid &&
          e.change == 'guests_changed',
      sent,
      'guests_changed',
    );
    // Tapchan: one choy and one non per guest (the courtyard's rule): 2 at opening, 3 more as a new round
    final Map<String, int> guestLines = <String, int>{};
    for (final PosOrderItem i in second.activeItems) {
      guestLines[i.productName] =
          (guestLines[i.productName] ?? 0) + i.quantity.wholePart;
    }
    expect(guestLines, <String, int>{'Choy': 5, 'Non': 5});
    expect(second.rounds, 2);

    // 7. Waiter 1 asks for the bill: the other waiter and the till hear it
    sent = DateTime.now();
    first = await waiter1.pos.orders.requestBill(
      first.uuid,
      version: first.version,
    );
    for (final Terminal t in <Terminal>[till, waiter2]) {
      final PosOrderBillRequested asked = await hears(
        t,
        (PosEvent e) => e is PosOrderBillRequested && e.orderUuid == first.uuid,
        sent,
        'order.bill_requested',
      ) as PosOrderBillRequested;
      expect(asked.requestedBy, w1.id);
      expect(asked.version, first.version);
    }
    floor = await waiter2.pos.floor.load();
    expect(floor.table(tableId('Stol 2'))!.billRequested, isTrue);

    // 8. Waiter 2 moves its party to another tapchan
    sent = DateTime.now();
    second = await waiter2.pos.orders.move(
      second.uuid,
      version: second.version,
      tableId: tableId('Tapchan 4'),
    );
    final PosEvent moved = await hears(
      waiter1,
      (PosEvent e) =>
          e is PosOrderItemsChanged &&
          e.orderUuid == second.uuid &&
          e.change == 'moved',
      sent,
      'moved',
    );
    expect((moved as PosOrderItemsChanged).tableId, tableId('Tapchan 4'));

    // 9. An unsent line is voided
    second = await waiter2.pos.orders.addRound(
      second.uuid,
      version: second.version,
      items: menu.inputs(<Dish>[Dish.of('Svejiy salat', 1)]),
    );
    sent = DateTime.now();
    second = await waiter2.pos.orders.voidItem(
      second.uuid,
      second.items.last.id,
      version: second.version,
    );
    await hears(
      waiter1,
      (PosEvent e) =>
          e is PosOrderItemsChanged &&
          e.orderUuid == second.uuid &&
          e.change == 'voided',
      sent,
      'voided',
    );

    // 10. Both parties leave: the orders are cancelled (the manager's PIN), the tables free up everywhere
    sent = DateTime.now();
    await cancel(waiter1, first);
    await everyoneElseHears(
      waiter1,
      (PosEvent e) =>
          e is PosOrderEvent &&
          e.orderUuid == first.uuid &&
          e.order.status == 'canceled',
      sent,
      'order.status_changed canceled',
    );
    await cancel(waiter2, second);
    await hears(
      waiter1,
      (PosEvent e) =>
          e is PosOrderEvent &&
          e.orderUuid == second.uuid &&
          e.order.status == 'canceled',
      sent,
      'order.status_changed canceled (2)',
    );

    // The events carry ids and states only: no prices, names or phone numbers
    for (final HeardEvent h in waiter2.log!.events) {
      final String body = jsonEncode(h.event.data);
      for (final String forbidden in <String>[
        'price',
        'phone',
        'customer',
        'amount',
      ]) {
        expect(
          body,
          isNot(contains(forbidden)),
          reason: '${h.event.name} carries "$forbidden"',
        );
      }
    }
    expect(waiter2.realtime!.state, PosLiveState.live);
    expect(refreshes, contains(PosRefreshReason.event));
  });

  test('a terminal that connects late learns the state from the API, not from missed events', () async {
    // The channel is not a log: a phone that joins now hears nothing of the past, and reloads the floor on subscribing
    final Terminal late = await Terminal.open(config, 'waiter2');
    addTearDown(late.close);
    await late.signIn('waiter2');
    final PosRealtime rt = PosRealtime.create(
      client: late.client,
      terminal: late.client.auth.terminal!,
      apiBaseUrl: config.apiUrl,
      appKey: config.reverbKey,
      reverbUrl: config.reverbUrl,
    )!;
    addTearDown(rt.dispose);
    final EventLog log = EventLog(rt);
    addTearDown(log.dispose);
    final Future<void> subscribed = rt.resubscribed.first.timeout(
      const Duration(seconds: 15),
    );
    rt.start();
    await subscribed;
    expect(rt.state, PosLiveState.live);
    expect(log.events, isEmpty);

    // From now on it hears: a change from another phone arrives
    final PosOrder order = await waiter1.pos.orders.open(
      channel: PosChannel.hall,
      tableId: tableId('Stol 3'),
      guestsCount: 2,
      waiterId: config.seed.staff('waiter1').id,
    );
    final DateTime sent = DateTime.now();
    await log.waitFor(
      (PosEvent e) =>
          e is PosOrderEvent && e.isCreated && e.orderUuid == order.uuid,
      timeout: deadline,
      what: 'order.created on the late terminal',
    );
    expect(DateTime.now().difference(sent), lessThan(deadline));
    await cancel(waiter1, order);
  });
}
