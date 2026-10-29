/// P6-19 "Osh markazi tushligi" against the real stack: an osh markazi (profile `osh_markazi`, its own store in the seed)
/// at lunch. Two tills sell over the counter, pay first and then the kitchen cooks (A4); every order gets a number from
/// its till's own block and a talon; the cook bumps the lines on the kitchen display, the "ready numbers" board on the
/// wall shows the number and the till hands it over (one is called again first). The first kazan runs out in the middle:
/// the dish is refused and shows the next batch, the cook opens the second kazan; part of the rush is sold on the
/// second till while it is offline and syncs afterwards. At the end the cashier writes the leftover off with the
/// manager's PIN, both tills close Z with the bank terminal's day total (one with a difference over the threshold), and
/// the yield report adds up (produced = sold + written off + left, variance 0).
///
/// The plan's load figure is "300 orders in 3 hours"; the run keeps its shape (two tills at once, a kazan running out,
/// an offline till) at 60 orders, which takes a few minutes. Everything goes through pos_data, the apps' own data layer.
@Timeout(Duration(minutes: 10))
library;

import 'dart:async';
import 'dart:io';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/kitchen.dart';
import 'support/menu.dart';
import 'support/stack.dart';

const String oshName = "To'y oshi";

/// Portions of the two kazans; the till sells at most [kazan2Sold] of the second (the rest is written off at the end).
const int kazan1 = 30;
const int kazan2 = 40;
const int kazan2Sold = 28;

/// The run: 60 counter orders; the second till is offline for [offlineOrders] of its own.
const int totalOrders = 60;
const int offlineOrders = 6;

/// The branch's bank-terminal difference threshold (P6-16): above it the owner is warned and Z shows a row.
const PosMoney threshold = PosMoney.som(10000);

Map<String, Object?> asMap(Object? value) =>
    (value! as Map<Object?, Object?>).cast<String, Object?>();

List<Map<String, Object?>> asList(Object? value) => <Map<String, Object?>>[
  for (final Object? e in value! as List<Object?>) asMap(e),
];

PosMoney money(Object? value) => PosMoney.parse(value);

PosMoney tenderAmount(Map<String, Object?> report, String tender) {
  for (final Map<String, Object?> r in asList(report['by_tender'])) {
    if (r['tender'] == tender) return money(r['amount']);
  }
  return PosMoney.zero;
}

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

/// The cable of a till: while [cut], every request fails as if the internet were gone (the real-time socket stays).
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

/// One counter sale as the test plans it, and what happened to it.
class Sale {
  Sale(
    this.index,
    this.dishes, {
    required this.card,
    this.cardType,
    this.rrn,
    this.last4,
  });

  final int index;
  final List<Dish> dishes;
  final bool card;
  final String? cardType;
  final String? rrn;
  final String? last4;

  int get osh => <Dish>[
    for (final Dish d in dishes)
      if (d.name == oshName) d,
  ].fold<int>(0, (int s, Dish d) => s + d.quantity.milli ~/ 1000);

  // Filled in by the till
  late Till till;
  bool offline = false;
  String? localUuid;
  late String uuid;
  late int number;
  late PosMoney total;
  late List<PosTenderInput> tenders;
  PosBill? bill;
}

/// A till of the osh markazi: the terminal, its journal (queue numbers, offline mode), its menu and its shift.
class Till {
  Till(this.terminal, this.store, this.cable);

  final Terminal terminal;
  final SyncStore store;
  final Cable cable;
  late Menu menu;
  late PosShift shift;
  late PosQueueRepository queue;
  final List<Sale> sales = <Sale>[];

  PosData get pos => terminal.pos;
  String get label => terminal.label;
  PosQueueState get queueState => terminal.bootstrap.queue;

  /// "To'lash": a number from the till's block, the order opened and sent to the kitchen, one bill, paid; the talon.
  Future<Sale> sell(Sale sale, {bool offline = false}) async {
    sale.till = this;
    sale.offline = offline;
    final String date = queueState.businessDate!;
    final int? number = await store.takeQueueNumber(date, queueState.blocks);
    expect(number, isNotNull, reason: '$label has numbers left in its block');
    final PosOrder order = await pos.orders.open(
      channel: PosChannel.takeaway,
      items: menu.inputs(sale.dishes),
      send: true,
      queueNumber: number,
    );
    sale.total = menu.total(sale.dishes);
    if (offline) {
      sale.localUuid = order.uuid;
      sale.number = number!;
    } else {
      sale.uuid = order.uuid;
      expect(
        order.queue,
        isNotNull,
        reason: 'a counter order of an osh markazi is numbered',
      );
      expect(
        order.queue!.number,
        number,
        reason: 'the server keeps the number the till took from its block',
      );
      expect(order.queue!.status, PosPickupStatus.preparing);
      sale.number = order.queue!.number;
      await store.noteQueueNumber(date, sale.number);
    }

    final PosBilling billing = await pos.billing.createBills(
      order.uuid,
      version: order.version,
    );
    final PosBill bill = billing.bills.firstWhere(
      (PosBill b) => !b.isPaid && b.status != 'void',
    );
    expect(
      bill.total,
      sale.total,
      reason: 'no service charge at the counter: the bill is the dishes',
    );
    sale.tenders = <PosTenderInput>[
      if (sale.card)
        PosTenderInput.card(
          sale.total,
          cardType: sale.cardType!,
          transactionId: sale.rrn!,
          cardLast4: sale.last4,
        )
      else
        PosTenderInput.cash(
          sale.total,
          tendered: PosMoney.som(
            (sale.total.tiyin / 100 / 10000).ceil() * 10000,
          ),
        ),
    ];
    final PosBillResult paid = await pos.billing.pay(
      bill.uuid,
      tenders: sale.tenders,
      shiftUuid: shift.uuid,
    );
    if (!offline) {
      // Pay first, then the kitchen: the order is closed and paid, its number is still being cooked
      expect(paid.bill.isPaid, isTrue);
      expect(paid.order.status, 'delivered');
      final PosQueueTicket? talon = paid.bill.ticket;
      expect(talon, isNotNull, reason: 'the receipt carries the talon');
      expect(talon!.number, sale.number);
      expect(talon.message, isNotEmpty);
      if (sale.card) {
        final PosPayment p = paid.bill.payments.single;
        expect(p.tender, 'card');
        expect(
          p.transactionId,
          sale.rrn,
          reason: 'the RRN of the bank terminal is on the payment',
        );
        expect(p.cardLast4, sale.last4);
      }
      sale.bill = paid.bill;
    }
    sales.add(sale);
    return sale;
  }
}

void main() {
  late StackConfig config;
  late Seed seed;
  late Dio owner;
  late Till till1;
  late Till till2;
  late Terminal kds;
  late Terminal display;
  late EventLog board;
  late CookWorker cook;
  late Staff manager;
  final Random random = Random(619);
  final List<Sale> all = <Sale>[];
  var made = 0;
  // The portions the tills may still sell from the open kazan (what the cashier sees on the button)
  var oshLeft = kazan1;
  late int kazan1Id;
  late int kazan2Id;
  late String plan2Time;
  final List<Duration> boardDelays = <Duration>[];

  ({int productId, int variantId}) product(String name) => seed.product(name);

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

  /// The cook's own calls (PIN session on the kitchen display): kazans and plans.
  Future<Map<String, Object?>> cookCall(
    String method,
    String path, [
    Map<String, Object?>? body,
  ]) async {
    final Response<Object?> r = await kds.client.dio.request<Object?>(
      path,
      data: body,
      options: Options(method: method),
    );
    return asMap(asMap(r.data)['data']);
  }

  /// The next planned sale (deterministic): osh from the open kazan while it has portions, salads, mastava, bread and
  /// tea; a card (Uzcard or Humo, the terminal's RRN and mostly the last 4 digits) about every third sale.
  Sale plan({int? wantOsh}) {
    made++;
    final int want =
        wantOsh ?? <int>[0, 1, 1, 1, 2, 2, 0, 1][random.nextInt(8)];
    final int osh = min(want, oshLeft);
    oshLeft -= osh;
    final List<Dish> dishes = <Dish>[
      if (osh > 0) Dish.of(oshName, osh),
      if (random.nextInt(10) < 4) Dish.of('Achichuk', 1),
      if (random.nextInt(10) < 3) Dish.of('Mastava', 1),
      if (random.nextInt(10) < 6) Dish.of('Non', 1 + random.nextInt(2)),
      if (random.nextInt(10) < 5) Dish.of('Choy', 1),
    ];
    if (dishes.isEmpty) dishes.add(Dish.of('Non', 1));
    final bool card = random.nextInt(3) == 0;
    return Sale(
      made,
      dishes,
      card: card,
      cardType: card ? (random.nextBool() ? 'uzcard' : 'humo') : null,
      rrn: card ? '${600000000000 + random.nextInt(99999999)}' : null,
      last4: card && random.nextInt(4) > 0
          ? '${1000 + random.nextInt(9000)}'
          : null,
    );
  }

  /// The board heard the number ready, the till hands it over (every fifth one is called again first) and the board
  /// hears it handed.
  Future<void> handOver(Iterable<Sale> sales) async {
    for (final Sale s in sales) {
      final HeardEvent ready = await board.waitFor(
        (PosEvent e) =>
            e is PosQueueEvent &&
            e.number == s.number &&
            e.name == 'queue.ready' &&
            !e.called,
        timeout: const Duration(seconds: 60),
        what: 'number ${s.number} ready on the board',
      );
      expect((ready.event as PosQueueEvent).announce, isTrue);
      final List<HeardEvent> preparing = board.where(
        (PosEvent e) =>
            e is PosQueueEvent &&
            e.number == s.number &&
            e.name == 'queue.preparing',
      );
      if (preparing.isNotEmpty) {
        boardDelays.add(ready.at.difference(preparing.first.at));
      }
      if (s.index % 5 == 0) {
        await s.till.queue.call(s.uuid);
        final HeardEvent again = await board.waitFor(
          (PosEvent e) =>
              e is PosQueueEvent &&
              e.number == s.number &&
              e.name == 'queue.ready' &&
              e.called,
          what: 'number ${s.number} called again',
        );
        expect((again.event as PosQueueEvent).callCount, 1);
      }
      final PosQueueEntry handed = await s.till.queue.handed(s.uuid);
      expect(handed.status, PosPickupStatus.handed);
      await board.waitFor(
        (PosEvent e) =>
            e is PosQueueEvent &&
            e.number == s.number &&
            e.name == 'queue.handed',
        what: 'number ${s.number} handed',
      );
    }
  }

  /// Both tills sell their part of [sales] at the same time, each one after the other.
  Future<void> rush(
    List<Sale> forTill1,
    List<Sale> forTill2, {
    bool till2Offline = false,
  }) async {
    await Future.wait(<Future<void>>[
      () async {
        for (final Sale s in forTill1) {
          all.add(await till1.sell(s));
        }
      }(),
      () async {
        for (final Sale s in forTill2) {
          all.add(await till2.sell(s, offline: till2Offline));
        }
      }(),
    ]);
  }

  setUpAll(() async {
    final StackConfig stack = StackConfig.fromEnvironment();
    seed = stack.seed.section('osh');
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

    // The owner chooses the format in the panel: osh markazi, with the kitchen display, the customer display and the
    // daily menu switched on over the profile
    final Map<String, Object?> format = await ownerCall(
      'PUT',
      '/v1/owner/pos/format',
      <String, Object?>{
        'branch_id': seed.branchId,
        'profile': 'osh_markazi',
        'modules': <String, Object?>{
          'kds': true,
          'customer_display': true,
          'daily_menu': true,
        },
      },
    );
    expect(format['profile'], 'osh_markazi');
    // Card payments on the bank's own terminal in "faqat terminal" mode; Z asks for the terminal's day total
    await ownerCall(
      'PUT',
      '/v1/owner/branches/${seed.branchId}/terminal-flow',
      <String, Object?>{
        'card_mode': 'terminal_only',
        'difference_threshold': threshold.toApiString(),
        'ask_bank_total': true,
      },
    );
    // Mastava and sho'rva are daily dishes on this branch; today's menu has mastava, not sho'rva
    await ownerCall('PUT', '/v1/owner/daily-menu/rotation', <String, Object?>{
      'branch_id': seed.branchId,
      'product_ids': <int>[
        product('Mastava').productId,
        product("Sho'rva").productId,
      ],
    });
    await ownerCall('PUT', '/v1/owner/daily-menu', <String, Object?>{
      'branch_id': seed.branchId,
      'product_ids': <int>[product('Mastava').productId],
    });

    Future<Till> openTill(String key, String staff) async {
      final Cable cable = Cable();
      final SyncStore store = await SyncStore.open(MemorySyncStorage());
      final Terminal t = await Terminal.open(config, key, offlineStore: store);
      t.client.dio.interceptors.add(cable.interceptor);
      await t.signIn(staff);
      final Till till = Till(t, store, cable)
        ..queue = PosQueueRepository(t.pos.http);
      till.menu = Menu(
        await t.pos.catalog.load(
          PosChannel.takeaway,
          menuVersion: t.bootstrap.menuVersion,
        ),
      );
      await t.listen();
      return till;
    }

    // One after the other: the first till to load its bootstrap gets the first block of the day
    till1 = await openTill('till1', 'cashier1');
    till2 = await openTill('till2', 'cashier2');
    kds = await Terminal.open(config, 'kds');
    await kds.signIn('cook');
    display = await Terminal.open(config, 'display');
    board = await display.listen();
    cook = CookWorker(kds);
  });

  tearDownAll(() async {
    await cook.stop();
    for (final Terminal t in <Terminal>[
      till1.terminal,
      till2.terminal,
      kds,
      display,
    ]) {
      await t.close();
    }
    owner.close();
    if (boardDelays.isNotEmpty) {
      final List<Duration> sorted = List<Duration>.of(boardDelays)..sort();
      stdout.writeln(
        '\n[pos-e2e] osh markazi: ${all.length} orders, preparing → ready on the board median '
        '${sorted[(sorted.length - 1) ~/ 2].inMilliseconds} ms, max ${sorted.last.inMilliseconds} ms',
      );
    }
  });

  group('Osh markazi tushligi', () {
    test('the format is osh markazi: quick counter first, numbers, calling, kazans and the bank terminal flow', () async {
      for (final Till t in <Till>[till1, till2]) {
        final PosFormat f = t.terminal.bootstrap.format;
        expect(f.profile, 'osh_markazi');
        expect(f.firstScreen, PosFirstScreen.quickCounter);
        expect(
          f.modules.hasAll(<PosModule>[
            PosModule.quickCounter,
            PosModule.queueNumbers,
            PosModule.queueCalling,
            PosModule.batches,
            PosModule.batchWriteoff,
            PosModule.terminalCardFlow,
            PosModule.kds,
            PosModule.customerDisplay,
            PosModule.dailyMenu,
          ]),
          isTrue,
          reason: '${t.label}: ${f.modules.enabled}',
        );
        expect(
          f.modules.has(PosModule.rooms),
          isFalse,
          reason: 'no rooms in an osh markazi',
        );
        final PosTerminalFlow flow = t.terminal.bootstrap.terminalFlow!;
        expect(flow.cardMode, PosCardMode.terminalOnly);
        expect(flow.askBankTotal, isTrue);
        // (The threshold itself is the owner's: the Z answer below carries it)
      }

      // Each till has its own block of today's numbers; the blocks never overlap and start the day from 1
      final PosQueueState q1 = till1.queueState;
      final PosQueueState q2 = till2.queueState;
      expect(q1.numbers && q2.numbers, isTrue);
      expect(q1.businessDate, isNotNull);
      expect(q2.businessDate, q1.businessDate);
      expect(q1.blocks, isNotEmpty);
      expect(q2.blocks, isNotEmpty);
      expect(
        q1.blocks.first.start,
        1,
        reason: 'the day starts from 1 at the first till',
      );
      expect(
        q2.blocks.first.start,
        q1.blockSize + 1,
        reason: 'the second till gets the next block',
      );
      for (final QueueRange a in q1.blocks) {
        for (final QueueRange b in q2.blocks) {
          expect(
            a.end < b.start || b.end < a.start,
            isTrue,
            reason: '$a and $b overlap',
          );
        }
      }

      // The display is a staff-free device on the same branch: an empty board to start with
      final PosQueueBoard empty = await display.pos.display.queue();
      expect(empty.preparing, isEmpty);
      expect(empty.ready, isEmpty);
      expect(empty.businessDate, q1.businessDate);
    });

    test('today\'s menu: a daily dish that is not on it is refused, one that is on it is sold', () async {
      final PosCatalog catalog = till1.menu.catalog;
      expect(catalog.isOffMenu(till1.menu.product("Sho'rva")), isTrue);
      expect(catalog.isOffMenu(till1.menu.product('Mastava')), isFalse);
      await expectLater(
        till1.pos.orders.open(
          channel: PosChannel.takeaway,
          items: till1.menu.inputs(<Dish>[Dish.of("Sho'rva", 1)]),
          send: true,
        ),
        throwsA(
          isA<PosException>()
              .having((PosException e) => e.statusCode, 'status', 422)
              .having(
                (PosException e) => e.message,
                'message',
                contains("Bugungi menyuda yo'q"),
              ),
        ),
      );
    });

    test('two kazans are planned and the cook opens the first; the shifts open on both tills', () async {
      final DateTime now = DateTime.now().toUtc();
      final ({int productId, int variantId}) osh = product(oshName);
      await cookCall('POST', '/v1/owner/batch-plans', <String, Object?>{
        'product_id': osh.productId,
        'planned_at': now.add(const Duration(minutes: 2)).toIso8601String(),
        'quantity': '$kazan1',
        'label': '1-qozon',
      });
      final Map<String, Object?> plan2 = await cookCall(
        'POST',
        '/v1/owner/batch-plans',
        <String, Object?>{
          'product_id': osh.productId,
          'planned_at': now.add(const Duration(minutes: 50)).toIso8601String(),
          'quantity': '$kazan2',
          'label': '2-qozon',
        },
      );
      plan2Time = plan2['time']! as String;

      final Map<String, Object?> first = await cookCall(
        'POST',
        '/v1/owner/batches',
        <String, Object?>{
          'product_id': osh.productId,
          'quantity': '$kazan1',
          'label': '1-qozon',
        },
      );
      kazan1Id = (first['id']! as num).toInt();
      expect(first['quantity_left'], kazan1);
      // The kazan took the nearest plan: only the second one is still ahead
      final Map<String, Object?> plans = await cookCall(
        'GET',
        '/v1/owner/batch-plans',
      );
      expect(
        asList(plans['plans']).map((Map<String, Object?> p) => p['label']),
        <String>['2-qozon'],
      );

      for (final Till t in <Till>[till1, till2]) {
        t.shift = await t.pos.shifts.open(
          openingCash: const PosMoney.som(300000),
          note: 'Tushlik',
        );
        t.pos.session.updateShift(t.shift.toSummary());
      }
    });

    test('the first sale: paid first, the number on the talon, the ticket on the kitchen display, the board shows it', () async {
      final Sale s = await till1.sell(plan(wantOsh: 2));
      all.add(s);
      // Paid and closed, still cooking: the kitchen display shows it as a normal ticket with its number
      final KitchenTicket ticket = (await kds.client.kitchen.tickets()).tickets
          .firstWhere((KitchenTicket t) => t.uuid == s.uuid);
      expect(ticket.queueNumber, s.number);
      expect(ticket.pickupStatus, 'preparing');
      expect(ticket.lines, isNotEmpty);
      await board.waitFor(
        (PosEvent e) =>
            e is PosQueueEvent &&
            e.number == s.number &&
            e.name == 'queue.preparing',
        what: 'queue.preparing',
      );
      final PosQueueBoard now = await display.pos.display.queue();
      expect(
        now.preparing.map((PosQueueEntry e) => e.number),
        contains(s.number),
      );

      // From here the cook bumps every line that reaches the display
      cook.start();
      await handOver(<Sale>[s]);
    });

    test('the rush: both tills at once, then the second till loses the internet, keeps selling and syncs', () async {
      // Both tills online
      final List<Sale> a = <Sale>[for (var i = 0; i < 16; i++) plan()];
      await rush(
        <Sale>[for (var i = 0; i < a.length; i += 2) a[i]],
        <Sale>[for (var i = 1; i < a.length; i += 2) a[i]],
      );
      await handOver(a);

      // The cable of the second till is out: its sales go to its journal with numbers from its own block
      till2.cable.cut = true;
      till2.pos.connectivity.reportFailure(
        const SocketException('e2e: the cable is out'),
      );
      expect(till2.pos.offline!.active, isTrue);
      final List<Sale> online = <Sale>[
        for (var i = 0; i < offlineOrders; i++) plan(),
      ];
      final List<Sale> offline = <Sale>[
        for (var i = 0; i < offlineOrders; i++) plan(),
      ];
      await rush(online, offline, till2Offline: true);
      expect(till2.store.pendingOrders, offlineOrders);
      expect(
        oshLeft,
        greaterThanOrEqualTo(0),
        reason: 'the tills never planned more than the kazan has',
      );
      await handOver(online);

      // The cable is back: the journal goes to the server in order; the server keeps every number
      till2.cable.cut = false;
      till2.pos.connectivity.reportReachable();
      final SyncDrainResult sync = await till2.pos.offline!.syncNow();
      expect(sync.error, isNull);
      expect(sync.left, 0);
      expect(till2.pos.offline!.active, isFalse);
      final Map<String, SyncOperation> opened = <String, SyncOperation>{
        for (final SyncOperation op in till2.store.answered)
          if (op.type == SyncOpType.orderOpen)
            op.payload['order']! as String: op,
      };
      for (final Sale s in offline) {
        final SyncOperation op = opened[s.localUuid]!;
        expect(
          op.status,
          SyncOpStatus.applied,
          reason: '${op.reason} ${op.message}',
        );
        expect(
          op.refs['queue_number'],
          s.number,
          reason: 'the offline number is kept (it is in the till\'s block)',
        );
        s.uuid = till2.store.resolve(s.localUuid!);
        expect(s.uuid, isNot(s.localUuid));
        final PosOrder synced = await till2.pos.orders.get(s.uuid);
        expect(
          synced.status,
          'delivered',
          reason: 'paid offline, closed on sync',
        );
        expect(synced.queue!.number, s.number);
        await till2.store.noteQueueNumber(
          till2.queueState.businessDate!,
          s.number,
        );
      }
      for (final SyncOperation op in till2.store.answered.where(
        (SyncOperation o) => o.type == SyncOpType.billPay,
      )) {
        expect(
          op.status,
          SyncOpStatus.applied,
          reason: '${op.reason} ${op.message}',
        );
      }
      // Synced orders reach the kitchen display like the others and are cooked, called and handed
      await handOver(offline);
    });

    test('the first kazan runs out: the dish is refused and shows the next batch; the cook opens the second kazan', () async {
      // The till sells the last portions of the first kazan
      final List<Sale> last = <Sale>[];
      while (oshLeft > 0) {
        final Sale s = plan(wantOsh: min(2, oshLeft));
        last.add(s);
        all.add(await till1.sell(s));
      }
      await handOver(last);
      expect(all.fold<int>(0, (int n, Sale s) => n + s.osh), kazan1);

      // Every till heard the dish sell out over the branch channel
      final ({int productId, int variantId}) osh = product(oshName);
      await till2.terminal.log!.waitFor(
        (PosEvent e) =>
            e is PosMenuAvailabilityChanged &&
            e.items.any((i) => i.id == osh.variantId && !i.available),
        what: 'the kazan sold out (menu.availability_changed)',
      );
      // One more portion is refused ("tugadi")...
      Object? refused;
      try {
        await till1.pos.orders.open(
          channel: PosChannel.takeaway,
          items: till1.menu.inputs(<Dish>[Dish.of(oshName, 1)]),
          send: true,
        );
      } on Object catch (e) {
        refused = e;
      }
      expect(
        refused,
        isA<PosException>().having(
          (PosException e) => e.statusCode,
          'status',
          422,
        ),
      );
      expect((refused! as PosException).message, contains('tugadi'));
      // ... and the menu shows when the next one comes
      await till1.pos.catalog.refreshAvailability(
        storeId: seed.storeId,
        branchId: seed.branchId,
      );
      final PosCatalog catalog = till1.pos.catalog.cached(PosChannel.takeaway)!;
      final PosProduct dish = catalog.products.firstWhere(
        (PosProduct p) => p.name == oshName,
      );
      expect(catalog.isProductAvailable(dish), isFalse);
      expect(
        catalog.nextBatchOf(dish)?.time,
        plan2Time,
        reason: '"keyingi partiya $plan2Time"',
      );

      // The cook closes the empty kazan and opens the second: the dish is back on both tills
      await cookCall('POST', '/v1/owner/batches/$kazan1Id/close');
      final Map<String, Object?> second = await cookCall(
        'POST',
        '/v1/owner/batches',
        <String, Object?>{
          'product_id': osh.productId,
          'quantity': '$kazan2',
          'label': '2-qozon',
        },
      );
      kazan2Id = (second['id']! as num).toInt();
      await till2.terminal.log!.waitFor(
        (PosEvent e) =>
            e is PosMenuAvailabilityChanged &&
            e.items.any((i) => i.id == osh.variantId && i.available),
        what: 'the second kazan opened (menu.availability_changed)',
      );
      final Map<String, Object?> plans = await cookCall(
        'GET',
        '/v1/owner/batch-plans',
      );
      expect(
        asList(plans['plans']),
        isEmpty,
        reason: 'the second kazan took the second plan',
      );
      await till1.pos.catalog.refreshAvailability(
        storeId: seed.storeId,
        branchId: seed.branchId,
      );
      expect(
        till1.pos.catalog.cached(PosChannel.takeaway)!.isProductAvailable(dish),
        isTrue,
      );
      oshLeft = kazan2Sold;
    });

    test(
      'the rest of the rush is sold from the second kazan on both tills',
      () async {
        final int left = totalOrders - all.length;
        expect(left, greaterThan(10));
        final List<Sale> d = <Sale>[for (var i = 0; i < left; i++) plan()];
        await rush(
          <Sale>[for (var i = 0; i < d.length; i += 2) d[i]],
          <Sale>[for (var i = 1; i < d.length; i += 2) d[i]],
        );
        await handOver(d);
        expect(all, hasLength(totalOrders));

        // The board is empty again: every number was handed over
        final PosQueueBoard end = await display.pos.display.queue();
        expect(end.ready, isEmpty);
        expect(end.preparing, isEmpty);
        expect(cook.refused, isEmpty);
      },
    );

    test('no number twice: each till numbered from its own block, all on today\'s business day', () async {
      final List<int> numbers = <int>[for (final Sale s in all) s.number];
      expect(
        numbers.toSet(),
        hasLength(numbers.length),
        reason: 'duplicate numbers: $numbers',
      );
      for (final Till t in <Till>[till1, till2]) {
        final List<int> own = <int>[for (final Sale s in t.sales) s.number];
        expect(own, isNotEmpty);
        for (final int n in own) {
          expect(
            t.queueState.blocks.any(
              (QueueRange r) => r.start <= n && n <= r.end,
            ),
            isTrue,
            reason: '${t.label}: $n is outside its blocks',
          );
        }
        expect(
          own.first,
          t.queueState.blocks.first.start,
          reason: '${t.label} starts the day at its block',
        );
        final List<int> sorted = List<int>.of(own)..sort();
        expect(own, sorted, reason: '${t.label} hands numbers out in order');
      }
      // The server's own view of the day agrees
      final PosQueueBoard today = await till1.queue.board();
      expect(today.businessDate, till1.queueState.businessDate);
    });

    test('the cashier writes the leftover of the second kazan off with the manager\'s PIN', () async {
      final int sold2 = all.fold<int>(0, (int n, Sale s) => n + s.osh) - kazan1;
      final int leftover = kazan2 - sold2;
      expect(leftover, greaterThanOrEqualTo(kazan2 - kazan2Sold));
      expect(
        till1.pos.session.can(PosPermissions.batchesManage),
        isFalse,
        reason: 'a cashier has no batches.manage',
      );

      // Without the PIN the server asks for it
      await expectLater(
        till1.pos.batches.writeOff(
          kazan2Id,
          quantity: PosQuantity.whole(leftover),
          reason: PosWriteOffReason.unsold,
          close: true,
        ),
        throwsA(
          isA<PosApprovalRequired>().having(
            (PosApprovalRequired e) => e.permission,
            'permission',
            PosPermissions.batchesManage,
          ),
        ),
      );
      // The manager types the PIN on the till: written off once, the kazan closed, on the cashier's shift
      var prompts = 0;
      final PosBatchWriteOff done = await runWithApproval<PosBatchWriteOff>(
        (String? token) => till1.pos.batches.writeOff(
          kazan2Id,
          quantity: PosQuantity.whole(leftover),
          reason: PosWriteOffReason.unsold,
          close: true,
          approvalToken: token,
        ),
        approve: (String permission, PosException cause) {
          prompts++;
          return till1.pos.approvals.request(
            permission: permission,
            userId: manager.id,
            pin: manager.pin!,
          );
        },
      );
      expect(prompts, 1);
      expect(done.approvedBy, manager.id);
      expect(done.quantity, PosQuantity.whole(leftover));
      expect(done.shiftId, isNotNull);
      expect(done.batch!.quantityLeft, PosQuantity.zero);
    });

    test('Z on each till with the bank terminal\'s day total; the second one is short and warned', () async {
      for (final Till t in <Till>[till1, till2]) {
        final PosMoney card = PosMoney.sum(<PosMoney>[
          for (final Sale s in t.sales)
            if (s.card) s.total,
        ]);
        final PosMoney cash = PosMoney.sum(<PosMoney>[
          for (final Sale s in t.sales)
            if (!s.card) s.total,
        ]);
        final PosShiftWithReport x = await t.pos.shifts.xReport(t.shift.uuid);
        expect(
          tenderAmount(x.report!.raw, 'card'),
          card,
          reason: '${t.label}: card on the X report',
        );
        expect(
          tenderAmount(x.report!.raw, 'cash'),
          cash,
          reason: '${t.label}: cash on the X report',
        );
        final PosMoney expected = x.report!.expectedCash;
        expect(expected, const PosMoney.som(300000) + cash);

        // The bank terminal's settlement: right on the first till, 15 000 short on the second (over the threshold)
        final bool short = t == till2;
        final PosMoney bank = short ? card - const PosMoney.som(15000) : card;
        final PosShift z = await t.pos.shifts.close(
          t.shift.uuid,
          countedBreakdown: banknotes(expected),
          bankTerminal: PosBankTerminalInput(
            total: bank,
            count: t.sales.where((Sale s) => s.card).length,
            terminalRef: 'TID-${t.label}',
          ),
        );
        expect(z.status, 'closed');
        expect(
          z.difference,
          PosMoney.zero,
          reason: '${t.label}: the drawer is right',
        );
        final PosBankTerminalReport report = z.bankTerminal!;
        expect(report.rizqimTotal, card);
        expect(report.bankTotal, bank);
        expect(report.difference, bank - card);
        expect(report.threshold, threshold);
        expect(
          report.overThreshold,
          short,
          reason: '${t.label}: the difference warning',
        );
        if (t == till1) {
          expect(
            z.summary!.batchWriteoffs.count,
            1,
            reason: 'the write-off is on the first till\'s Z',
          );
        }
        t.pos.session.updateShift(null);
      }
    });

    test('the yield report adds up: produced = sold + written off + left, no variance', () async {
      final String date = till1.queueState.businessDate!;
      final Response<Object?> r = await owner.get<Object?>(
        '/v1/owner/batch-yield',
        queryParameters: <String, Object?>{
          'branch_id': seed.branchId,
          'from': date,
          'to': date,
        },
      );
      final Map<String, Object?> data = asMap(asMap(r.data)['data']);
      final List<Map<String, Object?>> batches = asList(data['batches']);
      expect(batches, hasLength(2));
      final int sold = all.fold<int>(0, (int n, Sale s) => n + s.osh);
      num n(Object? v) => v! as num;
      final Map<int, Map<String, Object?>> byId = <int, Map<String, Object?>>{
        for (final Map<String, Object?> b in batches)
          n(b['batch_id'] ?? b['id']).toInt(): b,
      };
      final Map<String, Object?> first = byId[kazan1Id]!;
      final Map<String, Object?> second = byId[kazan2Id]!;
      expect(n(first['produced']), kazan1);
      expect(n(first['sold']), kazan1);
      expect(n(first['written_off']), 0);
      expect(n(first['left']), 0);
      expect(n(second['produced']), kazan2);
      expect(n(second['sold']), sold - kazan1);
      expect(n(second['written_off']), kazan2 - (sold - kazan1));
      expect(n(second['left']), 0);
      for (final Map<String, Object?> b in batches) {
        expect(n(b['variance']), 0, reason: 'variance of $b');
        expect(n(b['oversold']), 0);
        expect(
          n(b['produced']),
          n(b['sold']) + n(b['written_off']) + n(b['left']) + n(b['variance']),
        );
      }
      final List<Map<String, Object?>> days = asList(data['days']);
      final Map<String, Object?> day = days.single;
      expect(n(day['produced']), kazan1 + kazan2);
      expect(n(day['sold']), sold);
      expect(
        n(day['written_off']) + n(day['sold']) + n(day['left']),
        kazan1 + kazan2,
      );
      expect(n(day['variance']), 0);
    });
  });
}
