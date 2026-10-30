/// P6-29 "Juma kechasi restoran" against the real stack: a restaurant (profile `restoran`, its own store in the seed,
/// `restoran`) on a Friday evening. Four waiters, a hostess, a cook and the cashier clock in; the till opens the shift.
/// The hostess books a party of ten (too soon is refused; the deposit of 50 000 per guest must be taken before the
/// booking confirms; the confirmation announces the large-group service charge), puts two walk-ins on the waitlist,
/// calls and seats them. Six tables are served: three-course orders fired course by course, each "course ready" heard
/// by the right waiter's phone; skewers ×10; a fish weighed after it is chosen (no bill until then); one table is split
/// in two by amount, half paid, then handed to another waiter with the manager's PIN and finished by the new one. The
/// bills: by guest, by amount, single; tips in cash and by card, change left as a tip, one tip moved to a waiter
/// personally by the manager, the rest in the pool. The internet goes at the till for one card payment with a tip, then
/// syncs. One cash refund (the tips stay). The hostess marks a freed table "cleaning". Z: tips apart from the sales, the
/// receipts add up to the sales, the drawer is right. The day's tip pool split by role weights adds up to the so'm, and
/// the day's staff pay statement matches the numbers worked out here, the handover included (sales to the waiter at
/// payment time; switched to the table's last waiter for a moment and back). Everything goes through pos_data.
@Timeout(Duration(minutes: 10))
library;

import 'dart:async';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/menu.dart';
import 'support/stack.dart';

Map<String, Object?> asMap(Object? value) =>
    (value! as Map<Object?, Object?>).cast<String, Object?>();

List<Map<String, Object?>> asList(Object? value) => <Map<String, Object?>>[
  for (final Object? e in value! as List<Object?>) asMap(e),
];

PosMoney money(Object? value) => PosMoney.parse(value);

/// `a / b` rounded half up (a, b ≥ 0).
int halfUp(int a, int b) => (2 * a + b) ~/ (2 * b);

/// [base] × [percent] % the way the server's Money::percent() then roundTo(0) does it: to the tiyin, then to the so'm.
PosMoney percentOf(PosMoney base, int percent) {
  final int tiyin = halfUp(base.tiyin * percent, 100);
  return PosMoney(halfUp(tiyin, 100) * 100);
}

/// The service charge of an order: [percent] of the dishes, to the whole so'm (half up).
PosMoney serviceCharge(PosMoney dishes, int percent) =>
    PosMoney(halfUp(dishes.tiyin * percent, 10000) * 100);

/// Banknotes for an amount (whole 100 so'm), largest first.
Map<int, int> banknotes(PosMoney amount) {
  final Map<int, int> notes = <int, int>{};
  var rest = amount.tiyin;
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
  expect(rest, 0, reason: 'cash in whole 100 so\'m');
  return notes;
}

/// The till's cable: while [cut], every request fails as if the internet were gone (the real-time socket stays).
class Cable {
  bool cut = false;

  Interceptor get interceptor => InterceptorsWrapper(
    onRequest: (RequestOptions options, RequestInterceptorHandler handler) {
      if (!cut) return handler.next(options);
      handler.reject(
        DioException(
          requestOptions: options,
          type: DioExceptionType.connectionError,
          error: const SocketException('e2e: the till\'s cable is out'),
        ),
      );
    },
  );
}

/// One table of the evening.
class Party {
  Party(this.table, this.guests, this.dishes, {required this.waiter});

  final String table;
  final int guests;
  final List<Dish> dishes;

  /// The waiter's phone; changes when the table is handed over.
  Terminal waiter;
  late int tableId;
  late String uuid;
  late PosOrder order;

  /// The service charge percent the rules give the table.
  int get percent => guests >= 8 ? 15 : 12;
  Iterable<int> get courses =>
      dishes.map((Dish d) => d.courseNo).whereType<int>().toSet().toList()
        ..sort();
}

/// One paid bill: who it is attributed to (the waiter at payment time), its tip and the receipt.
class Paid {
  Paid(
    this.party,
    this.bill,
    this.waiter,
    this.tenders,
    this.tip,
    this.receipt,
  );

  final Party party;
  final PosBill bill;
  final Staff waiter;
  final List<PosTenderInput> tenders;
  final PosMoney tip;
  final PosFiscalReceiptDetail receipt;

  /// The bill's sales for staff pay: the lines less the discount.
  PosMoney get sales => bill.subtotal - bill.discount;
}

void main() {
  late StackConfig config;
  late Seed seed;
  late Dio owner;
  late Terminal till;
  late Cable tillCable;
  late SyncStore tillStore;
  late Terminal hostess;
  late Terminal kds;
  late List<Terminal> waiters;
  late Menu menu;
  late PosFloor floor;
  late Staff manager;
  late PosShift shift;
  late String businessDate;
  late Set<int> displayStations;
  late FiscalReceiptRepository receipts;

  late PosBooking booking;
  late PosWaitlistEntry aziza;
  late PosWaitlistEntry sardor;

  final Map<String, Party> parties = <String, Party>{};
  final List<Paid> paid = <Paid>[];
  final List<Duration> courseLatencies = <Duration>[];
  final List<Duration> transferLatencies = <Duration>[];

  // The money of the evening, written down as it happens
  const PosMoney openingCash = PosMoney.som(400000);
  const PosMoney deposit = PosMoney.som(500000);
  PosMoney cashIn = PosMoney.zero; // cash payments (their amounts)
  PosMoney cashTips = PosMoney.zero;
  PosMoney refunded = PosMoney.zero;
  late String personalTipUuid;
  late PosMoney personalTip;
  late Paid refundedBill;
  late PosRefund refund;

  Terminal waiter(int n) => waiters[n - 1];
  Staff staffOf(Terminal t) => t.staff!;

  Future<Map<String, Object?>> ownerCall(
    String method,
    String path, [
    Map<String, Object?>? body,
  ]) async {
    final Response<Object?> r = await owner.request<Object?>(
      path,
      data: body,
      options: Options(method: method),
    );
    return asMap(asMap(r.data)['data']);
  }

  /// The manager types the PIN on [on] when the server asks for an approval.
  Future<T> withManager<T>(
    Terminal on,
    String permission,
    Future<T> Function(String? token) action,
  ) {
    var prompts = 0;
    return runWithApproval<T>(
      action,
      approve: (String needed, PosException cause) {
        prompts++;
        expect(
          needed,
          permission,
          reason: 'the permission the server asks for',
        );
        expect(prompts, 1, reason: 'the manager is asked once');
        return on.pos.approvals.request(
          permission: needed,
          userId: manager.id,
          pin: manager.pin!,
        );
      },
    );
  }

  /// The cook marks ready every line of [course] (null: the lines without a course) sent to a station with a display;
  /// returns when the last one was asked for.
  Future<DateTime> cook(Party p, {int? course}) async {
    final PosOrder o = await till.pos.orders.get(p.uuid);
    final List<PosOrderItem> lines = <PosOrderItem>[
      for (final PosOrderItem i in o.items)
        if (i.kitchenStatus == PosKitchenStatus.sent &&
            displayStations.contains(i.stationId) &&
            i.courseNo == course)
          i,
    ];
    expect(lines, isNotEmpty, reason: '${p.table}: course $course cooks');
    var last = DateTime.now();
    for (final PosOrderItem line in lines) {
      last = DateTime.now();
      await kds.client.kitchen.bumpLine(p.uuid, line.id);
    }
    return last;
  }

  /// The waiter serves what is ready (and the bar's drinks, which have no display, at once).
  Future<PosOrder> serve(Party p) async {
    PosOrder o = await p.waiter.pos.orders.get(p.uuid);
    for (final PosOrderItem item in o.items) {
      final bool atDisplay = displayStations.contains(item.stationId);
      if (item.kitchenStatus == PosKitchenStatus.ready ||
          (item.kitchenStatus == PosKitchenStatus.sent && !atDisplay)) {
        o = await p.waiter.pos.orders.markServed(
          p.uuid,
          item.id,
          version: o.version,
        );
      }
    }
    return p.order = o;
  }

  /// Course [n] of a table: sent (course 1 went with the order), cooked, heard by the table's waiter and nobody
  /// else's, served.
  Future<void> course(Party p, int n) async {
    PosOrder o = await p.waiter.pos.orders.get(p.uuid);
    if (n > 1) {
      expect(o.course(n)!.status, PosCourseStatus.held, reason: p.table);
      expect(o.course(n)!.canSend, isTrue);
      o = await p.waiter.pos.orders.sendCourse(p.uuid, n, version: o.version);
    }
    expect(o.course(n)!.status, PosCourseStatus.sent, reason: '${p.table} $n');
    for (final PosCourse later in o.courses.where((c) => c.courseNo > n)) {
      expect(
        later.status,
        PosCourseStatus.held,
        reason: '${p.table}: course ${later.courseNo} waits for its turn',
      );
    }
    final DateTime bumped = await cook(p, course: n);
    final HeardEvent heard = await p.waiter.log!.waitFor(
      (PosEvent e) =>
          e is PosCourseReady && e.orderUuid == p.uuid && e.courseNo == n,
      what: '${p.table}: course $n ready on ${p.waiter.label}',
    );
    courseLatencies.add(heard.at.difference(bumped));
    final PosCourseReady ready = heard.event as PosCourseReady;
    expect(ready.tableId, p.tableId);
    expect(ready.isFor(staffOf(p.waiter).id), isTrue);
    for (final Terminal other in waiters.where((w) => w != p.waiter)) {
      expect(
        ready.isFor(staffOf(other).id),
        isFalse,
        reason: '${p.table}: "$n-kurs tayyor" is not for ${other.label}',
      );
    }
    o = await serve(p);
    expect(o.course(n)!.status, PosCourseStatus.served, reason: p.table);
  }

  /// The dishes of a table at the menu's prices (the fish at its weighed kg).
  PosMoney dishesOf(Party p) => menu.total(p.dishes);

  /// Pays [bill] at the till; checks the receipt is the bill (tips apart) and writes the money down.
  Future<Paid> pay(
    Party p,
    PosBill bill,
    List<PosTenderInput> tenders, {
    int? tipStaffId,
  }) async {
    final Staff at = staffOf(p.waiter);
    final PosBillResult result = await till.pos.billing.pay(
      bill.uuid,
      tenders: tenders,
      shiftUuid: shift.uuid,
      tipStaffId: tipStaffId,
    );
    final PosBill done = result.bill;
    expect(done.isPaid, isTrue, reason: '${p.table}: bill ${bill.number}');
    final PosMoney tip = PosMoney.sum(<PosMoney>[
      for (final PosTenderInput t in tenders)
        if (t.tip != null)
          t.tip!
        else if (t.tipFromChange && t.tendered != null)
          t.tendered! - t.amount,
    ]);
    expect(done.tipsAmount, tip, reason: '${p.table}: the bill\'s tips');
    expect(done.totalWithTips, done.total + tip);
    for (final PosTenderInput t in tenders) {
      if (t.tender == PosTender.cash) {
        cashIn += t.amount;
        cashTips += t.tendered != null && t.tipFromChange
            ? t.tendered! - t.amount
            : (t.tip ?? PosMoney.zero);
      }
    }
    final PosFiscalReceiptDetail receipt = await receipts.get(
      done.fiscal.receiptUuid!,
    );
    expect(
      receipt.total,
      done.total,
      reason: '${p.table}: the receipt is the bill, never the tip',
    );
    expect(
      PosMoney.sum(receipt.payments.map((x) => x.amount)),
      done.total,
      reason: '${p.table}: the receipt\'s payments are the bill\'s',
    );
    expect(
      receipt.lines.where((l) => l.name.toLowerCase().contains('choy puli')),
      isEmpty,
    );
    final Paid row = Paid(p, done, at, tenders, tip, receipt);
    paid.add(row);
    return row;
  }

  setUpAll(() async {
    final StackConfig stack = StackConfig.fromEnvironment();
    seed = stack.seed.section('restoran');
    config = stack.withSeed(seed);
    manager = seed.staff('manager');
    owner = Dio(
      BaseOptions(
        baseUrl: config.apiUrl,
        headers: <String, Object?>{
          'Authorization': 'Bearer ${seed.ownerToken}',
          'Accept': 'application/json',
        },
      ),
    );

    // The till keeps a journal (the offline mode); the phones and the tablet do not
    tillCable = Cable();
    tillStore = await SyncStore.open(MemorySyncStorage());
    till = await Terminal.open(config, 'cashier', offlineStore: tillStore);
    till.client.dio.interceptors.add(tillCable.interceptor);
    await till.signIn('cashier');
    waiters = <Terminal>[
      for (var i = 1; i <= 4; i++) await Terminal.open(config, 'waiter$i'),
    ];
    for (var i = 1; i <= 4; i++) {
      await waiter(i).signIn('waiter$i');
    }
    hostess = await Terminal.open(config, 'hostess');
    await hostess.signIn('hostess');
    kds = await Terminal.open(config, 'kds');
    await kds.signIn('cook');
    await Future.wait(<Future<EventLog>>[
      till.listen(),
      hostess.listen(),
      for (final Terminal w in waiters) w.listen(),
    ]);
    menu = Menu(
      await till.pos.catalog.load(
        PosChannel.hall,
        menuVersion: till.bootstrap.menuVersion,
      ),
    );
    floor = await till.pos.floor.load();
    displayStations = <int>{
      for (final PosStation s in till.bootstrap.stations)
        if (s.showOnKds) s.id,
    };
    receipts = FiscalReceiptRepository(till.pos.http);
  });

  tearDownAll(() async {
    for (final Terminal t in <Terminal>[till, hostess, kds, ...waiters]) {
      await t.close();
    }
    owner.close();
    final StringBuffer out = StringBuffer(
      '\n[pos-e2e] juma kechasi restoran: ${parties.length} tables, ${paid.length} bills',
    );
    String stat(List<Duration> all) {
      final List<Duration> sorted = List<Duration>.of(all)..sort();
      return 'median ${sorted[(sorted.length - 1) ~/ 2].inMilliseconds} ms, max ${sorted.last.inMilliseconds} ms';
    }

    if (courseLatencies.isNotEmpty) {
      out.write(
        ', last dish ready → "kurs tayyor" on the waiter\'s phone (${courseLatencies.length}) ${stat(courseLatencies)}',
      );
    }
    if (transferLatencies.isNotEmpty) {
      out.write(
        ', handover request → "order.transferred" on both phones ${stat(transferLatencies)}',
      );
    }
    stdout.writeln(out);
  });

  group('Juma kechasi restoran', () {
    test(
      'the restaurant format; the staff clock in and the till opens the shift',
      () async {
        for (final Terminal t in <Terminal>[till, hostess, ...waiters]) {
          final PosFormat f = t.bootstrap.format;
          expect(f.profile, 'restoran', reason: t.label);
          expect(
            f.modules.hasAll(<PosModule>[
              PosModule.courses,
              PosModule.tips,
              PosModule.staffPay,
              PosModule.guestSplit,
              PosModule.waiterTransfer,
              PosModule.hostess,
              PosModule.tableBooking,
              PosModule.serviceChargeRules,
              PosModule.skewerButtons,
              PosModule.weightLater,
              PosModule.kds,
            ]),
            isTrue,
            reason: '${t.label}: ${f.modules.enabled}',
          );
        }
        final PosTipSettings tips = till.bootstrap.tips;
        expect(tips.enabled, isTrue);
        expect(tips.mode, PosTipMode.pool);
        expect(tips.poolRule, 'role_weights');
        expect(till.bootstrap.staffPay, isNotNull);
        final PosBookingRules rules = till.bootstrap.bookingRules!;
        expect(rules.depositRequiredToConfirm, isTrue);
        expect(rules.tableMinAdvanceMinutes, 60);

        // The menu the phones show: ×2…×10 on the skewer with its grill time, the fish weighed after it is chosen
        final PosProduct skewer = menu.product("Qo'y kabob");
        expect(skewer.quickMultipliers, <int>[2, 3, 5, 10]);
        expect(skewer.waitMinutes, 15);
        expect(menu.product('Sazan').weighedLater, isTrue);
        expect(
          floor.tables.where((t) => t.name.startsWith('Stol ')),
          hasLength(20),
        );

        // "Ishga keldim": the waiters on their phones, the hostess on her tablet, the cook at the kitchen display
        for (final Terminal t in <Terminal>[...waiters, hostess, kds]) {
          final PosClockState clock = await t.pos.staffClock.clockIn();
          expect(clock.session, isNotNull, reason: t.label);
          expect(clock.session!.clockOutAt, isNull);
        }
        await expectLater(
          waiter(1).pos.staffClock.clockIn(),
          throwsA(
            isA<PosConflict>().having(
              (e) => e.reason,
              'reason',
              'already_clocked_in',
            ),
          ),
        );

        shift = await till.pos.shifts.open(
          openingCash: openingCash,
          note: 'Juma kechqurun',
        );
        till.pos.session.updateShift(shift.toSummary());
        businessDate = shift.businessDate!;
        expect(
          (await waiter(1).pos.staffClock.state()).businessDate,
          businessDate,
        );
      },
    );

    test('the hostess books a party of ten: too soon is refused, the deposit confirms it, 15% is announced', () async {
      final FloorTable vip = floor.tables.firstWhere(
        (t) => t.name == 'Stol 17',
      );

      // 20 minutes ahead: the rule is an hour
      await expectLater(
        hostess.pos.bookings.create(
          PosBookingInput(
            diningTableId: vip.id,
            startsAt: DateTime.now().toUtc().add(const Duration(minutes: 20)),
            guestsPlanned: 10,
            contactName: 'Shoshilgan mijoz',
          ),
        ),
        throwsA(
          isA<PosException>()
              .having((e) => e.statusCode, 'status', 422)
              .having((e) => e.reason, 'reason', 'booking_too_soon'),
        ),
      );

      booking = await hostess.pos.bookings.create(
        PosBookingInput(
          diningTableId: vip.id,
          startsAt: DateTime.now().toUtc().add(const Duration(minutes: 75)),
          guestsPlanned: 10,
          eventType: 'tugilgan_kun',
          contactName: 'Nodir aka',
          contactPhone: '+998903334455',
          source: 'phone',
        ),
      );
      expect(booking.status, 'requested');
      expect(booking.depositRequired, deposit, reason: '10 × 50 000');
      expect(booking.depositMissing, deposit);
      // Not confirmed without the deposit
      await expectLater(
        hostess.pos.bookings.confirm(booking.uuid),
        throwsA(
          isA<PosConflict>().having(
            (e) => e.reason,
            'reason',
            'deposit_required',
          ),
        ),
      );

      // The confirmation the guest gets: the large-group service charge of the rules
      final PosBookingConfirmation text = await hostess.pos.bookings
          .confirmation(booking.uuid);
      final PosConfirmationLine charge = text.lines.firstWhere(
        (l) => l.kind == 'service_charge',
      );
      expect(charge.value, "15% (8 va undan ko'p mehmon)");
      expect(text.text, contains("Xizmat haqi: 15% (8 va undan ko'p mehmon)"));

      // The deposit at the till (cash, an ADVANCE receipt): the booking confirms by itself
      final PosDepositResult taken = await till.pos.bookings.takeDeposit(
        booking.uuid,
        amount: deposit,
        tender: 'cash',
        idempotencyKey: till.pos.http.newKey(),
      );
      expect(taken.fiscal.receipt!.receiptType, FiscalReceiptType.advance);
      booking = await hostess.pos.bookings.get(booking.uuid);
      expect(booking.status, 'confirmed');
      expect(booking.depositMissing, PosMoney.zero);
      expect(booking.depositAvailable, deposit);
      await hostess.log!.waitFor(
        (PosEvent e) => e is PosBookingChanged && e.bookingUuid == booking.uuid,
        what: 'booking.changed',
      );

      // A party of four tomorrow needs no deposit (from 6 guests): confirmed at once
      final PosBooking small = await hostess.pos.bookings.create(
        PosBookingInput(
          diningTableId: floor.tables.firstWhere((t) => t.name == 'Stol 8').id,
          startsAt: DateTime.now().toUtc().add(const Duration(days: 1)),
          guestsPlanned: 4,
          contactName: 'Laziz',
          confirm: true,
        ),
      );
      expect(small.status, 'confirmed');
      expect(small.depositRequired, PosMoney.zero);
    });

    test(
      'walk-ins wait on the list, are called and seated by the hostess',
      () async {
        aziza = await hostess.pos.hostess.add(
          guestName: 'Aziza',
          partySize: 2,
          guestPhone: '+998907776655',
          quotedWaitMinutes: 15,
        );
        sardor = await hostess.pos.hostess.add(
          guestName: 'Sardor',
          partySize: 5,
          quotedWaitMinutes: 25,
          note: 'Deraza yonida',
        );
        expect(aziza.position, 1);
        expect(sardor.position, 2);
        await hostess.log!.waitFor(
          (PosEvent e) => e is PosWaitlistChanged && e.entryUuid == sardor.uuid,
          what: 'waitlist.changed',
        );

        aziza = await hostess.pos.hostess.notify(
          aziza.uuid,
          version: aziza.version,
        );
        expect(aziza.notifiedAt, isNotNull);
        aziza = await hostess.pos.hostess.seat(
          aziza.uuid,
          diningTableId: floor.tables.firstWhere((t) => t.name == 'Stol 2').id,
          version: aziza.version,
        );
        sardor = await hostess.pos.hostess.seat(
          sardor.uuid,
          diningTableId: floor.tables.firstWhere((t) => t.name == 'Stol 12').id,
          version: sardor.version,
        );
        expect(aziza.status, 'seated');
        expect(sardor.tableName, 'Stol 12');
        final PosWaitlist list = await hostess.pos.hostess.waitlist();
        expect(list.summary.seated, 2);
        expect(list.summary.waiting, 0);
        // The hostess opens no orders: the waiter does
        expect(hostess.pos.session.can(PosPermissions.hostessManage), isTrue);
        expect(hostess.pos.session.can('orders.manage'), isFalse);
      },
    );

    test('six tables are served: three courses fired one by one, each "course ready" reaches its own waiter', () async {
      parties['A'] = Party('Stol 17', 10, <Dish>[
        Dish.of('Sezar salati', 5, courseNo: 1),
        Dish.of('Achchiq-chuchuk', 5, courseNo: 1),
        Dish.of("Mol go'shti steyk", 6, courseNo: 2),
        Dish.of("Lag'mon", 4, courseNo: 2),
        Dish.of('Medovik', 10, courseNo: 3),
        Dish.of('Limonad', 4),
      ], waiter: waiter(1));
      parties['B'] = Party('Stol 3', 4, <Dish>[
        Dish.of('Sezar salati', 2, courseNo: 1),
        Dish.of("Qo'y kabob", 10, courseNo: 2),
        Dish.of('Sazan', 1, courseNo: 2),
        Dish.of('Chizkeyk', 4, courseNo: 3),
        Dish.of('Choy', 2),
      ], waiter: waiter(1));
      parties['C'] = Party('Stol 5', 3, <Dish>[
        Dish.of('Sezar salati', 1, courseNo: 1, seatNo: 1),
        Dish.of('Achchiq-chuchuk', 1, courseNo: 1, seatNo: 2),
        Dish.of('Sezar salati', 1, courseNo: 1, seatNo: 3),
        Dish.of("Mol go'shti steyk", 1, courseNo: 2, seatNo: 1),
        Dish.of("Lag'mon", 1, courseNo: 2, seatNo: 2),
        Dish.of("Lag'mon", 1, courseNo: 2, seatNo: 3),
        Dish.of('Medovik', 1, courseNo: 3, seatNo: 1),
        Dish.of('Medovik', 1, courseNo: 3, seatNo: 2),
        Dish.of('Limonad', 1, seatNo: 3),
        Dish.of('Choy', 1),
      ], waiter: waiter(2));
      parties['D'] = Party('Stol 9', 4, <Dish>[
        Dish.of('Achchiq-chuchuk', 4, courseNo: 1),
        Dish.of("Mol go'shti steyk", 2, courseNo: 2),
        Dish.of("Qo'y kabob", 6, courseNo: 2),
        Dish.of('Medovik', 2, courseNo: 3),
        Dish.of('Chizkeyk', 2, courseNo: 3),
      ], waiter: waiter(3));
      // The walk-ins: no courses, everything to the kitchen at once
      parties['E'] = Party('Stol 2', 2, <Dish>[
        Dish.of("Lag'mon", 2),
        Dish.of('Choy', 1),
      ], waiter: waiter(4));
      parties['F'] = Party('Stol 12', 5, <Dish>[
        Dish.of("Qo'y kabob", 10),
        Dish.of('Achchiq-chuchuk', 2),
        Dish.of('Limonad', 2),
      ], waiter: waiter(2));
      for (final Party p in parties.values) {
        p.tableId = floor.tables.firstWhere((t) => t.name == p.table).id;
      }

      // The booked party arrives early ("Keldi" at the till); waiter 1 takes the order over with the first round
      final Party a = parties['A']!;
      final PosArrival arrival = await till.pos.bookings.arrive(
        booking.uuid,
        guests: a.guests,
      );
      a.uuid = arrival.orderUuid;
      PosOrder arrived = await a.waiter.pos.orders.get(a.uuid);
      expect(arrived.depositAvailable, deposit);
      expect(arrived.table!.name, a.table);
      arrived = await a.waiter.pos.orders.addRound(
        a.uuid,
        version: arrived.version,
        items: menu.inputs(a.dishes),
        send: true,
      );
      expect(arrived.waiter!.id, staffOf(a.waiter).id);

      for (final String key in <String>['B', 'C', 'D', 'E', 'F']) {
        final Party p = parties[key]!;
        final PosOrder o = await p.waiter.pos.orders.open(
          channel: PosChannel.hall,
          tableId: p.tableId,
          guestsCount: p.guests,
          waiterId: staffOf(p.waiter).id,
          externalId: 'e2e-restoran-${p.table}',
          items: menu.inputs(p.dishes),
          send: true,
        );
        p.uuid = o.uuid;
        expect(o.waiter!.id, staffOf(p.waiter).id);
      }
      for (final Party p in parties.values) {
        p.order = await till.pos.orders.get(p.uuid);
        for (final PosOrderItem item in p.order.items) {
          if (item.courseNo == null || item.courseNo == 1) {
            expect(
              item.kitchenStatus,
              PosKitchenStatus.sent,
              reason: '${p.table}: ${item.productName} goes at once',
            );
          } else {
            expect(
              item.kitchenStatus,
              PosKitchenStatus.pending,
              reason:
                  '${p.table}: ${item.productName} waits for course ${item.courseNo}',
            );
          }
        }
      }

      // The skewers: one line of 10 (the ×10 button), the grill's time on the kitchen ticket with its course
      final PosOrder f = parties['F']!.order;
      expect(
        f.items.firstWhere((i) => i.productName == "Qo'y kabob").quantity,
        const PosQuantity.whole(10),
      );
      final Party b = parties['B']!;
      expect(b.order.weightPending, 1, reason: 'the fish is weighed later');
      final PosOrderItem fish = b.order.items.firstWhere(
        (i) => i.productName == 'Sazan',
      );
      expect(fish.awaitingWeight, isTrue);
      final KitchenTicket ticketC = (await kds.client.kitchen.tickets()).tickets
          .firstWhere((t) => t.uuid == parties['C']!.uuid);
      expect(
        ticketC.lines
            .where((l) => l.status == 'sent')
            .map((l) => l.courseNo)
            .toSet(),
        <int?>{1, null},
        reason: 'the first course is cooking, and the drink without a course',
      );
      expect(
        ticketC.lines
            .where((l) => l.status == 'sent' && l.courseNo == 1)
            .map((l) => l.seatNo)
            .toSet(),
        <int>{1, 2, 3},
        reason: 'each guest\'s salad carries the seat',
      );

      // The tables without courses: cooked and served at once (the drinks come from the bar)
      for (final String key in <String>['E', 'F']) {
        await cook(parties[key]!);
        await serve(parties[key]!);
      }
      // Courses 1 and 2 on every coursed table, one table after another
      for (final int n in <int>[1, 2]) {
        for (final String key in <String>['A', 'B', 'C', 'D']) {
          final Party p = parties[key]!;
          if (n == 1) await serve(p); // the drinks without a course
          await course(p, n);
        }
      }
      // Course 3 for the tables that are not handed over
      for (final String key in <String>['A', 'B', 'C']) {
        await course(parties[key]!, 3);
      }
    });

    test(
      'the fish is weighed: no bill until then, the price follows the real kg',
      () async {
        final Party b = parties['B']!;
        PosOrder o = await till.pos.orders.get(b.uuid);
        await expectLater(
          till.pos.billing.createBills(b.uuid, version: o.version),
          throwsA(
            isA<PosConflict>().having(
              (e) => e.reason,
              'reason',
              'weight_pending',
            ),
          ),
        );
        final PosOrderItem fish = o.items.firstWhere(
          (i) => i.productName == 'Sazan',
        );
        // The grill weighed it raw: 1.35 kg; the waiter enters it on the phone
        o = await b.waiter.pos.orders.setWeight(
          b.uuid,
          fish.id,
          version: (await b.waiter.pos.orders.get(b.uuid)).version,
          quantity: PosQuantity.parse('1.35'),
        );
        expect(o.weightPending, 0);
        final PosOrderItem weighed = o.items.firstWhere((i) => i.id == fish.id);
        expect(weighed.weightStatus, PosWeightStatus.weighed);
        expect(weighed.quantity, PosQuantity.parse('1.35'));
        expect(
          weighed.totalPrice,
          const PosMoney.som(324000),
          reason: '1.35 × 240 000',
        );
        b.dishes[b.dishes.indexWhere((d) => d.name == 'Sazan')] = Dish.of(
          'Sazan',
          '1.35',
          courseNo: 2,
        );
        b.order = o;
      },
    );

    test('every order adds up: the dishes and the service charge of the rules (15% from 8 guests, else 12%)', () async {
      for (final Party p in parties.values) {
        final PosOrder o = await till.pos.orders.get(p.uuid);
        final PosMoney dishes = dishesOf(p);
        expect(o.subtotal, dishes, reason: p.table);
        expect(
          o.serviceChargeAmount,
          serviceCharge(dishes, p.percent),
          reason: p.table,
        );
        expect(
          o.serviceChargeRule,
          p.guests >= 8 ? 'large_group' : 'order_type',
          reason: p.table,
        );
        expect(o.serviceChargeNotice, contains('Xizmat haqi'), reason: p.table);
        expect(
          o.totalPrice,
          dishes + serviceCharge(dishes, p.percent),
          reason: p.table,
        );
        p.order = o;
      }
    });

    test('table 9 is split by amount, half is paid, the table goes to another waiter with the manager\'s PIN', () async {
      final Party d = parties['D']!;
      final Terminal old = d.waiter;
      final Terminal next = waiter(4);
      final PosBilling billing = await till.pos.billing.createBills(
        d.uuid,
        version: (await till.pos.orders.get(d.uuid)).version,
        split: const PosSplit.equal(2),
      );
      expect(billing.bills, hasLength(2));
      expect(
        PosMoney.sum(billing.bills.map((x) => x.total)),
        d.order.totalPrice,
      );
      // The guest who leaves pays half by card with a tip; the waiter at payment time is the first one
      final PosBill first = billing.bills.first;
      final Paid half = await pay(d, first, <PosTenderInput>[
        PosTenderInput.card(
          first.total,
          cardType: 'humo',
          transactionId: '629900000901',
          cardLast4: '9012',
          tip: const PosMoney.som(50000),
        ),
      ]);
      personalTipUuid = half.bill.tips.single.uuid;
      personalTip = const PosMoney.som(50000);
      expect(
        half.bill.tips.single.mode,
        PosTipMode.pool,
        reason: 'the branch pools its tips',
      );

      // Waiter 3's shift ends: he cannot hand the table over himself, the manager types the PIN on his phone
      final PosOrder before = await old.pos.orders.get(d.uuid);
      // When the request that hands it over went out (the second one, with the manager's token)
      var asked = DateTime.now();
      final PosOrder handed = await withManager<PosOrder>(
        old,
        PosPermissions.ordersTransfer,
        (String? token) {
          asked = DateTime.now();
          return old.pos.hostess.transferOrder(
            d.uuid,
            version: before.version,
            waiterId: staffOf(next).id,
            reason: 'Smenasi tugadi',
            approvalToken: token,
          );
        },
      );
      expect(handed.waiter!.id, staffOf(next).id);
      for (final Terminal phone in <Terminal>[old, next]) {
        final HeardEvent heard = await phone.log!.waitFor(
          (PosEvent e) => e is PosOrderTransferred && e.orderUuid == d.uuid,
          what: 'order.transferred on ${phone.label}',
        );
        transferLatencies.add(heard.at.difference(asked));
        final PosOrderTransferred t = heard.event as PosOrderTransferred;
        expect(t.isFromMe(staffOf(old).id), isTrue);
        expect(t.isToMe(staffOf(next).id), isTrue);
        expect(t.tableRef, d.table);
      }
      d.waiter = next;
      // "Ishdan ketdim"
      final PosClockState out = await old.pos.staffClock.clockOut();
      expect(out.session, isNull);

      // The new waiter sends the desserts: "3-kurs tayyor" is his, not the old waiter's
      await course(d, 3);
      final PosBill second = (await till.pos.billing.bills(d.uuid)).bills
          .firstWhere((x) => !x.isPaid && x.status != 'void');
      expect(
        second.uuid,
        billing.bills.last.uuid,
        reason: 'the second half is the bill made before the handover',
      );
      await pay(d, second, <PosTenderInput>[
        PosTenderInput.card(
          second.total,
          cardType: 'uzcard',
          transactionId: '629900000902',
        ),
      ]);
      final PosOrder closed = await till.pos.orders.get(d.uuid);
      expect(closed.status, 'delivered');
    });

    test('the bills: by guest, single, the deposit; tips in cash and by card, change left as a tip', () async {
      // Table 5 by guest: one bill per seat, the shared tea split among the three
      final Party c = parties['C']!;
      final PosBilling byGuest = await till.pos.billing.createBills(
        c.uuid,
        version: (await till.pos.orders.get(c.uuid)).version,
        split: const PosSplit.byGuest(),
      );
      expect(byGuest.bills.map((x) => x.seatNo).toList(), <int>[1, 2, 3]);
      expect(
        PosMoney.sum(byGuest.bills.map((x) => x.total)),
        c.order.totalPrice,
      );
      final List<PosBill> seats = byGuest.bills;
      // Guest 1 by card with a tip, guest 2 in cash leaving the change, guest 3 by QR
      await pay(c, seats[0], <PosTenderInput>[
        PosTenderInput.card(
          seats[0].total,
          cardType: 'uzcard',
          transactionId: '629900000501',
          tip: const PosMoney.som(30000),
        ),
      ]);
      final PosMoney handed2 = PosMoney.som(
        (seats[1].total.tiyin ~/ 1000000 + 1) * 10000,
      );
      refundedBill = await pay(c, seats[1], <PosTenderInput>[
        PosTenderInput.cash(
          seats[1].total,
          tendered: handed2,
          tipFromChange: true,
        ),
      ]);
      expect(
        refundedBill.bill.payments.single.changeAmount ?? PosMoney.zero,
        PosMoney.zero,
      );
      await pay(c, seats[2], <PosTenderInput>[
        PosTenderInput.qr(seats[2].total, reference: 'QR-E2E-5'),
      ]);

      // Table 17: one bill, the deposit first, the rest by card with a tip
      final Party a = parties['A']!;
      final PosBill aBill = (await till.pos.billing.createBills(
        a.uuid,
        version: (await till.pos.orders.get(a.uuid)).version,
      )).bills.single;
      expect(aBill.total, a.order.totalPrice);
      await pay(a, aBill, <PosTenderInput>[
        const PosTenderInput.prepayment(deposit),
        PosTenderInput.card(
          aBill.total - deposit,
          cardType: 'visa',
          transactionId: '629900001701',
          tip: const PosMoney.som(100000),
        ),
      ]);

      // Table 3: one bill in cash, the change left as the tip
      final Party b = parties['B']!;
      final PosBill bBill = (await till.pos.billing.createBills(
        b.uuid,
        version: (await till.pos.orders.get(b.uuid)).version,
      )).bills.single;
      expect(bBill.total, b.order.totalPrice);
      final PosMoney handedB = PosMoney.som(
        (bBill.total.tiyin ~/ 5000000 + 1) * 50000,
      );
      await pay(b, bBill, <PosTenderInput>[
        PosTenderInput.cash(
          bBill.total,
          tendered: handedB,
          tipFromChange: true,
        ),
      ]);

      // Table 12 by card, no tip
      final Party f = parties['F']!;
      final PosBill fBill = (await till.pos.billing.createBills(
        f.uuid,
        version: (await till.pos.orders.get(f.uuid)).version,
      )).bills.single;
      await pay(f, fBill, <PosTenderInput>[
        PosTenderInput.card(
          fBill.total,
          cardType: 'humo',
          transactionId: '629900001201',
        ),
      ]);

      for (final String key in <String>['A', 'B', 'C', 'F']) {
        final PosOrder o = await till.pos.orders.get(parties[key]!.uuid);
        expect(o.status, 'delivered', reason: '${parties[key]!.table} closed');
      }

      // A guest asks for his tip to go to the waiter who served him: the manager moves it, with a reason
      final PosTip moved = await withManager<PosTip>(
        till,
        PosPermissions.tipsManage,
        (String? token) => till.pos.tips.correct(
          personalTipUuid,
          mode: PosTipMode.personal,
          staffId: seed.staff('waiter3').id,
          reason: 'Mehmon shaxsan ofitsiantga berdi',
          approvalToken: token,
        ),
      );
      expect(moved.mode, PosTipMode.personal);
      expect(moved.staff!.id, seed.staff('waiter3').id);
      expect(moved.amount, personalTip);
    });

    test('the internet goes at the till: table 2 pays by card with a tip offline and syncs', () async {
      final Party e = parties['E']!;
      final PosBill bill = (await till.pos.billing.createBills(
        e.uuid,
        version: (await till.pos.orders.get(e.uuid)).version,
      )).bills.single;
      expect(bill.total, e.order.totalPrice);

      tillCable.cut = true;
      till.pos.connectivity.reportFailure(
        const SocketException('e2e: the cable is out'),
      );
      expect(till.pos.offline!.active, isTrue);
      final List<PosTenderInput> tenders = <PosTenderInput>[
        PosTenderInput.card(
          bill.total,
          cardType: 'uzcard',
          transactionId: '629900000201',
          tip: const PosMoney.som(25000),
        ),
      ];
      await till.pos.billing.pay(
        bill.uuid,
        tenders: tenders,
        shiftUuid: shift.uuid,
      );
      expect(tillStore.pending, isNotEmpty);

      tillCable.cut = false;
      till.pos.connectivity.reportReachable();
      final SyncDrainResult sync = await till.pos.offline!.syncNow();
      expect(sync.error, isNull);
      expect(sync.left, 0);
      for (final SyncOperation op in tillStore.answered) {
        expect(
          op.status,
          SyncOpStatus.applied,
          reason: '${op.type} ${op.reason} ${op.message}',
        );
      }
      final PosBill synced = (await till.pos.billing.bills(e.uuid))
          .bills
          .single;
      expect(synced.isPaid, isTrue);
      expect(synced.tipsAmount, const PosMoney.som(25000));
      final PosFiscalReceiptDetail receipt = await receipts.get(
        synced.fiscal.receiptUuid!,
      );
      expect(receipt.total, synced.total);
      paid.add(
        Paid(
          e,
          synced,
          staffOf(e.waiter),
          tenders,
          const PosMoney.som(25000),
          receipt,
        ),
      );
      expect((await till.pos.orders.get(e.uuid)).status, 'delivered');
    });

    test('one cash refund with the manager\'s PIN: the tips stay', () async {
      final PosShiftTips before = await till.pos.tips.shiftTips(
        shiftUuid: shift.uuid,
      );
      const PosMoney amount = PosMoney.som(20000);
      refund = await withManager<PosRefund>(
        till,
        PosPermissions.refund,
        (String? token) => till.pos.billing.refund(
          paymentUuid: refundedBill.bill.payments.single.uuid,
          amount: amount,
          reason: 'Lag\'mon sovuq edi',
          shiftUuid: shift.uuid,
          approvalToken: token,
        ),
      );
      refunded += amount;
      expect(refund.amount, amount);
      expect(refund.method, 'cash');
      final PosShiftTips after = await till.pos.tips.shiftTips(
        shiftUuid: shift.uuid,
      );
      expect(
        after.summary.amount,
        before.summary.amount,
        reason: 'a refund never takes tips back',
      );
      expect(after.summary.count, before.summary.count);
    });

    test('the hostess marks the freed table cleaning, then free', () async {
      final int table = parties['C']!.tableId;
      final PosTableState cleaning = await hostess.pos.hostess.setTableCleaning(
        table,
        cleaning: true,
      );
      expect(cleaning.serviceState, 'cleaning');
      await waiter(2).log!.waitFor(
        (PosEvent e) =>
            e is PosTableStateChanged &&
            e.tableId == table &&
            e.serviceState == 'cleaning',
        what: 'table.state_changed cleaning',
      );
      final PosTableState free = await hostess.pos.hostess.setTableCleaning(
        table,
        cleaning: false,
      );
      expect(free.serviceState, isNull);
    });

    test('Z: the tips apart from the sales, the receipts add up to the sales, the drawer is right', () async {
      expect(paid, hasLength(9));
      final PosMoney tipsTotal = PosMoney.sum(paid.map((p) => p.tip));
      final PosMoney billsTotal = PosMoney.sum(paid.map((p) => p.bill.total));
      final PosMoney receiptsTotal = PosMoney.sum(
        paid.map((p) => p.receipt.total),
      );
      expect(receiptsTotal, billsTotal);

      final PosShiftWithReport x = await till.pos.shifts.xReport(shift.uuid);
      final PosShiftReport report = x.report!;
      final Map<String, Object?> sales = asMap(report.raw['sales']);
      expect(sales['bills'], paid.length);
      expect(money(sales['bills_total']), billsTotal);
      expect(
        money(sales['received']),
        billsTotal,
        reason: 'the payments are the bills: no tips',
      );
      expect(money(sales['refunded']), refunded);
      PosMoney byTender(String tender) => money(
        asList(report.raw['by_tender'])
            .firstWhere((r) => r['tender'] == tender)['amount'],
      );
      PosMoney paidBy(PosTender tender) => PosMoney.sum(<PosMoney>[
        for (final Paid p in paid)
          for (final PosTenderInput t in p.tenders)
            if (t.tender == tender) t.amount,
      ]);
      for (final PosTender t in <PosTender>[
        PosTender.cash,
        PosTender.card,
        PosTender.qr,
        PosTender.prepayment,
      ]) {
        expect(
          byTender(t.wire),
          paidBy(t),
          reason: '${t.wire} on the report, without tips',
        );
      }
      final PosTipsSummary tips = report.tips;
      expect(tips.amount, tipsTotal);
      expect(tips.count, 6);
      expect(tips.personal, personalTip);
      expect(tips.pool, tipsTotal - personalTip);
      expect(tips.byStaff.single.staffId, seed.staff('waiter3').id);
      expect(tips.byMethod['cash'], cashTips);
      expect(report.cashTips, cashTips);
      final PosMoney expected =
          openingCash + deposit + cashIn - refunded + cashTips;
      expect(report.expectedCash, expected);

      final PosShift z = await till.pos.shifts.close(
        shift.uuid,
        countedBreakdown: banknotes(expected),
      );
      expect(z.status, 'closed');
      expect(z.difference, PosMoney.zero);
      expect(z.summary!.tips.amount, tipsTotal);
      till.pos.session.updateShift(null);

      // "Ishdan ketdim" for everyone still at work
      for (final Terminal t in <Terminal>[
        waiter(1),
        waiter(2),
        waiter(4),
        hostess,
        kds,
      ]) {
        final PosClockState out = await t.pos.staffClock.clockOut();
        expect(out.session, isNull, reason: t.label);
      }
    });

    test('the day\'s tip pool is split by the role weights and adds up to the so\'m', () async {
      final PosMoney pool = PosMoney.sum(paid.map((p) => p.tip)) - personalTip;
      final Map<String, Object?> split = await ownerCall(
        'POST',
        '/v1/owner/pos/tips/distributions',
        <String, Object?>{
          'branch_id': seed.branchId,
          'business_date': businessDate,
        },
      );
      expect(split['rule'], 'role_weights');
      expect(money(split['pool_amount']), pool);
      final List<Map<String, Object?>> lines = asList(split['lines']);
      // Who worked: the four waiters (2 parts each) and the cook (1 part); the hostess and the cashier are not in the pool
      final Map<int, int> weights = <int, int>{
        for (var i = 1; i <= 4; i++) seed.staff('waiter$i').id: 2,
        seed.staff('cook').id: 1,
      };
      expect(
        lines.map((l) => (l['staff_id']! as num).toInt()).toSet(),
        weights.keys.toSet(),
      );

      // The rule by hand: whole so'm in proportion rounded down, the so'm left one each to the largest remainders
      // (ties: the larger weight, then the lower id), the tiyin to the largest share
      final int whole = pool.tiyin ~/ 100;
      final int total = weights.values.fold(0, (int a, int b) => a + b);
      final Map<int, int> soms = <int, int>{
        for (final MapEntry<int, int> w in weights.entries)
          w.key: whole * w.value ~/ total,
      };
      final List<int> order = weights.keys.toList()
        ..sort((int x, int y) {
          final int r = (whole * weights[y]! % total).compareTo(
            whole * weights[x]! % total,
          );
          if (r != 0) return r;
          final int w = weights[y]!.compareTo(weights[x]!);
          return w != 0 ? w : x.compareTo(y);
        });
      final int left = whole - soms.values.fold(0, (int a, int b) => a + b);
      for (var i = 0; i < left; i++) {
        soms[order[i]] = soms[order[i]]! + 1;
      }
      final Map<int, PosMoney> shares = <int, PosMoney>{
        for (final MapEntry<int, int> s in soms.entries)
          s.key: PosMoney.som(s.value),
      };
      final int cents = pool.tiyin % 100;
      if (cents > 0) {
        final int top =
            (shares.keys.toList()..sort((x, y) {
                  final int c = shares[y]!.tiyin.compareTo(shares[x]!.tiyin);
                  return c != 0 ? c : x.compareTo(y);
                }))
                .first;
        shares[top] = shares[top]! + PosMoney(cents);
      }
      for (final Map<String, Object?> line in lines) {
        final int id = (line['staff_id']! as num).toInt();
        expect(
          money(line['amount']),
          shares[id],
          reason: 'the share of ${line['name']}',
        );
      }
      expect(
        PosMoney.sum(lines.map((l) => money(l['amount']))),
        pool,
        reason: 'to the so\'m and the tiyin',
      );
      expect(money(split['distributed']), pool);
      tipShares
        ..clear()
        ..addAll(shares);
    });

    test('the day\'s staff pay statement matches the numbers worked out by hand, the handover included', () async {
      // Each waiter's own sales and service charge: the bills paid while the table was theirs, less the refund's share
      Map<int, (PosMoney, PosMoney)> byWaiter({required bool atPayment}) {
        final Map<int, (PosMoney, PosMoney)> out =
            <int, (PosMoney, PosMoney)>{};
        for (final Paid p in paid) {
          final int who = atPayment ? p.waiter.id : staffOf(p.party.waiter).id;
          final (PosMoney s, PosMoney c) =
              out[who] ?? (PosMoney.zero, PosMoney.zero);
          out[who] = (s + p.sales, c + p.bill.serviceChargeAmount);
        }
        // The refund: in the refunded bill's proportions (to the tiyin, half up), off the bill's person
        final PosBill r = refundedBill.bill;
        final int a = refund.amount.tiyin;
        final PosMoney salesPart = PosMoney(
          halfUp(a * refundedBill.sales.tiyin, r.total.tiyin),
        );
        final PosMoney chargePart = PosMoney(
          halfUp(a * r.serviceChargeAmount.tiyin, r.total.tiyin),
        );
        final int who = refundedBill.waiter.id;
        final (PosMoney s, PosMoney c) = out[who]!;
        out[who] = (s - salesPart, c - chargePart);
        return out;
      }

      PosMoney tipsOf(int id) =>
          (tipShares[id] ?? PosMoney.zero) +
          (id == seed.staff('waiter3').id ? personalTip : PosMoney.zero);

      void check(Map<String, Object?> statement, {required bool atPayment}) {
        final Map<int, Map<String, Object?>> lines =
            <int, Map<String, Object?>>{
              for (final Map<String, Object?> l in asList(statement['lines']))
                (l['user_id']! as num).toInt(): l,
            };
        final Map<int, (PosMoney, PosMoney)> sales = byWaiter(
          atPayment: atPayment,
        );
        var gross = PosMoney.zero;
        for (var i = 1; i <= 4; i++) {
          final Staff w = seed.staff('waiter$i');
          final Map<String, Object?> line =
              lines[w.id] ?? (throw StateError('no line for ${w.name}'));
          final (PosMoney s, PosMoney c) =
              sales[w.id] ?? (PosMoney.zero, PosMoney.zero);
          final Map<String, Object?> metrics = asMap(line['metrics']);
          expect(money(metrics['sales']), s, reason: '${w.name}: sales');
          expect(
            money(metrics['service_charge']),
            c,
            reason: '${w.name}: service charge',
          );
          expect(
            money(metrics['tips']),
            tipsOf(w.id),
            reason: '${w.name}: tips',
          );
          final Map<String, PosMoney> parts = <String, PosMoney>{
            for (final Map<String, Object?> part in asList(line['components']))
              part['type']! as String: money(part['amount']),
          };
          expect(parts, <String, PosMoney>{
            'shift_rate': const PosMoney.som(100000),
            'sales_percent': percentOf(s, 5),
            'service_charge_percent': percentOf(c, 30),
            // Every part is rounded to the so'm once
            'tips': tipsOf(w.id).roundToSom(),
          }, reason: w.name);
          final PosMoney sum = PosMoney.sum(parts.values);
          expect(money(line['gross']), sum, reason: '${w.name}: gross');
          gross += sum;
        }
        final Map<String, PosMoney> others = <String, PosMoney>{
          'cook':
              const PosMoney.som(180000) +
              tipsOf(seed.staff('cook').id).roundToSom(),
          'hostess': const PosMoney.som(120000),
          'cashier': const PosMoney.som(110000),
        };
        for (final MapEntry<String, PosMoney> o in others.entries) {
          final Map<String, Object?> line = lines[seed.staff(o.key).id]!;
          expect(money(line['gross']), o.value, reason: o.key);
          gross += o.value;
        }
        expect(
          lines,
          hasLength(7),
          reason: 'four waiters, the cook, the hostess, the cashier',
        );
        expect(money(statement['gross_total']), gross);
        expect(asList(statement['warnings']), isEmpty);
      }

      final Map<String, Object?> statement = await ownerCall(
        'POST',
        '/v1/owner/staff-pay/statements',
        <String, Object?>{'branch_id': seed.branchId, 'date': businessDate},
      );
      expect(statement['period'], 'day');
      expect(statement['period_from'], businessDate);
      check(statement, atPayment: true);
      // Waiter 3's line holds only the half of table 9 paid before the handover
      final Map<String, Object?> jasur = asList(statement['lines'])
          .firstWhere((l) => l['user_id'] == seed.staff('waiter3').id);
      expect(asMap(jasur['metrics'])['bills'], 1);

      // The owner switches whose sales a handed-over table is to the table's last waiter, recomputes, then back
      final String uuid = statement['uuid']! as String;
      final Map<String, Object?> setting = await ownerCall(
        'PUT',
        '/v1/owner/staff-pay/settings',
        <String, Object?>{
          'branch_id': seed.branchId,
          'sales_attribution': 'current_waiter',
        },
      );
      expect(setting['sales_attribution'], 'current_waiter');
      check(
        await ownerCall(
          'POST',
          '/v1/owner/staff-pay/statements/$uuid/recompute',
        ),
        atPayment: false,
      );
      await ownerCall('PUT', '/v1/owner/staff-pay/settings', <String, Object?>{
        'branch_id': seed.branchId,
        'sales_attribution': 'at_payment',
      });
      check(
        await ownerCall(
          'POST',
          '/v1/owner/staff-pay/statements/$uuid/recompute',
        ),
        atPayment: true,
      );
    });
  });
}

/// The pool shares of the day (staff id → amount), from the tip-pool test, for the staff pay statement.
final Map<int, PosMoney> tipShares = <int, PosMoney>{};
