/// P6-14, against the real stack: a customer display (device type `display`, free, no staff) is paired with a code the owner
/// makes, follows the till's cart over the branch channel and shows the pickup board. Each change is timed from the request
/// to the moment the display hears it (limit: 2 seconds).
///
/// The seeded choyxona has no counter formats on, so the owner switches the modules on over the profile and, whatever
/// happens, back again at the end (the other files run on the same branch).
@Timeout(Duration(minutes: 3))
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

/// The limit of the brief: the display is up to date within two seconds of the change.
const Duration limit = Duration(seconds: 2);

void main() {
  late StackConfig config;
  late Terminal till;
  late Terminal kds;
  late Menu menu;
  late Staff manager;
  late Dio owner;
  late RizqimClient displayClient;
  late PosData display;
  late PosRealtime displayRealtime;
  late EventLog heard;
  late PosQueueRepository queue;
  final List<Duration> latencies = <Duration>[];
  String? orderUuid;

  Future<void> modules(Map<String, bool?> switches) async {
    await owner.put<Object?>(
      '/v1/owner/pos/format',
      data: <String, Object?>{
        'branch_id': config.seed.branchId,
        'modules': switches,
      },
    );
  }

  setUpAll(() async {
    config = StackConfig.fromEnvironment();
    till = await Terminal.open(config, 'cashier');
    kds = await Terminal.open(config, 'kds');
    manager = config.seed.staff('manager');
    await till.signIn('cashier');
    await kds.signIn('cook');
    menu = Menu(
      await till.pos.catalog.load(
        PosChannel.takeaway,
        menuVersion: till.bootstrap.menuVersion,
      ),
    );
    owner = Dio(
      BaseOptions(
        baseUrl: config.apiUrl,
        headers: <String, Object?>{
          'Authorization': 'Bearer ${config.seed.ownerToken}',
          'Accept': 'application/json',
        },
      ),
    );
    await modules(<String, bool?>{
      'queue_numbers': true,
      'queue_calling': true,
      'customer_display': true,
    });

    // The owner makes a display code for the till's branch (following the seeded till); the display pairs with it
    final String tillUuid =
        ((config.seed.terminal('cashier')['device']!)
                as Map<Object?, Object?>)['uuid']!
            as String;
    final Response<Object?> made = await owner.post<Object?>(
      '/v1/owner/devices/pairing-codes',
      data: <String, Object?>{
        'type': 'display',
        'branch_id': config.seed.branchId,
        'name': 'Ikkinchi ekran (sinov)',
        'till_uuid': tillUuid,
      },
    );
    final String code =
        (((made.data! as Map<Object?, Object?>)['data']!)
                as Map<Object?, Object?>)['code']!
            as String;
    displayClient = RizqimClient(
      baseUrl: config.apiUrl,
      store: MemoryKeyValueStore(),
    );
    await displayClient.auth.pairTerminal(
      code,
      name: 'Mijoz ekrani',
      platform: 'e2e',
      appVersion: '0.1.0',
    );
    display = PosData(displayClient);

    // The display listens on the branch channel with its own terminal token, like the app
    displayRealtime = PosRealtime.create(
      client: displayClient,
      terminal: displayClient.auth.terminal!,
      apiBaseUrl: config.apiUrl,
      appKey: config.reverbKey,
      reverbUrl: config.reverbUrl,
      clientName: 'pos-e2e-display',
    )!;
    heard = EventLog(displayRealtime);
    displayRealtime.start();
    await displayRealtime.states
        .firstWhere((PosLiveState s) => s == PosLiveState.live)
        .timeout(const Duration(seconds: 15));
    queue = PosQueueRepository(till.pos.http);
  });

  tearDownAll(() async {
    // Whatever happened above: the open order is cancelled and the modules go back to the profile's own
    try {
      final String? uuid = orderUuid;
      if (uuid != null) {
        final PosOrder now = await till.pos.orders.get(uuid);
        if (now.status != 'canceled' && now.status != 'delivered') {
          await runWithApproval<PosOrder>(
            (String? token) => till.pos.orders.cancel(
              uuid,
              version: now.version,
              reason: 'Sinov tugadi',
              approvalToken: token,
            ),
            approve: (String permission, PosException cause) =>
                till.pos.approvals.request(
                  permission: permission,
                  userId: manager.id,
                  pin: manager.pin!,
                ),
          );
        }
      }
    } on Object catch (e) {
      stderr.writeln('[pos-e2e] display: cleanup of the order failed: $e');
    }
    try {
      await modules(<String, bool?>{
        'queue_numbers': null,
        'queue_calling': null,
        'customer_display': null,
      });
    } on Object catch (e) {
      stderr.writeln(
        '[pos-e2e] display: could not switch the modules back: $e',
      );
    }
    await displayRealtime.dispose();
    await heard.dispose();
    await display.dispose();
    await displayClient.auth.dispose();
    await till.close();
    await kds.close();
    owner.close();
    if (latencies.isNotEmpty) {
      final List<Duration> sorted = List<Duration>.of(latencies)..sort();
      stdout.writeln(
        '\n[pos-e2e] customer display: ${sorted.length} updates, median '
        '${sorted[(sorted.length - 1) ~/ 2].inMilliseconds} ms, max ${sorted.last.inMilliseconds} ms '
        '(limit ${limit.inSeconds} s)',
      );
    }
  });

  /// The display hears an event that satisfies [test] within [limit] of [sentAt]; the delay is recorded.
  Future<PosEvent> hears(
    bool Function(PosEvent e) test,
    DateTime sentAt,
    String what,
  ) async {
    final HeardEvent h = await heard.waitFor(
      (PosEvent e) => test(e),
      timeout: limit + const Duration(seconds: 3),
      what: what,
    );
    final Duration took = h.at.difference(sentAt);
    latencies.add(took);
    expect(
      took,
      lessThan(limit),
      reason:
          '$what: the display heard it ${took.inMilliseconds} ms after the change',
    );
    return h.event;
  }

  PosDisplayCart cart(
    PosDisplayScreen state, {
    PosMoney? total,
    PosDisplayPayment? payment,
    int? number,
  }) => PosDisplayCart(
    state: state,
    lines: <PosDisplayLine>[
      const PosDisplayLine(
        name: 'Shashlik',
        quantity: PosQuantity.whole(2),
        price: PosMoney.som(18000),
        total: PosMoney.som(36000),
        modifiers: <String>['Achchiq'],
      ),
      const PosDisplayLine(
        name: 'Osh',
        quantity: PosQuantity.milli(750),
        unit: 'kg',
        price: PosMoney.som(120000),
        total: PosMoney.som(90000),
      ),
    ],
    subtotal: const PosMoney.som(126000),
    total: total ?? const PosMoney.som(126000),
    queueNumber: number,
    payment: payment,
  );

  test('a display is a free device without staff: no PIN pad, no staff session, and the plan does not count it', () async {
    final PosDisplayState state = await display.display.state();
    expect(state.branchId, config.seed.branchId);
    expect(state.modules.customerDisplay, isTrue);
    expect(state.modules.queueCalling, isTrue);
    expect(state.tillUuid, isNotNull, reason: 'it follows the seeded till');
    expect(state.cart, isNull);

    // The display token cannot ask who may sign in, and cannot start a staff session
    await expectLater(
      displayClient.dio.get<Object?>('/v1/terminal/staff'),
      throwsA(
        isA<DioException>().having(
          (DioException e) => e.response?.statusCode,
          'status',
          403,
        ),
      ),
    );

    // The owner's plan numbers: displays are listed as free and unlimited, never as extras of kassa or KDS
    final Response<Object?> plan = await owner.get<Object?>(
      '/v1/owner/billing/entitlements',
    );
    final Map<Object?, Object?> devices =
        (((plan.data! as Map<Object?, Object?>)['data']!
                as Map<Object?, Object?>)['devices']!)
            as Map<Object?, Object?>;
    expect((devices['display']! as Map<Object?, Object?>)['unlimited'], isTrue);
    expect(
      (devices['display']! as Map<Object?, Object?>)['extra_price'],
      '0.00',
    );
    expect(
      (devices['pos']! as Map<Object?, Object?>)['extra'],
      0,
      reason: 'the display is not a till',
    );
  });

  test('the till\'s cart reaches the display within two seconds at every step: cart, payment with a QR, thanks, next customer', () async {
    final DisplayFeed feed = DisplayFeed(
      till.pos.display,
      debounce: const Duration(milliseconds: 150),
    );
    addTearDown(feed.dispose);

    // Dishes are added: the display refetches the state and shows the lines
    DateTime sentAt = DateTime.now();
    feed.publish(cart(PosDisplayScreen.cart, number: 1));
    await hears(
      (PosEvent e) => e is PosDisplayCartChanged && e.state == 'cart',
      sentAt,
      'cart',
    );
    PosDisplayState state = await display.display.state();
    expect(state.cart!.state, PosDisplayScreen.cart);
    expect(state.cart!.lines.map((PosDisplayLine l) => l.name), <String>[
      'Shashlik',
      'Osh',
    ]);
    expect(state.cart!.lines.first.modifiers, <String>['Achchiq']);
    expect(state.cart!.lines.last.quantity, const PosQuantity.milli(750));
    expect(state.cart!.total, const PosMoney.som(126000));
    expect(state.cart!.queueNumber, 1);
    expect(state.cartAge!, lessThan(const Duration(seconds: 5)));
    // The hint that crossed the staff channel carries no names or prices
    final PosDisplayCartChanged hint =
        heard
                .where(
                  (PosEvent e) =>
                      e is PosDisplayCartChanged && e.state == 'cart',
                )
                .last
                .event
            as PosDisplayCartChanged;
    expect(hint.data.toString(), isNot(contains('Shashlik')));
    expect(hint.data.toString(), isNot(contains('126000')));

    // The cashier presses "To'lash" and shows a Click QR
    sentAt = DateTime.now();
    feed.publish(
      cart(
        PosDisplayScreen.payment,
        number: 1,
        payment: const PosDisplayPayment(
          method: 'qr',
          qr: PosDisplayQr(
            payload:
                'https://my.click.uz/services/pay?service_id=1&amount=126000',
            label: 'Click',
            provider: 'click',
          ),
        ),
      ),
    );
    await hears(
      (PosEvent e) => e is PosDisplayCartChanged && e.state == 'payment',
      sentAt,
      'payment',
    );
    state = await display.display.state();
    expect(state.cart!.state, PosDisplayScreen.payment);
    expect(
      state.cart!.payment!.qr!.payload,
      startsWith('https://my.click.uz/'),
    );

    // Paid: "Rahmat"
    sentAt = DateTime.now();
    feed.publish(
      cart(
        PosDisplayScreen.paid,
        number: 1,
        payment: const PosDisplayPayment(
          method: 'cash',
          tendered: PosMoney.som(150000),
          change: PosMoney.som(24000),
        ),
      ),
    );
    await hears(
      (PosEvent e) => e is PosDisplayCartChanged && e.state == 'paid',
      sentAt,
      'paid',
    );
    state = await display.display.state();
    expect(state.cart!.payment!.change, const PosMoney.som(24000));

    // "Keyingi mijoz"
    sentAt = DateTime.now();
    feed.idle();
    await hears(
      (PosEvent e) => e is PosDisplayCartChanged && e.state == 'idle',
      sentAt,
      'idle',
    );
    state = await display.display.state();
    expect(state.cart!.state, PosDisplayScreen.idle);
    expect(state.cart!.lines, isEmpty);
    expect(feed.lastError, isNull);

    // Only a till publishes: the display's own token is refused
    await expectLater(
      display.display.publish(const PosDisplayCart.idle()),
      throwsA(
        isA<PosException>().having(
          (PosException e) => e.statusCode,
          'status',
          403,
        ),
      ),
    );
  });

  test('a counter order: preparing on the board, the kitchen makes it ready (expo), a recall and the hand-over reach the display in time', () async {
    // The till opens a takeaway and sends it; the server numbers it from the branch's block
    final PosOrder order = await till.pos.orders.open(
      channel: PosChannel.takeaway,
      items: menu.inputs(<Dish>[Dish.of('Shashlik', 2)]),
      send: true,
    );
    orderUuid = order.uuid;
    final int number = order.queue!.number;
    expect(order.queue!.status, PosPickupStatus.preparing);
    await heard.waitFor(
      (PosEvent e) =>
          e is PosQueueEvent &&
          e.number == number &&
          e.name == 'queue.preparing',
      what: 'queue.preparing',
    );

    PosQueueBoard board = await display.display.queue();
    expect(
      board.preparing.map((PosQueueEntry e) => e.number),
      contains(number),
    );
    expect(
      board.ready.map((PosQueueEntry e) => e.number),
      isNot(contains(number)),
    );

    // The kitchen display shows it as a ticket with its number; the cook bumps every line
    final KitchenTicketList tickets = await kds.client.kitchen.tickets();
    final KitchenTicket ticket = tickets.tickets.firstWhere(
      (KitchenTicket t) => t.uuid == order.uuid,
    );
    expect(ticket.queueNumber, number);
    expect(ticket.pickupStatus, 'preparing');
    DateTime sentAt = DateTime.now();
    for (final KitchenLine line in ticket.lines) {
      if (line.status == KitchenLineStatus.sent) {
        await kds.client.kitchen.bumpLine(ticket.uuid, line.id);
      }
    }
    final PosEvent ready = await hears(
      (PosEvent e) =>
          e is PosQueueEvent &&
          e.number == number &&
          e.name == 'queue.ready' &&
          !e.called,
      sentAt,
      'queue.ready',
    );
    expect((ready as PosQueueEvent).announce, isTrue);
    board = await display.display.queue();
    expect(board.ready.map((PosQueueEntry e) => e.number), contains(number));
    expect(
      board.preparing.map((PosQueueEntry e) => e.number),
      isNot(contains(number)),
    );

    // The till calls the number again
    sentAt = DateTime.now();
    await queue.call(order.uuid);
    final PosEvent again = await hears(
      (PosEvent e) =>
          e is PosQueueEvent &&
          e.number == number &&
          e.name == 'queue.ready' &&
          e.called,
      sentAt,
      'queue.ready (called)',
    );
    expect((again as PosQueueEvent).callCount, 1);

    // The customer takes it
    sentAt = DateTime.now();
    await queue.handed(order.uuid);
    await hears(
      (PosEvent e) =>
          e is PosQueueEvent && e.number == number && e.name == 'queue.handed',
      sentAt,
      'queue.handed',
    );
    board = await display.display.queue();
    expect(
      board.ready.map((PosQueueEntry e) => e.number),
      isNot(contains(number)),
    );
  });

  test('the expo button on the kitchen display makes a number ready without bumping its lines', () async {
    final PosOrder order = await till.pos.orders.open(
      channel: PosChannel.takeaway,
      items: menu.inputs(<Dish>[Dish.of('Shashlik', 1)]),
      send: true,
    );
    final int number = order.queue!.number;
    final String uuid = order.uuid;
    try {
      // The expo bump from the kitchen display: no lines were bumped, the number is called
      final DateTime sentAt = DateTime.now();
      await kds.client.kitchen.markQueueReady(uuid);
      await hears(
        (PosEvent e) =>
            e is PosQueueEvent &&
            e.number == number &&
            e.name == 'queue.ready' &&
            !e.called,
        sentAt,
        'queue.ready (expo)',
      );
      final PosQueueBoard board = await display.display.queue();
      expect(board.ready.map((PosQueueEntry e) => e.number), contains(number));
      await queue.handed(uuid);
    } finally {
      final PosOrder now = await till.pos.orders.get(uuid);
      await runWithApproval<PosOrder>(
        (String? token) => till.pos.orders.cancel(
          uuid,
          version: now.version,
          reason: 'Sinov tugadi',
          approvalToken: token,
        ),
        approve: (String permission, PosException cause) =>
            till.pos.approvals.request(
              permission: permission,
              userId: manager.id,
              pin: manager.pin!,
            ),
      );
    }
  });
}
