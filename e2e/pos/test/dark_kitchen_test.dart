/// P6-59 "dark kitchen" against the real stack: the seed's dark kitchen (`darkkitchen`, profile `dark_kitchen`, its own
/// store "Rizqim Dark Kitchen") sells three brands — Burger Bro, Lavash Lab, Shirin Bar — from one kitchen on every
/// channel: the Telegram bot and the mobile app (`POST /orders`, the customer's own API), the till (pos_data) and an
/// aggregator (two orders of today the seeder placed through the checkout action with the `aggregator` channel: there
/// is no aggregator API yet). The brand × channel rules hold (Lavash Lab 10 % dearer in the mobile app, Shirin Bar not
/// sold there — its dish is refused; Burger Bro 15 % dearer on the aggregator); every order lands in the one kitchen
/// queue with the brand on each line; "Tovuq tugadi" at the till closes the chicken dishes of every brand on every
/// channel at once (the till's and the customer's catalogue, a refused order on each side) and reopening brings them
/// back — except the dish stopped by its own stop-list entry, which stays stopped; "Pishloq" (not critical) only marks
/// its dishes. The channel net revenue report (gross, discounts, refunds, commission, net) is the orders, and its
/// Excel file opens. Nothing about speed is recorded.
@Timeout(Duration(minutes: 6))
library;

import 'dart:async';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/kitchen.dart';
import 'support/menu.dart';
import 'support/stack.dart';
import 'support/xlsx.dart';

const PosMoney openingCash = PosMoney.som(200000);

Map<String, Object?> asMap(Object? value) =>
    (value! as Map<Object?, Object?>).cast<String, Object?>();

List<Map<String, Object?>> asList(Object? value) => <Map<String, Object?>>[
  for (final Object? e in (value as List<Object?>?) ?? const <Object?>[])
    asMap(e),
];

PosMoney money(Object? value) => PosMoney.parse(value);

PosMoney som(int value) => PosMoney.som(value);

/// Banknotes for an amount (whole so'm), largest first.
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

/// One order of the drill as the report must count it: its channel, total, discount, refunds and its lines' totals
/// by brand code.
class Sold {
  Sold(
    this.channel,
    this.total, {
    this.discount = PosMoney.zero,
    this.refunds = PosMoney.zero,
    required this.brands,
    required this.commission,
  });

  final String channel;
  final PosMoney total;
  final PosMoney discount;
  PosMoney refunds;
  final Map<String, PosMoney> brands;
  final PosMoney commission;

  PosMoney get gross => total + discount;
}

void main() {
  late Seed seed;
  late StackConfig config;
  late Terminal till;
  late Terminal kds;
  late Dio customer; // the Mini App (telegram)
  late Dio phone; // the same customer in the mobile app (a token bound to a customer_mobile device)
  late Dio owner;
  late OnlineOrderRepository online;
  late Menu menu;
  late PosShift shift;
  late String today;
  late Map<String, int> brandIds; // code → id
  late int chicken;
  late int cheese;
  final List<Sold> sold = <Sold>[];

  Staff staff(String key) => seed.staff(key);
  int productId(String dish) => seed.product(dish).productId;

  Future<T> withManager<T>(Future<T> Function(String? token) action) =>
      runWithApproval<T>(
        action,
        approve: (String permission, PosException cause) =>
            till.pos.approvals.request(
              permission: permission,
              userId: staff('manager').id,
              pin: staff('manager').pin!,
            ),
      );

  /// The customer's catalogue of the store on a channel (`telegram`, `mobile`).
  Future<Map<String, Object?>> catalog(String channel) async {
    final Response<Object?> r = await customer.get<Object?>(
      '/v1/catalog',
      queryParameters: <String, Object?>{
        'store_id': seed.storeId,
        'channel': channel,
      },
    );
    expect(r.statusCode, 200, reason: '${r.data}');
    return asMap(asMap(r.data)['data']);
  }

  /// dish name → its price on that channel (dishes the channel hides are absent).
  Future<Map<String, PosMoney>> prices(String channel) async =>
      <String, PosMoney>{
        for (final Map<String, Object?> p in asList(
          (await catalog(channel))['products'],
        ))
          p['name']! as String: money(asList(p['variants']).single['price']),
      };

  /// `POST /orders` as the Mini App (telegram) or the mobile app sends a pickup — the channel is the sign-in's; the
  /// whole answer (refusals too).
  Future<Response<Object?>> place(String channel, List<Dish> dishes) =>
      (channel == 'mobile' ? phone : customer).post<Object?>(
        '/v1/orders',
        options: Options(
          headers: <String, Object?>{'Idempotency-Key': newIdempotencyKey()},
        ),
        data: <String, Object?>{
          'store_id': seed.storeId,
          'branch_id': seed.branchId,
          'type': 'pickup',
          'payment_method': 'cash',
          'items': <Map<String, Object?>>[
            for (final Dish d in dishes)
              <String, Object?>{
                'variant_id': menu.variant(d.name).uuid,
                'quantity': d.quantity.format(),
              },
          ],
        },
      );

  Future<KitchenTicket> ticketOf(String orderUuid) =>
      eventually<KitchenTicket>(() async {
        for (final KitchenTicket t
            in (await kds.client.kitchen.tickets()).tickets) {
          if (t.uuid == orderUuid) return t;
        }
        return null;
      }, what: 'the kitchen ticket of $orderUuid');

  /// The kitchen's one queue: every line of the order is on the ticket with its brand; the cook marks them ready.
  Future<void> cook(String orderUuid, Map<String, String> brandOfDish) async {
    final KitchenTicket ticket = await ticketOf(orderUuid);
    for (final KitchenLine line in ticket.lines) {
      expect(
        line.brand?.code,
        brandOfDish[line.productName],
        reason: '${line.productName}: the brand on the kitchen line',
      );
    }
    expect(
      ticket.brands.map((KitchenBrand b) => b.code).toSet(),
      brandOfDish.values.toSet(),
      reason: "the ticket's brands",
    );
    for (final KitchenLine line in ticket.lines) {
      await kds.client.kitchen.bumpLine(orderUuid, line.id);
    }
  }

  /// A customer's order through the till's incoming queue, the kitchen, and handed over.
  Future<Sold> throughQueue(
    String channel,
    List<Dish> dishes,
    Map<String, PosMoney> channelPrices,
  ) async {
    final Response<Object?> r = await place(channel, dishes);
    expect(r.statusCode, 201, reason: '${r.data}');
    final String uuid = asMap(asMap(r.data)['data'])['order_uuid']! as String;
    final PosOnlineOrder incoming = await eventually<PosOnlineOrder>(() async {
      for (final PosOnlineOrder o in await online.pending()) {
        if (o.uuid == uuid) return o;
      }
      return null;
    }, what: 'the order in the incoming queue');
    expect(incoming.channel, channel);
    final Map<String, PosMoney> byBrand = <String, PosMoney>{};
    final Map<String, String> brandOfDish = <String, String>{};
    for (final Dish d in dishes) {
      final String code = brandCodeOf(menu, d.name, brandIds);
      brandOfDish[d.name] = code;
      byBrand[code] =
          (byBrand[code] ?? PosMoney.zero) +
          channelPrices[d.name]!.timesQuantity(d.quantity);
    }
    final PosMoney total = PosMoney.sum(byBrand.values);
    expect(
      incoming.totalPrice,
      total,
      reason: 'the channel prices of $channel',
    );
    await online.accept(uuid);
    await cook(uuid, brandOfDish);
    await till.client.dio.post<Object?>(
      '/v1/staff/orders/$uuid/status',
      data: <String, Object?>{'status': 'delivered'},
    );
    // RIZQIM's commission of a pickup (the store's 10 %): the billing source of the report
    return Sold(channel, total, brands: byBrand, commission: total.percent(10));
  }

  setUpAll(() async {
    final StackConfig stack = StackConfig.fromEnvironment();
    seed = stack.seed.section('darkkitchen');
    config = stack.withSeed(seed);
    today = seed.json['business_date']! as String;
    brandIds = <String, int>{
      for (final Object? b in asMap(seed.json['brands']).values)
        asMap(b)['code']! as String: (asMap(b)['id']! as num).toInt(),
    };
    final Map<String, Object?> ingredients = asMap(seed.json['ingredients']);
    chicken = (asMap(ingredients['chicken'])['id']! as num).toInt();
    cheese = (asMap(ingredients['cheese'])['id']! as num).toInt();

    till = await Terminal.open(config, 'till');
    kds = await Terminal.open(config, 'kds');
    await till.signIn('cashier');
    await kds.signIn('cook');
    menu = Menu(
      await till.pos.catalog.load(
        PosChannel.takeaway,
        menuVersion: till.bootstrap.menuVersion,
      ),
    );
    online = OnlineOrderRepository(till.pos.http);
    customer = Dio(
      BaseOptions(
        baseUrl: config.apiUrl,
        headers: <String, Object?>{
          'Authorization': 'Bearer ${asMap(seed.json['customer'])['token']}',
          'Accept': 'application/json',
        },
        validateStatus: (_) => true,
      ),
    );
    phone = Dio(
      BaseOptions(
        baseUrl: config.apiUrl,
        headers: <String, Object?>{
          'Authorization':
              'Bearer ${asMap(seed.json['customer'])['mobile_token']}',
          'Accept': 'application/json',
        },
        validateStatus: (_) => true,
      ),
    );
    owner = Dio(
      BaseOptions(
        baseUrl: config.apiUrl,
        headers: <String, Object?>{
          'Authorization': 'Bearer ${seed.ownerToken}',
          'Accept': 'application/json',
        },
        validateStatus: (_) => true,
      ),
    );
    shift = await till.pos.shifts.open(
      openingCash: openingCash,
      note: 'Kechki smena',
    );
    till.pos.session.updateShift(shift.toSummary());

    // The aggregator orders of today the seeder placed (delivered), at the aggregator's prices
    for (final Map<String, Object?> o in asList(
      seed.json['aggregator_orders'],
    )) {
      final PosMoney total = money(o['total']);
      sold.add(
        Sold(
          'aggregator',
          total,
          brands: const <String, PosMoney>{},
          commission: total.percent(25),
        ),
      );
    }
  });

  tearDownAll(() async {
    customer.close();
    phone.close();
    owner.close();
    await till.close();
    await kds.close();
  });

  test(
    'brand × channel: the prices and a brand hidden on one channel',
    () async {
      final PosCatalog pos = menu.catalog;
      expect(
        pos.brands.map((PosBrand b) => b.code),
        containsAll(<String>['BB', 'LL', 'SB']),
      );
      expect(menu.variant('Tovuqli lavash').price, som(35000));
      expect(menu.variant('Chiken burger').price, som(45000));

      final Map<String, PosMoney> telegram = await prices('telegram');
      final Map<String, PosMoney> mobile = await prices('mobile');
      // Telegram: the menu prices; every brand is sold
      expect(telegram['Tovuqli lavash'], som(35000));
      expect(telegram['Cheesecake'], som(28000));
      // The mobile app: Lavash Lab 10 % dearer, Shirin Bar not sold, Burger Bro as on the menu
      expect(mobile['Tovuqli lavash'], som(38500));
      expect(mobile["Go'shtli lavash"], som(44000));
      expect(mobile['Chiken burger'], som(45000));
      expect(mobile.containsKey('Cheesecake'), isFalse);
      expect(mobile.containsKey('Milkshake'), isFalse);
      expect(
        asList((await catalog('mobile'))['brands'])
            .map((Map<String, Object?> b) => b['code'])
            .toSet(),
        <String>{'BB', 'LL'},
      );
      // A hidden brand's dish cannot be ordered there
      final Response<Object?> refused = await place('mobile', <Dish>[
        Dish.of('Cheesecake', 1),
      ]);
      expect(refused.statusCode, 422, reason: '${refused.data}');
    },
  );

  test('orders of every channel land in the one kitchen queue with the brand on each line', () async {
    final Map<String, PosMoney> telegram = await prices('telegram');
    final Map<String, PosMoney> mobile = await prices('mobile');
    sold.add(
      await throughQueue('telegram', <Dish>[
        Dish.of('Chiken burger', 2),
        Dish.of('Fri', 1),
      ], telegram),
    );
    sold.add(
      await throughQueue('telegram', <Dish>[
        Dish.of('Tovuqli lavash', 1),
        Dish.of('Milkshake', 2),
      ], telegram),
    );
    sold.add(
      await throughQueue('mobile', <Dish>[
        Dish.of('Tovuqli lavash', 2),
        Dish.of('Double burger', 1),
      ], mobile),
    );

    // The till: a takeaway with a 10 % discount (the manager allows it), and one paid and partly refunded
    final List<Dish> first = <Dish>[
      Dish.of('Double burger', 1),
      Dish.of('Fri', 2),
    ];
    PosOrder order = await till.pos.orders.open(
      channel: PosChannel.takeaway,
      items: menu.inputs(first),
      send: true,
    );
    final PosOrder discounted = await withManager(
      (String? token) => till.pos.orders.discount(
        order.uuid,
        version: order.version,
        kind: PosDiscountKind.percent,
        value: '10',
        reason: 'Doimiy mijoz',
        approvalToken: token,
      ),
    );
    final PosBilling bills = await till.pos.billing.createBills(
      discounted.uuid,
      version: discounted.version,
    );
    final PosBill bill = bills.bills.single;
    expect(bill.total, som(76500)); // 85 000 less 8 500
    final PosBillResult paid = await till.pos.billing.pay(
      bill.uuid,
      tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
      shiftUuid: shift.uuid,
    );
    expect(paid.bill.status, 'paid');
    // Paid at the counter first, then cooked (a ready order takes no discount any more)
    await cook(order.uuid, <String, String>{
      'Double burger': 'BB',
      'Fri': 'BB',
    });
    sold.add(
      Sold(
        'pos_takeaway',
        som(76500),
        discount: som(8500),
        brands: <String, PosMoney>{'BB': som(85000)},
        commission: PosMoney.zero, // the store's own till: no RIZQIM commission
      ),
    );

    final List<Dish> second = <Dish>[
      Dish.of("Go'shtli lavash", 1),
      Dish.of('Cheesecake', 1),
    ];
    order = await till.pos.orders.open(
      channel: PosChannel.takeaway,
      items: menu.inputs(second),
      send: true,
    );
    final PosBill bill2 = (await till.pos.billing.createBills(
      order.uuid,
      version: order.version,
    )).bills.single;
    expect(bill2.total, som(68000));
    final PosBillResult paid2 = await till.pos.billing.pay(
      bill2.uuid,
      tenders: <PosTenderInput>[PosTenderInput.cash(bill2.total)],
      shiftUuid: shift.uuid,
    );
    await cook(order.uuid, <String, String>{
      "Go'shtli lavash": 'LL',
      'Cheesecake': 'SB',
    });
    // The cheesecake was not right: 10 000 back
    await withManager(
      (String? token) => till.pos.billing.refund(
        paymentUuid: paid2.bill.payments.single.uuid,
        amount: som(10000),
        reason: "Desert sifati yomon",
        shiftUuid: shift.uuid,
        approvalToken: token,
      ),
    );
    sold.add(
      Sold(
        'pos_takeaway',
        som(68000),
        refunds: som(10000),
        brands: <String, PosMoney>{'LL': som(40000), 'SB': som(28000)},
        commission: PosMoney.zero,
      ),
    );
  });

  test('"Tovuq tugadi" closes the chicken dishes of every brand on every channel at once, and reopens', () async {
    // A dish already stopped by its own entry
    final Response<Object?> own = await owner.post<Object?>(
      '/v1/owner/stop-list',
      data: <String, Object?>{
        'branch_id': seed.branchId,
        'type': 'product',
        'id': productId('Tovuqli shaurma'),
        'reason': 'Lavash noni tugadi',
      },
    );
    expect(own.statusCode, 201, reason: '${own.data}');
    final int ownEntry = (asMap(asMap(own.data)['data'])['id']! as num).toInt();

    // The manager's quick stop at the till
    await till.signIn('manager');
    final PosIngredientStopResult stopped = await till.pos.ingredients.stop(
      chicken,
      reason: 'Tovuq tugadi',
    );
    await till.signIn('cashier');
    final Set<int> chickenDishes = <int>{
      productId('Chiken burger'),
      productId('Tovuqli lavash'),
      productId('Tovuqli shaurma'),
    };
    expect(
      stopped.closedProductIds.toSet(),
      containsAll(<int>{
        productId('Chiken burger'),
        productId('Tovuqli lavash'),
      }),
    );

    // The till's catalogue (both brands), and the customer's on every channel
    PosCatalog pos = await till.pos.catalog.load(
      PosChannel.takeaway,
      force: true,
    );
    for (final String dish in <String>[
      'Chiken burger',
      'Tovuqli lavash',
      'Tovuqli shaurma',
    ]) {
      expect(
        pos.isProductAvailable(Menu(pos).product(dish)),
        isFalse,
        reason: dish,
      );
    }
    expect(
      pos.isProductAvailable(Menu(pos).product("Go'shtli lavash")),
      isTrue,
    );
    expect(pos.isProductAvailable(Menu(pos).product('Double burger')), isTrue);
    expect(
      pos.availability.stoppedIngredients.map(
        (PosStoppedIngredient i) => i.name,
      ),
      contains('Tovuq'),
    );
    for (final String channel in <String>['telegram', 'mobile']) {
      final Map<String, Object?> branch =
          asList((await catalog(channel))['availability']).firstWhere(
            (Map<String, Object?> b) =>
                (b['branch_id']! as num).toInt() == seed.branchId,
          );
      expect(
        asList(branch['stopped_ingredients'])
            .map((Map<String, Object?> i) => i['name']),
        contains('Tovuq'),
        reason: channel,
      );
      final Set<int> closed = <int>{
        for (final Object? id
            in asMap(branch['stopped'])['products']! as List<Object?>)
          (id! as num).toInt(),
      };
      expect(closed, containsAll(chickenDishes), reason: channel);
    }
    // An order is refused on both sides
    final Response<Object?> refused = await place('telegram', <Dish>[
      Dish.of('Tovuqli lavash', 1),
    ]);
    expect(refused.statusCode, 422, reason: '${refused.data}');
    Object? tillRefusal;
    try {
      await till.pos.orders.open(
        channel: PosChannel.takeaway,
        items: menu.inputs(<Dish>[Dish.of('Chiken burger', 1)]),
        send: true,
      );
    } on PosException catch (e) {
      tillRefusal = e;
    }
    expect(tillRefusal, isA<PosException>());
    expect((tillRefusal! as PosException).statusCode, 422);

    // "Pishloq" is not critical: its dishes stay on sale, marked as limited
    await till.signIn('manager');
    await till.pos.ingredients.stop(cheese, reason: 'Pishloq kam qoldi');
    pos = await till.pos.catalog.load(PosChannel.takeaway, force: true);
    expect(pos.isProductAvailable(Menu(pos).product('Double burger')), isTrue);
    expect(
      pos.availability.limitedProducts.keys.toSet(),
      containsAll(<int>{productId('Double burger'), productId('Cheesecake')}),
    );
    await till.pos.ingredients.reopen(cheese);

    // Reopened: the chicken dishes are back, except the one stopped by its own entry
    await till.pos.ingredients.reopen(chicken);
    await till.signIn('cashier');
    pos = await till.pos.catalog.load(PosChannel.takeaway, force: true);
    expect(pos.isProductAvailable(Menu(pos).product('Chiken burger')), isTrue);
    expect(pos.isProductAvailable(Menu(pos).product('Tovuqli lavash')), isTrue);
    expect(
      pos.isProductAvailable(Menu(pos).product('Tovuqli shaurma')),
      isFalse,
    );
    expect(pos.availability.stoppedIngredients, isEmpty);
    final List<PosIngredient> list = await till.pos.ingredients.list();
    expect(list.every((PosIngredient i) => !i.stoppedHere), isTrue);
    // The own entry is lifted last
    final Response<Object?> lifted = await owner.delete<Object?>(
      '/v1/owner/stop-list/$ownEntry',
      queryParameters: <String, Object?>{'branch_id': seed.branchId},
    );
    expect(lifted.statusCode, 200, reason: '${lifted.data}');
  });

  test('the channel net revenue report is the orders (gross, discounts, refunds, commission, net); the Excel opens', () async {
    final Response<Object?> r = await owner.get<Object?>(
      '/v1/owner/reports/channel-revenue',
      queryParameters: <String, Object?>{'from': today, 'to': today},
    );
    expect(r.statusCode, 200, reason: '${r.data}');
    final Map<String, Object?> report = asMap(asMap(r.data)['data']);

    final Map<String, Map<String, Object?>> byChannel =
        <String, Map<String, Object?>>{
          for (final Map<String, Object?> c in asList(report['by_channel']))
            c['channel']! as String: c,
        };
    expect(byChannel.keys.toSet(), <String>{
      'aggregator',
      'telegram',
      'mobile',
      'pos_takeaway',
    });
    for (final String channel in byChannel.keys) {
      final List<Sold> of = sold
          .where((Sold s) => s.channel == channel)
          .toList();
      final Map<String, Object?> row = byChannel[channel]!;
      PosMoney sum(PosMoney Function(Sold s) f) => PosMoney.sum(of.map(f));
      expect(row['orders'], of.length, reason: channel);
      expect(
        money(row['gross']),
        sum((Sold s) => s.gross),
        reason: '$channel gross',
      );
      expect(
        money(row['discounts']),
        sum((Sold s) => s.discount),
        reason: '$channel discounts',
      );
      expect(
        money(row['refunds']),
        sum((Sold s) => s.refunds),
        reason: '$channel refunds',
      );
      expect(
        money(row['commission']),
        sum((Sold s) => s.commission),
        reason: '$channel commission',
      );
      expect(
        money(row['net']),
        sum((Sold s) => s.gross - s.discount - s.refunds - s.commission),
        reason: '$channel net',
      );
    }
    expect(byChannel['aggregator']!['commission_source'], 'owner');
    expect(byChannel['aggregator']!['commission_percent'], '25.00');
    expect(byChannel['telegram']!['commission_source'], 'billing');

    // By brand: the line totals of every order of that brand (no fees on a pickup: gross is the lines)
    final Map<String, PosMoney> brandGross = <String, PosMoney>{
      'BB': som(126500) + som(51750), // the aggregator's (15 % dearer)
      'LL': som(40000),
      'SB': som(22000),
    };
    for (final Sold s in sold) {
      for (final MapEntry<String, PosMoney> e in s.brands.entries) {
        brandGross[e.key] = brandGross[e.key]! + e.value;
      }
    }
    final Map<String, PosMoney> reported = <String, PosMoney>{
      for (final Map<String, Object?> b in asList(report['by_brand']))
        if (b['brand_code'] != null)
          b['brand_code']! as String: money(b['gross']),
    };
    expect(reported, brandGross);
    // The brand rows add up to the totals exactly
    final Map<String, Object?> totals = asMap(report['totals']);
    expect(money(totals['gross']), PosMoney.sum(sold.map((Sold s) => s.gross)));
    expect(
      money(totals['net']),
      PosMoney.sum(
        asList(report['rows'])
            .map((Map<String, Object?> row) => money(row['net'])),
      ),
    );

    final Response<Object?> x = await owner.get<Object?>(
      '/v1/owner/reports/channel-revenue/export',
      queryParameters: <String, Object?>{'from': today, 'to': today},
      options: Options(responseType: ResponseType.bytes),
    );
    expect(x.statusCode, 200);
    expect(x.headers.value('content-type'), contains('spreadsheetml'));
    final XlsxFile file = XlsxFile.parse(x.data! as List<int>);
    expect(file.sheetNames, <String>['Kanal va brend', 'Kanallar', 'Brendlar']);
    for (final String name in <String>[
      'Burger Bro',
      'Lavash Lab',
      'Shirin Bar',
    ]) {
      expect(file.allText, contains(name));
    }
  });

  test(
    'the Z of the till: the takeaways in cash less the refund, difference 0',
    () async {
      final PosShiftWithReport x = await till.pos.shifts.xReport(shift.uuid);
      final PosMoney expected =
          openingCash + som(76500) + som(68000) - som(10000);
      expect(x.report!.expectedCash, expected);
      final PosShift z = await till.pos.shifts.close(
        shift.uuid,
        countedBreakdown: banknotes(expected),
      );
      expect(z.difference, PosMoney.zero);
      till.pos.session.updateShift(null);
    },
  );
}

/// The brand code of a dish on the till's catalogue.
String brandCodeOf(Menu menu, String dish, Map<String, int> brandIds) {
  final int? id = menu.product(dish).brandId;
  return brandIds.entries
      .firstWhere((MapEntry<String, int> e) => e.value == id)
      .key;
}
