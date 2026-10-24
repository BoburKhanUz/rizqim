import 'dart:async';

import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';

import 'kitchen.dart';
import 'menu.dart';
import 'plans.dart';
import 'pricing.dart';
import 'stack.dart';

/// One bill that was paid: what the till asked for and what came back.
class PaidBill {
  PaidBill(this.bill, this.tenders, this.receipt);

  final PosBill bill;
  final List<PosTenderInput> tenders;
  final PosFiscalReceiptDetail receipt;
}

/// One party's order, from the plan to the paid receipts.
class OrderRun {
  OrderRun(this.plan);

  final RoomPlan plan;
  late PosOrder order;
  late FloorTable place;

  /// Every dish that is on the order and not voided (the planned rounds, the choy and non the server adds, extras).
  final List<Dish> ordered = <Dish>[];
  PosMoney voided = PosMoney.zero;
  int voidedLines = 0;
  int rounds = 0;
  final List<PaidBill> paid = <PaidBill>[];
  late ExpectedCharges expected;
  late String businessDate;
  late bool weekend;

  String get table => plan.table;
}

/// How long a thing took, for the report at the end.
class Timings {
  final Map<String, Duration> phases = <String, Duration>{};
  final List<Duration> serviceRounds = <Duration>[];

  Future<T> phase<T>(String name, Future<T> Function() body) async {
    final Stopwatch watch = Stopwatch()..start();
    try {
      return await body();
    } finally {
      phases[name] = watch.elapsed;
    }
  }

  static String ms(Duration d) => '${d.inMilliseconds} ms';

  static Duration percentile(List<Duration> sorted, double p) =>
      sorted[((sorted.length - 1) * p).round()];
}

/// The waiters' and the cashier's side of serving a party: open, rounds, send, kitchen, served, bill request. Every
/// method is what one app screen does with `pos_data`.
class FloorWork {
  FloorWork({
    required this.menu,
    required this.cook,
    required this.step,
    required this.clock,
    required this.timings,
  });

  final Menu menu;
  final CookWorker cook;
  final StepLog step;
  final SimClock clock;
  final Timings timings;

  Set<int> displayStations(Terminal who) => <int>{
    for (final PosStation s in who.bootstrap.stations)
      if (s.showOnKds) s.id,
  };

  /// Opens a table (or, for the booked party, takes over the order the cashier's "Keldi" made) and serves every round.
  Future<void> serve(OrderRun run, Terminal who, {String? arrivedOrder}) async {
    final RoomPlan plan = run.plan;
    final Stopwatch partyWatch = Stopwatch()..start();
    final Set<int> display = displayStations(who);
    run.ordered.addAll(<Dish>[...guestLines(plan.hall, plan.guests)]);

    PosOrder order;
    int next;
    if (arrivedOrder != null) {
      // The booked party: round 1 is the pre-order and the choy-non lines, made at "Keldi"
      order = await who.pos.orders.get(arrivedOrder);
      run.ordered.addAll(plan.rounds.first);
      next = 1;
      step(who.label, '${plan.table}: takes over the arrived party');
      order = await who.pos.orders.send(order.uuid, version: order.version);
      final List<int> held = <int>[
        for (final PosOrderItem i in order.items)
          if (i.kitchenStatus == PosKitchenStatus.pending &&
              i.fireMode == PosFireMode.hold)
            i.id,
      ];
      // The osh is fired by id: it was cooked for the booked time
      order = await who.pos.orders.send(
        order.uuid,
        version: order.version,
        itemIds: held,
      );
    } else {
      run.ordered.addAll(plan.rounds.first);
      order = await who.pos.orders.open(
        channel: PosChannel.hall,
        tableId: run.place.id,
        guestsCount: plan.guests,
        waiterId: plan.byWaiter ? who.staff!.id : null,
        externalId: 'e2e-${plan.table}',
        items: menu.inputs(plan.rounds.first),
        send: plan.rounds.first.any((Dish d) => d.fireMode != PosFireMode.hold),
      );
      next = 1;
      step(who.label, '${plan.table}: opened with ${plan.guests} guests');
    }
    run.rounds = 1;
    order = await _afterRound(who, order, display, run);

    for (; next < plan.rounds.length; next++) {
      await clock.advance(18 + (plan.table.hashCode.abs() % 7));
      final List<Dish> dishes = plan.rounds[next];
      final bool hasNow = dishes.any(
        (Dish d) => d.fireMode != PosFireMode.hold,
      );
      final Stopwatch roundWatch = Stopwatch()..start();
      order = await who.pos.orders.addRound(
        order.uuid,
        version: order.version,
        items: menu.inputs(dishes),
        send: hasNow,
      );
      run.ordered.addAll(dishes);
      run.rounds++;
      step(
        who.label,
        '${plan.table}: round ${next + 1} (${dishes.length} lines)',
      );
      order = await _afterRound(who, order, display, run);
      timings.serviceRounds.add(roundWatch.elapsed);
    }

    order = await _extras(who, order, display, run);

    // The party asks for the bill
    if (plan.byWaiter) {
      await clock.advance(25);
      order = await who.pos.orders.requestBill(
        order.uuid,
        version: order.version,
      );
      step(who.label, '${plan.table}: bill requested');
      run.order = order;
    } else {
      run.order = await who.pos.orders.get(order.uuid);
    }
    timings.phases['party ${plan.table}'] = partyWatch.elapsed;
  }

  /// Fires the lines held for later, then waits for the kitchen and serves everything that is ready.
  Future<PosOrder> _afterRound(
    Terminal who,
    PosOrder order,
    Set<int> display,
    OrderRun run,
  ) async {
    final List<int> held = <int>[
      for (final PosOrderItem i in order.items)
        if (i.kitchenStatus == PosKitchenStatus.pending &&
            i.fireMode == PosFireMode.hold)
          i.id,
    ];
    if (held.isNotEmpty) {
      await clock.advance(10);
      order = await who.pos.orders.send(
        order.uuid,
        version: order.version,
        itemIds: held,
      );
      step(who.label, '${run.plan.table}: fires ${held.length} held lines');
    }
    return serveReady(who, order, display);
  }

  /// Waits until the kitchen has marked every cooking line ready and marks every ready line served; tea and bread (a
  /// station without a display) are served from `sent` at once.
  Future<PosOrder> serveReady(
    Terminal who,
    PosOrder order,
    Set<int> display, {
    Duration timeout = const Duration(seconds: 40),
  }) async {
    final Stopwatch watch = Stopwatch()..start();
    while (true) {
      order = await who.pos.orders.get(order.uuid);
      var cooking = 0;
      for (final PosOrderItem item in order.items) {
        final bool atDisplay = display.contains(item.stationId);
        final bool serveNow =
            item.kitchenStatus == PosKitchenStatus.ready ||
            (item.kitchenStatus == PosKitchenStatus.sent && !atDisplay);
        if (serveNow) {
          order = await who.pos.orders.markServed(
            order.uuid,
            item.id,
            version: order.version,
          );
        } else if (item.kitchenStatus == PosKitchenStatus.sent) {
          cooking++;
        }
      }
      if (cooking == 0) return order;
      if (watch.elapsed > timeout) {
        throw TimeoutException(
          '${order.tableRef ?? order.uuid}: $cooking lines still cooking after ${timeout.inSeconds}s '
          '(the cook bumped ${cook.bumps}, refused ${cook.refused})',
        );
      }
      await Future<void>.delayed(const Duration(milliseconds: 120));
    }
  }

  /// The slips of the evening: a line added by mistake and voided before it is sent (Kabinka 8), a sent line voided
  /// with the manager's approval (Kabinka 9).
  Future<PosOrder> _extras(
    Terminal who,
    PosOrder order,
    Set<int> display,
    OrderRun run,
  ) async {
    if (run.table == 'Kabinka 8') {
      const Dish slip = Dish('Svejiy salat', PosQuantity.one);
      order = await who.pos.orders.addRound(
        order.uuid,
        version: order.version,
        items: menu.inputs(<Dish>[slip]),
      );
      run.rounds++;
      final PosOrderItem added = order.items.last;
      check(added.kitchenStatus == PosKitchenStatus.pending, 'an unsent line');
      order = await who.pos.orders.voidItem(
        order.uuid,
        added.id,
        version: order.version,
        reason: 'Xato bosildi',
      );
      run.voided += menu.price(slip);
      run.voidedLines++;
      step(who.label, 'Kabinka 8: an unsent line voided without an approval');
    }
    if (run.table == 'Kabinka 9') {
      const Dish mistake = Dish('Shashlik', PosQuantity.whole(2));
      order = await who.pos.orders.addRound(
        order.uuid,
        version: order.version,
        items: menu.inputs(<Dish>[mistake]),
        send: true,
      );
      run.rounds++;
      final PosOrderItem sent = order.items.last;
      var prompts = 0;
      order = await runWithApproval<PosOrder>(
        (String? token) => who.pos.orders.voidItem(
          order.uuid,
          sent.id,
          version: order.version,
          reason: 'Mijoz rad etdi',
          approvalToken: token,
        ),
        approve: (String permission, PosException cause) async {
          prompts++;
          check(permission == PosPermissions.ordersVoidItem, permission);
          final Staff manager = who.config.seed.staff('manager');
          return who.pos.approvals.request(
            permission: permission,
            userId: manager.id,
            pin: manager.pin!,
          );
        },
      );
      check(prompts == 1, 'the manager was asked exactly once, not $prompts');
      run.voided += menu.price(mistake);
      run.voidedLines++;
      step(who.label, 'Kabinka 9: a sent line voided with the manager\'s PIN');
    }
    return order;
  }
}

/// A failed expectation inside the helpers (a plain error with a message).
void check(bool ok, String what) {
  if (!ok) throw StateError('Expected: $what');
}
