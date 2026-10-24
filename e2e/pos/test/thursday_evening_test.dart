/// A choyxona Thursday evening on the REAL backend (Laravel + Postgres + Reverb), driven only through `pos_data`, the
/// way the cashier, waiter and kitchen apps drive it: a shift opens; a party booked in advance leaves a pre-order and a
/// cash deposit (ADVANCE receipt) and arrives; ten rooms (and a tapchan and a table in the courtyard) are served at
/// once by two waiters and the cashier, four rounds or more each, the cook marking lines ready, the waiters serving
/// them; bills are asked for, split (single, equal, by items with kg shares) and paid with every tender (cash with
/// change, card, QR, the deposit, nasiya with the manager's approval); every SALE receipt is checked line by line;
/// the nasiya is repaid (CREDIT receipts), one payment is refunded (REFUND receipt), the X report is checked figure by
/// figure and the shift is closed (Z) with a banknote count and no difference.
@Timeout(Duration(minutes: 8))
library;

import 'dart:async';
import 'dart:io';

import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:test/test.dart';

import 'support/evening.dart';
import 'support/kitchen.dart';
import 'support/menu.dart';
import 'support/plans.dart';
import 'support/pricing.dart';
import 'support/stack.dart';

Map<String, Object?> asMap(Object? value) =>
    (value! as Map<Object?, Object?>).cast<String, Object?>();

List<Map<String, Object?>> asList(Object? value) => <Map<String, Object?>>[
  for (final Object? e in value! as List<Object?>) asMap(e),
];

PosMoney money(Object? value) => PosMoney.parse(value);

/// The report's `by_tender` row of a tender.
PosMoney tenderAmount(Map<String, Object?> report, String tender) => money(
  asList(report['by_tender'])
      .firstWhere((Map<String, Object?> r) => r['tender'] == tender)['amount'],
);

void main() {
  late StackConfig config;
  late Terminal till;
  late Terminal waiter1;
  late Terminal waiter2;
  late Terminal kds;
  late Menu menu;
  late PosFloor floor;
  late CookWorker cook;
  late FloorWork work;
  final Timings timings = Timings();
  final SimClock clock = SimClock();
  late StepLog step;
  late List<OrderRun> runs;
  late Staff manager;

  late PosShift shift;
  late PosBooking booking;
  late PosArrival arrival;
  late PosBooking otherBooking;
  late PosBookingPayment otherDeposit;
  late String accountUuid;
  late PosQuantity kazanBefore;

  // The money the evening moves, written down as it happens and checked against the X report at the end
  PosMoney cashRefunded = PosMoney.zero;
  PosMoney depositCash = PosMoney.zero;
  PosMoney depositRefundedCash = PosMoney.zero;
  var depositsTaken = 0;
  final List<PosMoney> repayments = <PosMoney>[];
  PosMoney repaymentsCash = PosMoney.zero;
  late PaidBill refundedFrom;
  late PosRefund refund;

  /// Pays with the manager's PIN when the till is asked for an approval.
  Future<T> approved<T>(
    Future<T> Function(String? token) action,
    String permission, {
    List<String>? asked,
  }) => runWithApproval<T>(
    action,
    approve: (String needed, PosException cause) async {
      expect(needed, permission, reason: 'the permission the server asks for');
      asked?.add(needed);
      return till.pos.approvals.request(
        permission: needed,
        userId: manager.id,
        pin: manager.pin!,
      );
    },
  );

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
    await Future.wait(<Future<EventLog>>[
      till.listen(),
      waiter1.listen(),
      waiter2.listen(),
      kds.listen(),
    ]);
    menu = Menu(
      await till.pos.catalog.load(
        PosChannel.hall,
        menuVersion: till.bootstrap.menuVersion,
      ),
    );
    floor = await till.pos.floor.load();
    cook = CookWorker(kds, cookingTime: const Duration(milliseconds: 30));
    step = StepLog(clock);
    work = FloorWork(
      menu: menu,
      cook: cook,
      step: step,
      clock: clock,
      timings: timings,
    );
    runs = <OrderRun>[
      for (final RoomPlan plan in thursdayRooms) OrderRun(plan),
    ];
    for (final OrderRun run in runs) {
      run.place = floor.tables.firstWhere(
        (FloorTable t) => t.name == run.table,
      );
    }
  });

  tearDownAll(() async {
    await cook.stop();
    await Future.wait(<Future<void>>[
      till.close(),
      waiter1.close(),
      waiter2.close(),
      kds.close(),
    ]);
    final StringBuffer out = StringBuffer(
      '\n[pos-e2e] Thursday evening timings\n',
    );
    timings.phases.forEach((String name, Duration d) {
      if (!name.startsWith('party ')) out.writeln('  $name: ${Timings.ms(d)}');
    });
    final List<Duration> rounds = List<Duration>.of(timings.serviceRounds)
      ..sort();
    if (rounds.isNotEmpty) {
      out.writeln(
        '  service rounds (add + send + kitchen + served): ${rounds.length}, '
        'median ${Timings.ms(Timings.percentile(rounds, 0.5))}, '
        'max ${Timings.ms(rounds.last)}',
      );
    }
    stdout.write(out);
  });

  group('Thursday evening', () {
    test('the cashier opens the shift with 500 000 in the drawer', () async {
      expect((await till.pos.shifts.current()).shift, isNull);

      // A waiter has no right to open a shift
      await expectLater(
        waiter1.pos.shifts.open(openingCash: const PosMoney.som(100000)),
        throwsA(isA<PosException>().having((e) => e.statusCode, 'status', 403)),
      );

      shift = await timings.phase(
        'shift open',
        () => till.pos.shifts.open(
          openingCash: const PosMoney.som(500000),
          note: 'Payshanba kechqurun',
        ),
      );
      expect(shift.isOpen, isTrue);
      expect(shift.openingCash, const PosMoney.som(500000));
      till.pos.session.updateShift(shift.toSummary());

      await expectLater(
        till.pos.shifts.open(openingCash: const PosMoney.som(1)),
        throwsA(
          isA<PosConflict>().having(
            (e) => e.reason,
            'reason',
            'shift_already_open',
          ),
        ),
      );

      // The waiters' phones hear it
      final HeardEvent heard = await waiter1.log!.waitFor(
        (PosEvent e) =>
            e is PosShiftChanged &&
            e.change == 'opened' &&
            e.shiftUuid == shift.uuid,
        what: 'shift.changed opened',
      );
      expect(
        (heard.event as PosShiftChanged).deviceUuid,
        till.client.auth.terminal!.device.uuid,
      );
      expect((await till.pos.shifts.current()).shift!.uuid, shift.uuid);
    });

    test('a booking with a pre-order and a cash deposit leaves an ADVANCE receipt', () async {
      final FloorTable room = floor.tables.firstWhere(
        (t) => t.name == 'Kabinka 1',
      );
      final DateTime startsAt = DateTime.now().toUtc().add(
        const Duration(minutes: 40),
      );
      booking = await till.pos.bookings.create(
        PosBookingInput(
          diningTableId: room.id,
          startsAt: startsAt,
          guestsPlanned: 4,
          eventType: 'ziyofat',
          contactName: 'Jamshid aka',
          contactPhone: '+998901112233',
          source: 'phone',
          confirm: true,
        ),
      );
      expect(booking.status, 'confirmed');

      // Two kg of osh, ready for the party's time
      final Dish osh = Dish.of('Osh', 2);
      booking = await till.pos.bookings.addItem(
        booking.uuid,
        productVariantUuid: menu.variant('Osh').uuid,
        quantity: osh.quantity,
        dueAt: startsAt,
      );
      expect(booking.items, hasLength(1));
      expect(booking.items.single.quantity, osh.quantity);
      expect(booking.items.single.unitPrice, const PosMoney.som(120000));

      // Another booking, in the same room, at the same time: refused
      await expectLater(
        till.pos.bookings.create(
          PosBookingInput(
            diningTableId: room.id,
            startsAt: startsAt.add(const Duration(minutes: 30)),
            contactName: 'Boshqa mijoz',
          ),
        ),
        throwsA(
          isA<PosConflict>().having(
            (e) => e.reason,
            'reason',
            'booking_conflict',
          ),
        ),
      );

      // 300 000 cash: a deposit, and the ADVANCE receipt with one "avans" line
      final String key = till.pos.http.newKey();
      final PosDepositResult deposit = await till.pos.bookings.takeDeposit(
        booking.uuid,
        amount: const PosMoney.som(300000),
        tender: 'cash',
        idempotencyKey: key,
      );
      depositCash += const PosMoney.som(300000);
      depositsTaken++;
      final PosFiscalReceiptDetail advance = deposit.fiscal.receipt!;
      expect(advance.receiptType, FiscalReceiptType.advance);
      expect(advance.status, 'issued');
      expect(advance.total, const PosMoney.som(300000));
      expect(advance.lines, hasLength(1));
      expect(
        advance.fiscalSign,
        isNull,
        reason: 'an advance receipt has no fiscal sign',
      );
      expect(advance.payments.single.form, 'cash');
      expect(deposit.booking.depositAvailable, const PosMoney.som(300000));

      // The same request again (a network retry): the same deposit, no second receipt
      final PosDepositResult again = await till.pos.bookings.takeDeposit(
        booking.uuid,
        amount: const PosMoney.som(300000),
        tender: 'cash',
        idempotencyKey: key,
      );
      expect(again.payment.uuid, deposit.payment.uuid);
      expect(again.booking.depositTotal, const PosMoney.som(300000));
      booking = again.booking;

      // The floor map shows the room as booked soon, with the party's name for the cashier
      floor = await till.pos.floor.load();
      final FloorTable shown = floor.table(room.id)!;
      expect(shown.statusWire, 'reserved_soon');
      expect(shown.reservation!.bookingUuid, booking.uuid);
      expect(shown.reservation!.contactName, 'Jamshid aka');
      // ... and the waiter's phone hears the booking
      await waiter2.log!.waitFor(
        (PosEvent e) => e is PosBookingChanged && e.bookingUuid == booking.uuid,
        what: 'booking.changed',
      );

      // A second party, tomorrow: its 200 000 cash deposit is taken now and goes back out of the drawer at the end
      final FloorTable vip = floor.tables.firstWhere(
        (t) => t.name == 'Kabinka 10',
      );
      otherBooking = await till.pos.bookings.create(
        PosBookingInput(
          diningTableId: vip.id,
          startsAt: startsAt.add(const Duration(days: 1)),
          guestsPlanned: 12,
          contactName: 'Botir aka',
          contactPhone: '+998902223344',
          confirm: true,
        ),
      );
      final PosDepositResult second = await till.pos.bookings.takeDeposit(
        otherBooking.uuid,
        amount: const PosMoney.som(200000),
        tender: 'cash',
      );
      depositCash += const PosMoney.som(200000);
      depositsTaken++;
      otherDeposit = second.payment;
      expect(second.fiscal.receipt!.receiptType, FiscalReceiptType.advance);
    });

    test('ten rooms and the courtyard are served at once by two waiters and the cashier', () async {
      // Other test files may have used some osh from the kazan: count from what it holds now
      await till.pos.catalog.refreshAvailability(
        storeId: config.seed.storeId,
        branchId: config.seed.branchId,
      );
      kazanBefore = till.pos.catalog
          .cached(PosChannel.hall)!
          .availability
          .batches
          .single
          .quantityLeft;
      expect(kazanBefore.milli, greaterThanOrEqualTo(30000));
      // 19:05, "Keldi": the booked party arrives (the order opens with the pre-order on hold and the choy-non lines)
      await clock.advance(65);
      arrival = await till.pos.bookings.arrive(booking.uuid, guests: 4);
      expect(arrival.booking.status, 'arrived');
      await expectLater(
        till.pos.bookings.arrive(booking.uuid),
        throwsA(
          isA<PosConflict>().having(
            (e) => e.reason,
            'reason',
            'booking_already_arrived',
          ),
        ),
      );
      final PosOrder arrived = await till.pos.orders.get(arrival.orderUuid);
      expect(arrived.bookingUuid, booking.uuid);
      expect(arrived.depositAvailable, const PosMoney.som(300000));
      expect(arrived.guestsCount, 4);
      expect(arrived.table!.name, 'Kabinka 1');
      expect(
        arrived.waiter,
        isNull,
        reason: 'the waiter takes the order over at the first change',
      );
      final PosOrderItem preOrder = arrived.items.firstWhere(
        (i) => i.productName == 'Osh',
      );
      expect(preOrder.fireMode, PosFireMode.hold);
      expect(preOrder.quantityLabel, contains('2'));
      expect(preOrder.quantityLabel, contains('kg'));
      expect(preOrder.kitchenStatus, PosKitchenStatus.pending);

      cook.start();
      await timings.phase('service (12 parties, 4+ rounds)', () async {
        final List<Future<void>> parties = <Future<void>>[];
        for (final OrderRun run in runs) {
          final int index = runs.indexOf(run);
          final Terminal who = switch (run.plan.actor) {
            'waiter1' => waiter1,
            'waiter2' => waiter2,
            _ => till,
          };
          parties.add(
            Future<void>.delayed(Duration(milliseconds: 25 * index)).then(
              (_) => work.serve(
                run,
                who,
                arrivedOrder: run.plan.booking ? arrival.orderUuid : null,
              ),
            ),
          );
        }
        await Future.wait(parties);
      });
      await cook.stop();
      printOnFailure(
        'the cook marked ${cook.bumps} lines ready; refused: ${cook.refused}',
      );
      // Every line of a station with a display went through the cook's hands (a sent mistake may have been bumped too)
      final int displayLines = <int>[
        for (final OrderRun run in runs)
          run.ordered
              .where((Dish d) => d.name != 'Choy' && d.name != 'Non')
              .length,
      ].fold(0, (int a, int b) => a + b);
      expect(cook.bumps, inInclusiveRange(displayLines, displayLines + 1));

      for (final OrderRun run in runs) {
        expect(run.rounds, greaterThanOrEqualTo(4), reason: run.table);
      }
    });

    test('every order adds up to the tiyin: lines, auto choy-non, room fee, minimum, service charge', () async {
      var checked = 0;
      for (final OrderRun run in runs) {
        final PosOrder order = await till.pos.orders.get(run.order.uuid);
        run.order = order;
        final RoomPlan plan = run.plan;

        // The day type comes from the server; the test checks it against the calendar
        run.businessDate = order.charges.businessDate!;
        run.weekend = order.charges.dayType == 'weekend';
        expect(
          run.weekend,
          Rules.isWeekend(run.businessDate),
          reason: '${run.businessDate} day type',
        );
        expect(order.charges.dayType, anyOf('weekday', 'weekend'));

        // Lines: what was ordered, by dish and modifiers, with the quantities
        final Map<String, int> want = <String, int>{};
        for (final Dish d in run.ordered) {
          final String key =
              '${d.name}|${(List<String>.of(d.modifiers)..sort()).join('+')}';
          want[key] = (want[key] ?? 0) + d.quantity.milli;
        }
        final Map<String, int> got = <String, int>{};
        for (final PosOrderItem i in order.activeItems) {
          final String key =
              '${i.productName}|${(<String>[for (final m in i.modifiers) m.name]..sort()).join('+')}';
          got[key] = (got[key] ?? 0) + i.quantity.milli;
          // Every line costs what the menu says: (price + modifiers) x quantity
          final Dish asDish = Dish(
            i.productName,
            i.quantity,
            modifiers: <String>[for (final m in i.modifiers) m.name],
          );
          expect(
            i.totalPrice,
            menu.price(asDish),
            reason: '${run.table} ${i.displayName}',
          );
          expect(
            i.kitchenStatus,
            PosKitchenStatus.served,
            reason: '${run.table} ${i.displayName} was served',
          );
          expect(i.sentAt, isNotNull, reason: '${i.displayName} was sent');
        }
        expect(got, want, reason: '${run.table}: lines');
        expect(
          order.subtotal,
          menu.total(run.ordered),
          reason: '${run.table}: subtotal',
        );
        expect(order.rounds, run.rounds, reason: '${run.table}: rounds');

        // The charges, from the hall's rules computed here
        final ExpectedCharges expected = ExpectedCharges(
          hall: plan.hall,
          table: plan.table,
          kind: run.place.kind,
          guests: plan.guests,
          weekend: run.weekend,
          food: menu.total(run.ordered),
        );
        run.expected = expected;
        expect(
          order.serviceChargeAmount,
          expected.serviceCharge,
          reason: '${run.table}: service charge',
        );
        expect(
          order.placeFeeAmount,
          expected.placeFee,
          reason: '${run.table}: place fee',
        );
        expect(
          order.roomFeeAmount,
          expected.roomFee,
          reason: '${run.table}: room fee',
        );
        expect(
          order.minSpendTopupAmount,
          expected.topup,
          reason: '${run.table}: minimum spend top-up',
        );
        expect(order.totalPrice, expected.total, reason: '${run.table}: total');
        expect(order.charges.total, expected.total);
        expect(order.guestsCount, plan.guests);
        expect(order.hall!.name, plan.hall);
        expect(order.table!.name, plan.table);
        expect(order.channel, 'pos_hall');
        // The waiter: the waiter who served it; the cashier's own rooms have none
        expect(order.waiter?.id, switch (plan.actor) {
          'cashier' => isNull,
          final String key => config.seed.staff(key).id,
        }, reason: '${run.table}: waiter');

        // The server routed the lines to the stations
        for (final PosOrderItem i in order.activeItems) {
          final String station = till.bootstrap.stations
              .firstWhere((s) => s.id == i.stationId)
              .name;
          final String expectedStation = switch (i.productName) {
            'Osh' => 'Osh',
            'Shashlik' => 'Mangal',
            'Choy' || 'Non' => 'Choy',
            _ => 'Oshxona',
          };
          expect(
            station,
            expectedStation,
            reason: '${run.table} ${i.productName}',
          );
        }
        checked++;
      }
      expect(checked, 12);

      // The three kinds of minimum: a top-up where the order is small, none where it clears the minimum
      OrderRun by(String table) => runs.firstWhere((r) => r.table == table);
      expect(by('Kabinka 3').expected.topup.isPositive, isTrue);
      expect(by('Kabinka 7').expected.topup, PosMoney.zero);
      expect(by('Tapchan 1').expected.placeFee, const PosMoney.som(25000));
      expect(
        by('Kabinka 10').expected.roomFee,
        PosMoney.som(by('Kabinka 10').weekend ? 600000 : 500000),
      );

      // The kazan: what it held before the evening, minus every kilogram of osh that was ordered
      var osh = 0;
      for (final OrderRun run in runs) {
        for (final Dish d in run.ordered.where((d) => d.name == 'Osh')) {
          osh += d.quantity.milli;
        }
      }
      await till.pos.catalog.refreshAvailability(
        storeId: config.seed.storeId,
        branchId: config.seed.branchId,
      );
      final PosBatch batch = till.pos.catalog
          .cached(PosChannel.hall)!
          .availability
          .batches
          .single;
      expect(batch.quantityLeft, PosQuantity.milli(kazanBefore.milli - osh));
    });

    test('the waiters\' bill requests reach the till at once', () async {
      for (final OrderRun run in runs.where((r) => r.plan.byWaiter)) {
        final Staff waiter = config.seed.staff(run.plan.actor);
        final HeardEvent heard = await till.log!.waitFor(
          (PosEvent e) =>
              e is PosOrderBillRequested && e.orderUuid == run.order.uuid,
          what: 'order.bill_requested for ${run.table}',
        );
        final PosOrderBillRequested event =
            heard.event as PosOrderBillRequested;
        expect(event.requestedBy, waiter.id, reason: run.table);
        expect(event.tableId, run.place.id, reason: run.table);
      }

      // The floor map shows the parties that asked, and not the ones the cashier opened himself
      floor = await till.pos.floor.load();
      for (final OrderRun run in runs) {
        final FloorTable table = floor.table(run.place.id)!;
        expect(
          table.billRequested,
          run.plan.byWaiter,
          reason: '${run.table} bill_requested',
        );
        expect(table.orders.single.uuid, run.order.uuid);
        expect(
          table.orders.single.unsentCount,
          0,
          reason: '${run.table}: nothing left unsent',
        );
        expect(
          table.orders.single.readyCount,
          0,
          reason: '${run.table}: everything served',
        );
      }
      final List<PosOpenOrder> queue = await till.pos.orders.openOrders();
      expect(queue.where((o) => o.isPos), hasLength(12));
      expect(queue.where((o) => o.billRequested), hasLength(10));
    });

    test('every terminal heard every party: created, rounds, sends, kitchen', () async {
      for (final Terminal terminal in <Terminal>[till, waiter1, waiter2, kds]) {
        for (final OrderRun run in runs) {
          final String uuid = run.order.uuid;
          await terminal.log!.waitFor(
            (PosEvent e) =>
                e is PosOrderEvent && e.isCreated && e.orderUuid == uuid,
            what: '${terminal.label} order.created ${run.table}',
          );
          // round_added once per round after the first (the first is the opening); the kitchen's marks per dish line
          final int addedRounds = run.rounds - 1;
          await eventually<bool>(
            () async {
              final int rounds = terminal.log!
                  .where(
                    (PosEvent e) =>
                        e is PosOrderItemsChanged &&
                        e.orderUuid == uuid &&
                        e.change == 'round_added',
                  )
                  .length;
              return rounds >= addedRounds ? true : null;
            },
            what:
                '${terminal.label}: $addedRounds round_added events for ${run.table}',
            timeout: const Duration(seconds: 10),
          );
          final int served = terminal.log!
              .where(
                (PosEvent e) =>
                    e is PosKitchenItemChanged &&
                    e.orderUuid == uuid &&
                    e.kitchenStatus == 'served',
              )
              .length;
          expect(
            served,
            greaterThanOrEqualTo(run.ordered.length),
            reason: '${terminal.label}: served events of ${run.table}',
          );
        }
      }
    });

    test('bills are split, printed and paid with every tender; each SALE receipt is exact', () async {
      final List<String> asked = <String>[];
      accountUuid = config.seed.accountUuid;
      final FiscalReceiptRepository receipts = FiscalReceiptRepository(
        till.pos.http,
      );

      await timings.phase('billing (12 orders)', () async {
        for (final OrderRun run in runs) {
          final PaymentPlan plan = thursdayPayments[run.table]!;
          final PosOrder order = await till.pos.orders.get(run.order.uuid);
          expect(order.billLocked, isFalse);
          final PosSplit split = switch (plan.split) {
            'equal' => PosSplit.equal(plan.tenders.length),
            'items' => PosSplit.items(splitByItems(order)),
            _ => const PosSplit.single(),
          };
          final PosBilling billing = await till.pos.billing.createBills(
            order.uuid,
            version: order.version,
            split: split,
          );
          expect(
            billing.bills,
            hasLength(plan.tenders.length),
            reason: '${run.table}: bills',
          );
          // The bills add up to the order, to the tiyin
          expect(
            PosMoney.sum(billing.bills.map((b) => b.total)),
            order.totalPrice,
            reason: '${run.table}: bills sum',
          );
          expect(billing.order.unbilledAmount, PosMoney.zero);
          if (plan.split == 'equal') {
            final int low = billing.bills
                .map((b) => b.total.tiyin)
                .reduce((a, b) => a < b ? a : b);
            final int high = billing.bills
                .map((b) => b.total.tiyin)
                .reduce((a, b) => a > b ? a : b);
            expect(
              high - low,
              lessThan(plan.tenders.length),
              reason: '${run.table}: an equal split differs by tiyin only',
            );
          }
          if (plan.split == 'items') {
            // The kg shares: 1.5 kg of osh went 0.5 kg to one bill, 1 kg to the other
            final List<String> shares = <String>[
              for (final PosBill b in billing.bills)
                for (final PosBillItem item in b.items)
                  if (item.productName == 'Osh') item.quantity.format(),
            ];
            expect(shares..sort(), <String>['0.5', '1']);
          }

          for (var i = 0; i < billing.bills.length; i++) {
            final PosBill bill = billing.bills[i];
            final PosBillResult printed = await till.pos.billing.printBill(
              bill.uuid,
            );
            expect(printed.bill.printedCount, 1);
            expect(
              printed.order.billLockedAt,
              isNotNull,
              reason: 'the first print locks the order',
            );

            final List<PosTenderInput> tenders = tendersFor(
              plan.tenders[i],
              bill.total,
              run,
              i,
              accountUuid,
            );
            expect(
              PosMoney.sum(tenders.map((t) => t.amount)),
              bill.total,
              reason: '${run.table} bill ${i + 1}: tenders cover the bill',
            );

            Future<PosBillResult> pay(String? key, String? token) =>
                till.pos.billing.pay(
                  bill.uuid,
                  tenders: tenders,
                  approvalToken: token,
                  idempotencyKey: key,
                );
            final String key = till.pos.http.newKey();
            final PosBillResult result;
            if (tenders.any((t) => t.tender == PosTender.account)) {
              // Nasiya: the cashier has no right to it; the manager types his PIN
              await expectLater(
                pay(null, null),
                throwsA(
                  isA<PosApprovalRequired>().having(
                    (e) => e.permission,
                    'permission',
                    PosPermissions.customerAccountsManage,
                  ),
                ),
              );
              result = await approved<PosBillResult>(
                (String? token) =>
                    pay(token == null ? key : till.pos.http.newKey(), token),
                PosPermissions.customerAccountsManage,
                asked: asked,
              );
            } else {
              result = await pay(key, null);
              if (run.table == 'Kabinka 2') {
                // The network drops the answer and the till sends the same payment again: no second payment
                final PosBillResult replay = await pay(key, null);
                expect(
                  replay.bill.payments.map((p) => p.uuid),
                  result.bill.payments.map((p) => p.uuid),
                );
                expect(replay.bill.paidAmount, bill.total);
              }
            }
            final PosBill paid = result.bill;
            expect(paid.status, 'paid', reason: '${run.table} bill ${i + 1}');
            expect(paid.paidAmount, bill.total);
            expect(paid.dueAmount, PosMoney.zero);
            expect(
              paid.fiscal.status,
              'issued',
              reason: '${run.table}: the sale receipt is issued',
            );
            expect(
              paid.payments,
              hasLength(tenders.length),
              reason: '${run.table}: payments',
            );

            // Cash with change: the guest handed over more and got the difference back
            for (final PosPayment p in paid.payments.where(
              (p) => p.tender == 'cash',
            )) {
              final PosTenderInput given = tenders.firstWhere(
                (t) => t.tender == PosTender.cash,
              );
              final PosMoney? handed = PosMoney.tryParse(
                given.toJson()['tendered_amount'],
              );
              expect(p.amount, given.amount);
              if (handed != null) {
                expect(p.tenderedAmount, handed);
                expect(
                  p.changeAmount,
                  handed - given.amount,
                  reason: '${run.table}: change',
                );
              }
            }

            // The receipt, from the answer and read again by its uuid
            final PosFiscalReceiptDetail receipt = paid.fiscal.receipt!;
            final PosFiscalReceiptDetail read = await receipts.get(
              paid.fiscal.receiptUuid!,
            );
            expect(read.uuid, receipt.uuid);
            expect(read.total, receipt.total);
            expect(read.fiscalSign, receipt.fiscalSign);
            checkSaleReceipt(read, bill, tenders, run);
            run.paid.add(PaidBill(paid, tenders, read));

            // After the last bill the order is closed and the room is free
            if (i == billing.bills.length - 1) {
              expect(
                result.order.status,
                'delivered',
                reason: '${run.table} closed',
              );
              expect(result.order.paymentStatus, 'paid');
              expect(result.order.closedAt, isNotNull);
            } else {
              expect(
                result.order.closedAt,
                isNull,
                reason: '${run.table}: not closed before its last bill',
              );
            }
          }
          step(
            'cashier',
            '${run.table}: ${billing.bills.length} bill(s) paid, ${order.totalPrice.format()}',
          );
        }
      });
      expect(asked, <String>[PosPermissions.customerAccountsManage]);

      // Every receipt of the evening: the totals agree to the tiyin with the orders and the bills
      final List<PaidBill> all = <PaidBill>[for (final r in runs) ...r.paid];
      final PosMoney receiptTotal = PosMoney.sum(
        all.map((p) => p.receipt.total),
      );
      final PosMoney paymentsTotal = PosMoney.sum(<PosMoney>[
        for (final p in all)
          for (final part in p.receipt.payments) part.amount,
      ]);
      final PosMoney ordersTotal = PosMoney.sum(
        runs.map((r) => r.expected.total),
      );
      expect(receiptTotal, ordersTotal);
      expect(paymentsTotal, receiptTotal);
      expect(
        all.map((p) => p.receipt.uuid).toSet(),
        hasLength(all.length),
        reason: 'one receipt per bill',
      );
      expect(
        all,
        hasLength(
          runs.fold<int>(
            0,
            (n, r) => n + thursdayPayments[r.table]!.tenders.length,
          ),
        ),
      );
    });

    test(
      'the orders are closed, the rooms are free and the booking is completed',
      () async {
        final List<PosOpenOrder> queue = await till.pos.orders.openOrders();
        expect(queue.where((o) => o.isPos), isEmpty);
        floor = await till.pos.floor.load();
        expect(floor.orders, isEmpty);
        for (final OrderRun run in runs) {
          final FloorTable table = floor.table(run.place.id)!;
          expect(table.orders, isEmpty, reason: run.table);
          expect(table.statusWire, 'free', reason: run.table);
        }
        final List<PosOpenOrder> closed = await till.pos.orders.closedOrders();
        expect(
          closed.map((o) => o.uuid).toSet(),
          runs.map((r) => r.order.uuid).toSet(),
        );

        booking = await till.pos.bookings.get(booking.uuid);
        expect(booking.status, 'completed');
        expect(booking.payments.single.status, 'applied');
        expect(
          booking.payments.single.appliedAmount,
          const PosMoney.som(300000),
        );
        expect(booking.depositAvailable, PosMoney.zero);

        // The waiters' phones heard the rooms free up
        for (final Terminal w in <Terminal>[waiter1, waiter2]) {
          for (final OrderRun run in runs) {
            await w.log!.waitFor(
              (PosEvent e) =>
                  e is PosOrderEvent &&
                  e.orderUuid == run.order.uuid &&
                  e.order.status == 'delivered',
              what: '${w.label}: ${run.table} closed',
            );
          }
        }
      },
    );

    test('the nasiya is repaid in two parts and each repayment leaves a CREDIT receipt', () async {
      final PosMoney owed = runs
          .expand((r) => r.paid)
          .expand((p) => p.tenders)
          .where((t) => t.tender == PosTender.account)
          .fold(PosMoney.zero, (PosMoney a, PosTenderInput t) => a + t.amount);
      expect(owed.isPositive, isTrue);
      var account = await till.pos.accounts.get(accountUuid);
      expect(account.account.balance, owed);
      expect((await till.pos.accounts.debtors()).totalDebt, owed);
      expect(account.entries.single.type, 'charge');

      // First by cash (a week later, the same shift for the test), the rest by card
      final PosMoney first = floorToHundred(PosMoney(owed.tiyin ~/ 2));
      final PosMoney second = owed - first;
      for (final (PosMoney amount, String tender) in <(PosMoney, String)>[
        (first, 'cash'),
        (second, 'card'),
      ]) {
        account = await till.pos.accounts.repay(
          accountUuid,
          amount: amount,
          tender: tender,
          cardType: tender == 'card' ? 'humo' : null,
          transactionId: tender == 'card' ? 'RRN-NASIYA-1' : null,
          note: 'Nasiya to\'lovi',
        );
        repayments.add(amount);
        if (tender == 'cash') repaymentsCash += amount;
        final PosFiscalReceiptDetail credit = account.fiscal!.receipt!;
        expect(credit.receiptType, FiscalReceiptType.credit);
        expect(credit.status, 'issued');
        expect(credit.total, amount);
        expect(credit.lines, hasLength(1));
        expect(credit.lines.single.amount, amount);
        expect(
          credit.fiscalSign,
          isNull,
          reason: 'a credit receipt has no fiscal sign',
        );
        expect(credit.qrUrl, isNull);
        expect(credit.payments.single.form, tender);
        expect(PosMoney.sum(credit.payments.map((p) => p.amount)), amount);
      }
      expect(account.account.balance, PosMoney.zero);
      expect((await till.pos.accounts.debtors()).count, 0);
      // The repayment's answer carries its own entry; the account's list has the sale and both repayments
      final PosAccountWithEntries full = await till.pos.accounts.get(
        accountUuid,
      );
      expect(full.entries.map((e) => e.type), <String>[
        'repayment',
        'repayment',
        'charge',
      ]);
      expect(full.entries.first.balanceAfter, PosMoney.zero);
      // More than is owed: refused
      await expectLater(
        till.pos.accounts.repay(
          accountUuid,
          amount: const PosMoney.som(1),
          tender: 'cash',
        ),
        throwsA(isA<PosException>().having((e) => e.statusCode, 'status', 422)),
      );
    });

    test('one cash payment is refunded with the manager\'s approval: a REFUND receipt', () async {
      refundedFrom = runs.firstWhere((r) => r.table == 'Kabinka 2').paid.single;
      final PosPayment cash = refundedFrom.bill.payments.firstWhere(
        (p) => p.tender == 'cash',
      );
      const PosMoney amount = PosMoney.som(25000);

      // More than was paid: refused
      await expectLater(
        approved<PosRefund>(
          (String? token) => till.pos.billing.refund(
            paymentUuid: cash.uuid,
            amount: cash.amount + const PosMoney.som(1),
            reason: 'Xato',
            approvalToken: token,
          ),
          PosPermissions.refund,
        ),
        throwsA(
          isA<PosRejected>().having(
            (e) => e.reason,
            'reason',
            'refund_exceeds_payment',
          ),
        ),
      );

      final List<String> asked = <String>[];
      refund = await approved<PosRefund>(
        (String? token) => till.pos.billing.refund(
          paymentUuid: cash.uuid,
          amount: amount,
          reason: 'Mijoz shikoyat qildi: shashlik sovuq',
          approvalToken: token,
        ),
        PosPermissions.refund,
        asked: asked,
      );
      expect(asked, hasLength(1));
      cashRefunded += amount;
      expect(refund.amount, amount);
      expect(refund.method, 'cash');
      expect(refund.approvedBy!.id, manager.id);
      final PosFiscalReceiptDetail receipt = refund.fiscal.receipt!;
      expect(receipt.receiptType, FiscalReceiptType.refund);
      expect(receipt.status, 'issued');
      expect(receipt.total, amount);
      expect(
        receipt.originalReceiptUuid,
        refundedFrom.receipt.uuid,
        reason: 'linked to the sale receipt',
      );
      expect(PosMoney.sum(receipt.payments.map((p) => p.amount)), amount);
      expect(PosMoney.sum(receipt.lines.map((l) => l.amount)), amount);

      final PosBilling billing = await till.pos.billing.bills(
        refundedFrom.bill.orderUuid,
      );
      expect(billing.refunds.map((r) => r.uuid), <String>[refund.uuid]);
    });

    test('the second party cancels and its cash deposit goes back out of the drawer', () async {
      otherBooking = await till.pos.bookings.cancel(
        otherBooking.uuid,
        reason: 'Rejalar o\'zgardi',
      );
      expect(otherBooking.status, 'canceled');
      final PosDepositResult back = await approved<PosDepositResult>(
        (String? token) => till.pos.bookings.refundDeposit(
          otherBooking.uuid,
          otherDeposit.uuid,
          reason: 'Mijoz so\'radi',
          approvalToken: token,
        ),
        PosPermissions.refund,
      );
      expect(back.payment.status, 'refunded');
      depositRefundedCash += const PosMoney.som(200000);
    });

    test('the X report shows every figure of the evening', () async {
      final PosShiftWithReport x = await till.pos.shifts.xReport(shift.uuid);
      final Map<String, Object?> report = x.report!.raw;
      final List<PaidBill> all = <PaidBill>[for (final r in runs) ...r.paid];
      PosMoney tenderSum(PosTender t) => PosMoney.sum(<PosMoney>[
        for (final p in all)
          for (final i in p.tenders)
            if (i.tender == t) i.amount,
      ]);

      // Money by tender
      for (final PosTender t in PosTender.values) {
        expect(
          tenderAmount(report, t.wire),
          tenderSum(t),
          reason: 'by_tender ${t.wire}',
        );
      }
      final PosMoney received = PosMoney.sum(PosTender.values.map(tenderSum));
      final Map<String, Object?> sales = asMap(report['sales']);
      expect(money(sales['received']), received);
      expect(money(sales['refunded']), cashRefunded);
      expect(money(sales['net']), received - cashRefunded);
      expect(sales['bills'], all.length);
      expect(
        money(sales['bills_total']),
        PosMoney.sum(runs.map((r) => r.expected.total)),
      );
      expect(
        money(sales['subtotal']),
        PosMoney.sum(runs.map((r) => r.expected.subtotal)),
      );
      expect(
        money(sales['room_fee']),
        PosMoney.sum(runs.map((r) => r.expected.roomFee)),
      );
      expect(
        money(sales['service_charge']),
        PosMoney.sum(runs.map((r) => r.expected.serviceCharge)),
      );
      expect(
        money(sales['place_fee']),
        PosMoney.sum(runs.map((r) => r.expected.placeFee)),
      );
      expect(
        money(sales['min_spend_topup']),
        PosMoney.sum(runs.map((r) => r.expected.topup)),
      );

      // Card types
      final Map<String, PosMoney> cards = <String, PosMoney>{};
      for (final PaidBill p in all) {
        for (final PosTenderInput t in p.tenders.where(
          (t) => t.tender == PosTender.card,
        )) {
          final String type = p.bill.payments
              .firstWhere((x) => x.tender == 'card' && x.amount == t.amount)
              .cardType!;
          cards[type] = (cards[type] ?? PosMoney.zero) + t.amount;
        }
      }
      expect(<String, PosMoney>{
        for (final Map<String, Object?> c in asList(report['cards']))
          c['card_type']! as String: money(c['amount']),
      }, cards);

      // Waiters: what each one's orders brought and the service charge on them; the cashier's rooms have no waiter
      final Map<int?, Map<String, Object?>> byWaiter =
          <int?, Map<String, Object?>>{
            for (final Map<String, Object?> w in asList(report['by_waiter']))
              (w['waiter_id'] as num?)?.toInt(): w,
          };
      for (final String actor in <String>['waiter1', 'waiter2', 'cashier']) {
        final List<OrderRun> own = runs
            .where((r) => r.plan.actor == actor)
            .toList();
        final Map<String, Object?>? row =
            byWaiter[actor == 'cashier' ? null : config.seed.staff(actor).id];
        expect(row, isNotNull, reason: 'by_waiter $actor');
        expect(row!['orders'], own.length, reason: '$actor: orders');
        expect(
          row['bills'],
          own.fold<int>(0, (n, r) => n + r.paid.length),
          reason: '$actor: bills',
        );
        expect(
          money(row['amount']),
          PosMoney.sum(
            own
                .expand((r) => r.paid)
                .expand((p) => p.tenders)
                .map((t) => t.amount),
          ),
          reason: '$actor: amount',
        );
        expect(
          money(row['service_charge']),
          PosMoney.sum(own.map((r) => r.expected.serviceCharge)),
          reason: '$actor: service charge',
        );
      }
      expect(byWaiter, hasLength(3));

      // The channel; the voided lines (one unsent, one sent); one refund
      expect(asList(report['by_channel']).single['channel'], 'pos_hall');
      expect(asList(report['by_channel']).single['orders'], 12);
      final PosMoney voided = PosMoney.sum(runs.map((r) => r.voided));
      expect(
        asMap(report['voids'])['count'],
        runs.fold<int>(0, (n, r) => n + r.voidedLines),
      );
      expect(money(asMap(report['voids'])['amount']), voided);
      expect(asMap(report['refunds'])['count'], 1);
      expect(money(asMap(report['refunds'])['amount']), cashRefunded);
      expect(
        asList(asMap(report['refunds'])['by_method']).single['method'],
        'cash',
      );

      // Deposits: two taken in cash, one applied to a bill, one refunded from the drawer
      final Map<String, Object?> deposits = asMap(report['deposits']);
      expect(asMap(deposits['taken'])['count'], depositsTaken);
      expect(money(asMap(deposits['taken'])['amount']), depositCash);
      expect(money(asMap(deposits['taken'])['cash']), depositCash);
      expect(asMap(deposits['applied'])['count'], 1);
      expect(
        money(asMap(deposits['applied'])['amount']),
        const PosMoney.som(300000),
      );
      expect(asMap(deposits['refunded'])['count'], 1);
      expect(money(asMap(deposits['refunded'])['amount']), depositRefundedCash);
      expect(money(asMap(deposits['refunded'])['cash']), depositRefundedCash);

      // Nasiya: the sale on credit and both repayments (only the cash one went into the drawer)
      final Map<String, Object?> credit = asMap(report['credit']);
      expect(asMap(credit['sales'])['count'], 1);
      expect(
        money(asMap(credit['sales'])['amount']),
        tenderSum(PosTender.account),
      );
      expect(asMap(credit['repayments'])['count'], 2);
      expect(
        money(asMap(credit['repayments'])['amount']),
        PosMoney.sum(repayments),
      );
      expect(money(asMap(credit['repayments'])['cash']), repaymentsCash);

      // The cash the drawer must hold
      final Map<String, Object?> cash = asMap(report['cash']);
      final PosMoney expectedCash =
          const PosMoney.som(500000) +
          tenderSum(PosTender.cash) -
          cashRefunded +
          depositCash -
          depositRefundedCash +
          repaymentsCash;
      expect(money(cash['opening']), const PosMoney.som(500000));
      expect(money(cash['sales']), tenderSum(PosTender.cash));
      expect(money(cash['refunds']), cashRefunded);
      expect(money(cash['deposits']), depositCash);
      expect(money(cash['deposit_refunds']), depositRefundedCash);
      expect(money(cash['repayments']), repaymentsCash);
      expect(money(cash['expected']), expectedCash);
      expect(x.report!.expectedCash, expectedCash);
      expectedDrawer = expectedCash;

      // The current-shift endpoint tells the same
      final PosShiftWithReport current = await till.pos.shifts.current();
      expect(current.report!.expectedCash, expectedCash);
    });

    test(
      'Z: the cashier counts the banknotes, the drawer is right to the so\'m',
      () async {
        final Map<int, int> notes = <int, int>{};
        var rest = expectedDrawer.tiyin;
        expect(
          rest % 10000,
          0,
          reason: 'the expected cash is in whole 100 so\'m',
        );
        for (final int note in <int>[
          200000,
          100000,
          50000,
          20000,
          10000,
          5000,
          2000,
          1000,
          500,
          200,
          100,
        ]) {
          final int count = rest ~/ (note * 100);
          if (count > 0) notes[note] = count;
          rest -= count * note * 100;
        }
        expect(rest, 0);

        final PosShift z = await timings.phase(
          'Z close',
          () => till.pos.shifts.close(shift.uuid, countedBreakdown: notes),
        );
        expect(z.status, 'closed');
        expect(z.expectedCash, expectedDrawer);
        expect(z.countedCash, expectedDrawer);
        expect(z.difference, PosMoney.zero);
        expect(z.zNumber, 1);
        expect(z.countedBreakdown, notes);
        till.pos.session.updateShift(null);
        expect((await till.pos.shifts.current()).shift, isNull);
        expect(
          (await till.pos.shifts.list(status: 'closed')).map((s) => s.uuid),
          <String>[shift.uuid],
        );
        await waiter1.log!.waitFor(
          (PosEvent e) =>
              e is PosShiftChanged &&
              e.change == 'closed' &&
              e.shiftUuid == shift.uuid &&
              e.zNumber == 1,
          what: 'shift.changed closed',
        );

        // Every receipt of the evening was issued
        final List<PaidBill> all = <PaidBill>[for (final r in runs) ...r.paid];
        expect(all.every((p) => p.receipt.status == 'issued'), isTrue);
      },
    );
  });
}

late PosMoney expectedDrawer;

/// The tenders of a bill from the plan: a set amount, or the rest.
List<PosTenderInput> tendersFor(
  List<TenderPlan> plan,
  PosMoney due,
  OrderRun run,
  int billIndex,
  String accountUuid,
) {
  var remaining = due;
  final List<PosTenderInput> out = <PosTenderInput>[];
  for (var n = 0; n < plan.length; n++) {
    final TenderPlan t = plan[n];
    final bool last = n == plan.length - 1;
    // Cash that is not the last tender takes about 60% of what is left, in whole 100 so'm; the next tender pays the rest
    final PosMoney amount =
        t.fixed ??
        (last
            ? remaining
            : floorToHundred(PosMoney(remaining.tiyin * 6 ~/ 10)));
    remaining -= amount;
    final String ref = '${run.table}-${billIndex + 1}-${n + 1}';
    out.add(switch (t.kind) {
      PosTender.cash => PosTenderInput.cash(
        amount,
        tendered: t.change
            ? PosMoney(((amount.tiyin ~/ 5000000) + 1) * 5000000)
            : null,
      ),
      PosTender.card => PosTenderInput.card(
        amount,
        cardType: t.cardType!,
        transactionId: 'RRN-$ref',
      ),
      PosTender.qr => PosTenderInput.qr(amount, reference: 'QR-$ref'),
      PosTender.prepayment => PosTenderInput.prepayment(amount),
      PosTender.account => PosTenderInput.account(
        amount,
        accountUuid: accountUuid,
      ),
    });
  }
  return out;
}

/// Buyurtma qatorlarini ikki schyotga bo'ladi: kg qatorlar 0.5 / qolgani, donalilar teng, bitta donali qatorlar navbat bilan.
List<PosSplitBill> splitByItems(PosOrder order) {
  final Map<int, PosQuantity> a = <int, PosQuantity>{};
  final Map<int, PosQuantity> b = <int, PosQuantity>{};
  var flip = false;
  for (final PosOrderItem item in order.activeItems) {
    final PosQuantity q = item.quantity;
    if (item.unit.isMeasured) {
      a[item.id] = const PosQuantity.milli(500);
      b[item.id] = q - const PosQuantity.milli(500);
    } else if (q.wholePart >= 2) {
      final PosQuantity half = PosQuantity.whole(q.wholePart ~/ 2);
      a[item.id] = half;
      b[item.id] = q - half;
    } else {
      (flip ? b : a)[item.id] = q;
      flip = !flip;
    }
  }
  return <PosSplitBill>[PosSplitBill(items: a), PosSplitBill(items: b)];
}

/// One SALE receipt against the bill and the tenders that paid it.
void checkSaleReceipt(
  PosFiscalReceiptDetail receipt,
  PosBill bill,
  List<PosTenderInput> tenders,
  OrderRun run,
) {
  final String where = '${run.table} bill ${bill.number}';
  expect(receipt.receiptType, FiscalReceiptType.sale, reason: where);
  expect(receipt.status, 'issued', reason: where);
  expect(
    receipt.nonFiscal,
    isTrue,
    reason: '$where: the sandbox driver marks it "Bu fiskal chek emas"',
  );
  expect(receipt.fiscalSign, isNotNull, reason: '$where: fiscal sign');
  expect(receipt.receiptNumber, isNotNull, reason: where);
  expect(receipt.qrUrl, isNotNull, reason: where);
  expect(receipt.terminalId, isNotNull, reason: where);

  // The receipt is for the whole bill; its lines and its payments both add up to it
  expect(receipt.total, bill.total, reason: '$where: total');
  expect(
    PosMoney.sum(receipt.lines.map((l) => l.amount)),
    receipt.total,
    reason: '$where: lines sum',
  );
  expect(
    PosMoney.sum(receipt.payments.map((p) => p.amount)),
    receipt.total,
    reason: '$where: payments sum',
  );
  expect(
    receipt.vatTotal,
    PosMoney.sum(receipt.lines.map((l) => l.vatAmount)),
    reason: '$where: VAT sum',
  );
  for (final PosFiscalLine l in receipt.lines) {
    expect(
      l.mxikCode,
      matches(RegExp(r'^\d{17}$')),
      reason: '$where: ${l.name} has a 17-digit MXIK',
    );
    expect(l.vatRate, '12', reason: '$where: ${l.name} VAT rate');
    expect(
      l.vatAmount,
      l.amount.vatIncluded(12),
      reason: '$where: ${l.name} VAT is inside the price',
    );
  }

  // The service lines carry the codes the owner set
  final Map<String, String> serviceCodes = <String, String>{
    'Xona puli': '10202002001000001',
    'Xizmat haqi': '10202002005000001',
    'Joy puli': '10202002002000001',
    "Minimal summaga qo'shimcha": '10202002006000001',
  };
  for (final PosFiscalLine l in receipt.lines.where(
    (l) => serviceCodes.containsKey(l.name),
  )) {
    expect(l.mxikCode, serviceCodes[l.name], reason: '$where: ${l.name}');
  }
  final Set<String> names = receipt.lines.map((l) => l.name).toSet();
  if (run.expected.roomFee.isPositive) {
    expect(names, contains('Xona puli'), reason: where);
  }
  if (run.expected.serviceCharge.isPositive) {
    expect(names, contains('Xizmat haqi'), reason: where);
  }
  if (run.table == 'Tapchan 1' || run.table == 'Stol 1') {
    expect(names, contains('Joy puli'), reason: where);
  }
  if (run.expected.topup.isPositive) {
    expect(names, contains("Minimal summaga qo'shimcha"), reason: where);
  }
  if (run.table == 'Kabinka 6' &&
      bill.items.any((i) => i.productName == 'Osh')) {
    final PosFiscalLine osh = receipt.lines.firstWhere(
      (l) => l.name.startsWith('Osh'),
    );
    expect(osh.unit, 'kg (guruch)', reason: '$where: osh is sold by the kg');
  }

  // The payments: by form, with the deposit marked prepayment and nasiya marked credit
  PosMoney of(bool Function(PosFiscalPaymentPart p) test) =>
      PosMoney.sum(receipt.payments.where(test).map((p) => p.amount));
  PosMoney tendered(PosTender t) =>
      PosMoney.sum(tenders.where((i) => i.tender == t).map((i) => i.amount));
  expect(
    of((p) => p.prepayment),
    tendered(PosTender.prepayment),
    reason: '$where: prepayment part',
  );
  expect(
    of((p) => p.credit),
    tendered(PosTender.account),
    reason: '$where: credit part',
  );
  expect(
    of((p) => !p.prepayment && !p.credit && p.form == 'cash'),
    tendered(PosTender.cash),
    reason: '$where: cash part',
  );
  expect(
    of((p) => !p.prepayment && !p.credit && p.form == 'card'),
    tendered(PosTender.card),
    reason: '$where: card part',
  );
  expect(
    of((p) => !p.prepayment && !p.credit && p.form == 'qr'),
    tendered(PosTender.qr),
    reason: '$where: QR part',
  );
  expect(
    receipt.payments.where((p) => p.prepayment && p.credit),
    isEmpty,
    reason: where,
  );
  for (final PosFiscalPaymentPart p in receipt.payments.where(
    (p) => p.form == 'card' && !p.prepayment && !p.credit,
  )) {
    expect(p.cardType, isNotNull, reason: '$where: card type on the receipt');
    expect(
      p.transactionId,
      startsWith('RRN-'),
      reason: '$where: bank reference',
    );
  }
  final Set<String> forms = <String>{
    for (final PosTenderInput t in tenders)
      if (t.tender.isMoneyReceived) t.tender.wire,
  };
  expect(
    receipt.paymentForm,
    forms.length > 1
        ? 'mixed'
        : (forms.isEmpty ? receipt.paymentForm : forms.single),
    reason: '$where: payment form',
  );
}
