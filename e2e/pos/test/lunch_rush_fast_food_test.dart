/// P6-39 "Tushlik cho'qqisi" against the real stack: a fast food (profile `fast_food`, its own store in the seed,
/// `fastfood`) at the lunch peak. Two tills sell over the counter, pay first, while about twenty Telegram orders come
/// in. Lavash and burger combos (the owner's receipt example: one line, the parts below it without prices), drinks with
/// the one-tap size, coffee from the corner's barista. Every combo part goes to its own station (grill, fryer, drinks,
/// barista); an order cooked on two or more stations waits on the expo screen until it is assembled, then its number
/// shows ready on the board; the fryer falls behind for a while and its lines turn late. Cup stickers (drinks, inside
/// combos too) and bag labels go to the TSPL label printer (the emulator decodes them). Every takeaway carries its
/// packaging (a bag, a cup per drink) as its own fiscal lines; one guest brings their own bag. One order is for a later
/// pickup and fires to the kitchen at its time (two Telegram ones too); the second till sells a combo offline and syncs.
/// The combo's fiscal mode is switched to `split` for a while (the shares add up to the tiyin) and back. Z on both tills
/// (the receipts are the sales, the packaging apart), and the prep time report matches the timestamps the kitchen
/// display showed. Everything goes through pos_data and the kitchen display's own calls; the customers use the Mini
/// App's `POST /orders`.
///
/// The simulated hour (12:00–13:00) is about 60 counter orders and 20 Telegram ones in a few minutes of real time.
@Timeout(Duration(minutes: 12))
library;

import 'dart:async';
import 'dart:io';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/testing.dart' show TsplDocument;
import 'package:test/test.dart';

import 'support/fast_food.dart';
import 'support/stack.dart';

/// The rush: counter orders on the two tills (the offline ones and the split-fiscal ones included) and Telegram orders.
const int counterOrders = 60;
const int telegramOrders = 20;
const int offlineOrders = 4;

const String bagReason = "Mijoz o'z sumkasini olib keldi";

/// The MXIK codes of the seed (PosStackSeeder::fastFood).
const Map<String, String> mxik = <String, String>{
  'Lavash kombo': '10202001009000021',
  'Burger kombo': '10202001009000022',
  'Lavash': '10202001011000021',
  'Gamburger': '10202001012000021',
  'Chizburger': '10202001012000022',
  'Kartoshka fri': '10202001013000021',
  'Nagetslar': '10202001013000022',
  'Pepsi': '10202001005000021',
  'Fanta': '10202001005000022',
  'Achchiq sous': '10202001016000021',
  'Sarimsoqli sous': '10202001016000022',
  'Paket': '10202002010000001',
  'Stakan': '10202002010000002',
};

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

/// Shares of [total] tiyin by [weights], largest remainder, ties to the earlier one (docs/MENU.md §13, `split`).
List<int> shares(int total, List<int> weights) {
  final int sum = weights.fold<int>(0, (int a, int b) => a + b);
  if (sum == 0) {
    return <int>[for (var i = 0; i < weights.length; i++) i == 0 ? total : 0];
  }
  final List<int> out = <int>[for (final int w in weights) total * w ~/ sum];
  final List<int> rest = <int>[for (final int w in weights) total * w % sum];
  var left = total - out.fold<int>(0, (int a, int b) => a + b);
  final List<int> order = List<int>.generate(weights.length, (int i) => i)
    ..sort((int a, int b) => rest[b] != rest[a] ? rest[b] - rest[a] : a - b);
  for (final int i in order) {
    if (left == 0) break;
    out[i]++;
    left--;
  }
  return out;
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
    this.items, {
    this.card = false,
    this.removeBag = false,
    this.pickupIn,
  });

  final int index;
  final List<Item> items;
  final bool card;

  /// The guest brought their own bag: the cashier takes the bag line off with a reason.
  final bool removeBag;

  /// For a later pickup: the time from now.
  final Duration? pickupIn;

  late Till till;
  bool offline = false;
  String? localUuid;
  late String uuid;
  late int number;
  late PosMoney dishes;
  late PosMoney packaging;
  late Set<String> stations;
  late List<PosTenderInput> tenders;
  PosBill? bill;
  PosFiscalReceiptDetail? receipt;
  PosPickupTime? pickup;

  /// The fiscal mode the combo lines were sold under.
  String fiscalMode = 'single';

  PosMoney get total => dishes + packaging;
  bool get multiStation => stations.length >= 2;

  @override
  String toString() => '#$index ${items.join(', ')}';
}

/// A Telegram order (the Mini App's `POST /orders`, a pickup paid in cash at the counter).
class TgOrder {
  TgOrder(this.index, this.customer, this.items, {this.pickupIn});

  final int index;
  final int customer;
  final List<Item> items;
  final Duration? pickupIn;
  late String uuid;
  late PosMoney dishes;
  late PosMoney packaging;
  late Set<String> stations;
  int? number;
  DateTime? pickupAt;
  DateTime? acceptedAt;

  PosMoney get total => dishes + packaging;
  bool get multiStation => stations.length >= 2;

  @override
  String toString() => 'TG$index ${items.join(', ')}';
}

/// A till of the fast food: the terminal, its journal (queue numbers, offline mode), its menu and its shift.
class Till {
  Till(this.terminal, this.store, this.cable);

  final Terminal terminal;
  final SyncStore store;
  final Cable cable;
  late FfMenu menu;
  late PosShift shift;
  late PosQueueRepository queue;
  late PosTakeawayRepository takeaway;
  late FiscalReceiptRepository receipts;
  final List<Sale> sales = <Sale>[];

  PosData get pos => terminal.pos;
  String get label => terminal.label;
  PosQueueState get queueState => terminal.bootstrap.queue;
}

void main() {
  late StackConfig config;
  late Seed seed;
  late Dio owner;
  late List<Dio> customers;
  late Till till1;
  late Till till2;
  late Terminal kds;
  late Terminal expoScreen;
  late Terminal display;
  late EventLog board;
  late KitchenCrew crew;
  late ExpoWorker expo;
  late LabelPrinterSim labelPrinter;
  late OnlineOrderRepository online;
  late Map<String, int> stationIds;
  late Map<String, int> categories;
  late int bagRule;
  late int cupRule;
  late String businessDate;
  late Sale scheduled;
  final SimClock clock = SimClock(hour: 12);
  late StepLog step;
  final Random random = Random(639);
  final List<Sale> all = <Sale>[];
  final List<TgOrder> telegram = <TgOrder>[];
  final List<Duration> expoToBoard = <Duration>[];
  final List<Duration> bumpToBoard = <Duration>[];
  var made = 0;
  var stickers = 0;
  var comboStickers = 0;
  var bagLabels = 0;
  String fiscalMode = 'single';

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

  String stationKey(int? id) => stationIds.entries
      .firstWhere(
        (MapEntry<String, int> e) => e.value == id,
        orElse: () => const MapEntry<String, int>('none', 0),
      )
      .key;

  /// The next planned sale (deterministic): combos, lavash, burgers, fries and nuggets, drinks with their size, coffee
  /// and sauces; a card about every third sale.
  Sale plan({List<Item>? items, bool removeBag = false, Duration? pickupIn}) {
    made++;
    final List<Item> lines = items ?? <Item>[];
    if (items == null) {
      final int kind = random.nextInt(10);
      if (kind < 3) {
        lines.add(
          Item.combo(
            'Lavash kombo',
            picks: <List<String>>[
              const <String>[],
              const <String>['Ichimlik — 0,4 l Fanta'],
              const <String>[
                "1 ta lavash — katta, tovuq go'shtli",
                'Sous — sarimsoqli',
              ],
              const <String>['Ichimlik — 0,5 l Pepsi'],
            ][random.nextInt(4)],
          ),
        );
      } else if (kind < 5) {
        lines.add(
          Item.combo(
            'Burger kombo',
            picks: <List<String>>[
              const <String>[],
              const <String>['Garnir — Nagetslar (6 dona)'],
              const <String>[
                'Asosiy — Gamburger (Dona)',
                'Ichimlik — 0,4 l Fanta',
              ],
            ][random.nextInt(3)],
          ),
        );
      } else if (kind < 6) {
        lines.add(
          Item.dish(
            'Lavash',
            modifiers: <String>[
              random.nextBool() ? "Mol go'shtli" : "Tovuq go'shtli",
            ],
          ),
        );
      } else if (kind < 7) {
        lines.add(Item.dish(random.nextBool() ? 'Chizburger' : 'Gamburger'));
        lines.add(const Item.dish('Kartoshka fri'));
      } else if (kind < 8) {
        lines.add(
          Item.dish(
            random.nextBool() ? 'Kapuchino' : 'Latte',
            modifiers: <String>[random.nextBool() ? '0,3 l' : '0,4 l'],
          ),
        );
      } else if (kind < 9) {
        lines.add(const Item.dish('Nagetslar'));
      } else {
        lines.add(
          const Item.dish(
            'Lavash',
            variant: 'Oddiy',
            modifiers: <String>["Mol go'shtli"],
          ),
        );
        lines.add(const Item.dish('Achchiq sous'));
      }
      if (random.nextInt(10) < 4) {
        lines.add(
          Item.dish(
            random.nextBool() ? 'Pepsi' : 'Fanta',
            quantity: 1 + random.nextInt(2),
            modifiers: <String>[
              <String>['0,3 l', '0,4 l', '0,5 l'][random.nextInt(3)],
            ],
          ),
        );
      }
      if (random.nextInt(10) < 2) lines.add(const Item.dish('Suv'));
    }
    return Sale(
      made,
      lines,
      card: random.nextInt(3) == 0,
      removeBag: removeBag,
      pickupIn: pickupIn,
    );
  }

  /// Cup stickers and the bag label of an order to the label printer (as the till does after payment).
  Future<void> printLabels(
    Till till, {
    required int number,
    required List<Item> items,
    String? customer,
    DateTime? pickupAt,
  }) async {
    final PosLabelSettings labels = till.terminal.bootstrap.labelSettings!;
    final PosPrinterInfo printer = till.terminal.bootstrap.printers.firstWhere(
      (PosPrinterInfo p) => p.isLabel,
    );
    final DateTime now = DateTime.now();
    for (final CupSticker s in till.menu.stickers(
      items,
      number: '$number',
      at: now,
      labels: labels,
      customer: customer,
    )) {
      final String text = await labelPrinter.send(
        layoutCupSticker(s, labels.size),
        printer.address!,
        printer.port!,
      );
      // A doubled-size text reads "#~5~7~" on the emulator's screen
      expect(
        text.replaceAll('~', ''),
        contains('#$number'),
        reason: 'the sticker of ${s.itemName}: $text',
      );
      expect(text, contains(s.itemName), reason: text);
      stickers++;
      if (s.modifiers.any((String m) => m.contains('kombo'))) comboStickers++;
    }
    if (labels.bagLabel) {
      final String text = await labelPrinter.send(
        layoutBagLabel(
          BagLabel(
            number: '$number',
            itemCount: items.fold<int>(0, (int n, Item i) => n + i.quantity),
            printedAt: now,
            customerName: customer,
            pickupAt: pickupAt,
          ),
          labels.size,
        ),
        printer.address!,
        printer.port!,
      );
      expect(text.replaceAll('~', ''), contains('$number'), reason: text);
      bagLabels++;
    }
  }

  /// The packaging lines of an order or a bill as the plan says: a bag (removed: kept with its reason, 0 so'm) and the
  /// cups of the drinks.
  void expectPackaging(
    List<PosPackagingLine> lines,
    List<Item> items,
    FfMenu menu, {
    bool removedBag = false,
    required String what,
  }) {
    final PosPackagingLine bag = lines.firstWhere(
      (PosPackagingLine l) => l.ruleId == bagRule,
    );
    expect(bag.quantity, 1, reason: '$what: one bag');
    expect(bag.removed, removedBag, reason: what);
    expect(bag.amount, removedBag ? PosMoney.zero : bagPrice, reason: what);
    if (removedBag) expect(bag.removedReason, bagReason);
    final int cups = menu.cups(items);
    final Iterable<PosPackagingLine> cupLines = lines.where(
      (PosPackagingLine l) => l.ruleId == cupRule,
    );
    if (cups == 0) {
      expect(
        cupLines,
        isEmpty,
        reason: '$what: no drinks of their own, no cup',
      );
    } else {
      expect(cupLines.single.quantity, cups, reason: '$what: a cup per drink');
      expect(cupLines.single.amount, cupPrice * cups, reason: what);
    }
  }

  /// The receipt of a sale: the combo as ONE line with its own MXIK (single) or its parts (split, the shares add up to
  /// the tiyin), the packaging lines with their own codes, and nothing else; the lines add up to the bill.
  void expectReceipt(Sale s) {
    final PosFiscalReceiptDetail r = s.receipt!;
    final FfMenu menu = s.till.menu;
    expect(r.total, s.total, reason: '$s: the receipt is the bill');
    expect(
      PosMoney.sum(r.lines.map((PosFiscalLine l) => l.amount)),
      s.total,
      reason: '$s: the receipt lines add up',
    );
    final List<PosFiscalLine> bag = r.lines
        .where((PosFiscalLine l) => l.mxikCode == mxik['Paket'])
        .toList();
    final List<PosFiscalLine> cup = r.lines
        .where((PosFiscalLine l) => l.mxikCode == mxik['Stakan'])
        .toList();
    if (s.removeBag) {
      expect(bag, isEmpty, reason: '$s: the bag was taken off');
    } else {
      expect(
        bag.single.amount,
        bagPrice,
        reason: '$s: the bag is its own fiscal line',
      );
    }
    final int cups = menu.cups(s.items);
    if (cups > 0) {
      expect(cup.single.amount, cupPrice * cups, reason: '$s: the cups');
      expect(cup.single.quantity, PosQuantity.whole(cups));
    } else {
      expect(cup, isEmpty);
    }
    for (final Item i in s.items.where((Item i) => i.isCombo)) {
      final PosMoney line = menu.price(i);
      final List<PosFiscalLine> own = r.lines
          .where((PosFiscalLine l) => l.mxikCode == mxik[i.name])
          .toList();
      if (s.fiscalMode == 'single') {
        expect(
          own,
          isNotEmpty,
          reason: '$s: ${i.name} is one fiscal line with its own MXIK',
        );
        expect(
          PosMoney.sum(own.map((PosFiscalLine l) => l.amount)),
          menu.total(s.items.where((Item x) => x.name == i.name)),
        );
      } else {
        expect(own, isEmpty, reason: '$s: split: no line of the combo itself');
        // The shares by the parts' list prices (dish price + preset modifiers) × pieces
        final List<Part> parts = menu.parts(i);
        final List<int> weights = <int>[
          for (final Part p in parts)
            ((menu
                            .productById(p.option.productId!)
                            .variants
                            .firstWhere(
                              (PosVariant v) => v.id == p.option.variantId,
                            )
                            .price +
                        PosMoney.sum(
                          p.option.modifiers.map(
                            (PosComboModifier m) => m.priceDelta * m.quantity,
                          ),
                        )) *
                    p.pieces)
                .tiyin,
        ];
        final List<int> expected = shares(line.tiyin, weights);
        expect(expected.fold<int>(0, (int a, int b) => a + b), line.tiyin);
        for (var k = 0; k < parts.length; k++) {
          final String code =
              mxik[menu.productById(parts[k].option.productId!).name]!;
          final Iterable<PosFiscalLine> partLines = r.lines.where(
            (PosFiscalLine l) =>
                l.mxikCode == code && l.amount.tiyin == expected[k],
          );
          expect(
            partLines,
            isNotEmpty,
            reason:
                '$s: split share ${parts[k].option.name} = ${expected[k]} tiyin (${r.lines.map((PosFiscalLine l) => '${l.name} ${l.mxikCode} ${l.amount}').join('; ')})',
          );
        }
      }
    }
  }

  /// "To'lash": a number from the till's block, the order opened (sent to the kitchen unless it is for a later pickup),
  /// the bag taken off if the guest brought one, one bill with the packaging, paid; the receipt and the labels.
  Future<Sale> sell(
    Till t,
    Sale s, {
    bool offline = false,
    PosOrder? opened,
  }) async {
    s.till = t;
    s.offline = offline;
    s.fiscalMode = fiscalMode;
    s.dishes = t.menu.total(s.items);
    s.packaging = t.menu.packaging(s.items, bag: !s.removeBag);
    s.stations = t.menu.stations(s.items);
    final String date = t.queueState.businessDate!;
    // [opened]: the order the till opened before (its number taken then)
    final int? number =
        opened?.queue?.number ??
        await t.store.takeQueueNumber(date, t.queueState.blocks);
    expect(
      number,
      isNotNull,
      reason: '${t.label} has numbers left in its block',
    );
    step(t.label, 'sells $s as $number${offline ? ' offline' : ''}');
    PosOrder order =
        opened ??
        await t.pos.orders.open(
          channel: PosChannel.takeaway,
          items: t.menu.inputs(s.items),
          send: s.pickupIn == null,
          queueNumber: number,
        );
    s.number = number!;
    if (offline) {
      s.localUuid = order.uuid;
      expect(
        order.totalPrice,
        s.total,
        reason:
            '$s: the till prices the combo and the packaging offline as the server does',
      );
    } else {
      s.uuid = order.uuid;
      expect(
        order.queue!.number,
        number,
        reason: 'the server keeps the number the till took from its block',
      );
      await t.store.noteQueueNumber(date, s.number);
      expectPackaging(order.packagingLines, s.items, t.menu, what: '$s');
      expect(order.packagingAmount, t.menu.packaging(s.items));
      // A combo is one priced line; its parts ride on it without prices
      for (final Item i in s.items.where((Item i) => i.isCombo)) {
        final PosOrderItem line = order.items.firstWhere(
          (PosOrderItem l) => l.isCombo && l.productName == i.name,
        );
        expect(
          <String>[for (final PosComboPart p in line.comboParts!) p.label],
          <String>[for (final Part p in t.menu.parts(i)) p.option.receiptLabel],
        );
        expect(line.totalPrice, t.menu.price(i), reason: '$s: ${i.name}');
      }
      if (s.removeBag) {
        order = await t.takeaway.removePackaging(
          s.uuid,
          bagRule,
          version: order.version,
          reason: bagReason,
        );
        expectPackaging(
          order.packagingLines,
          s.items,
          t.menu,
          removedBag: true,
          what: '$s after the bag was taken off',
        );
      }
      if (s.pickupIn != null) {
        order = await t.takeaway.setPickupTime(
          s.uuid,
          DateTime.now().toUtc().add(s.pickupIn!),
          version: order.version,
        );
        s.pickup = order.pickup;
      }
      expect(
        order.totalPrice,
        s.total,
        reason: '$s: dishes ${s.dishes} + packaging ${s.packaging}',
      );
    }

    final PosBilling billing = await t.pos.billing.createBills(
      order.uuid,
      version: order.version,
    );
    final PosBill bill = billing.bills.firstWhere(
      (PosBill b) => !b.isPaid && b.status != 'void',
    );
    expect(
      bill.total,
      s.total,
      reason: '$s: the bill is the dishes and the packaging',
    );
    s.tenders = <PosTenderInput>[
      if (s.card)
        PosTenderInput.card(
          s.total,
          cardType: s.index.isEven ? 'uzcard' : 'humo',
          transactionId: '${700000000000 + s.index}',
          cardLast4: '${4000 + s.index}',
        )
      else
        PosTenderInput.cash(
          s.total,
          tendered: PosMoney.som((s.total.tiyin / 100 / 10000).ceil() * 10000),
        ),
    ];
    final PosBillResult paid = await t.pos.billing.pay(
      bill.uuid,
      tenders: s.tenders,
      shiftUuid: t.shift.uuid,
    );
    if (!offline) {
      expect(paid.bill.isPaid, isTrue);
      expect(
        paid.order.status,
        'delivered',
        reason: 'pay first: the order is closed while the kitchen cooks it',
      );
      expect(paid.bill.ticket!.number, s.number, reason: 'the talon');
      expect(paid.bill.packagingAmount, s.packaging);
      s.bill = paid.bill;
      s.receipt = await t.receipts.get(paid.bill.fiscal.receiptUuid!);
      t.sales.add(s);
      all.add(s);
      expectReceipt(s);
      await printLabels(
        t,
        number: s.number,
        items: s.items,
        pickupAt: s.pickup?.pickupAt,
      );
    }
    if (offline) {
      t.sales.add(s);
      all.add(s);
    }
    await clock.advance(1);
    return s;
  }

  /// The number reached the board as ready (after the expo for an order of several stations), the till hands it over.
  Future<void> handOver(
    String uuid,
    Till t, {
    required bool multi,
    required String what,
  }) async {
    final HeardEvent ready = await board.waitFor(
      (PosEvent e) =>
          e is PosQueueEvent &&
          e.orderUuid == uuid &&
          e.name == 'queue.ready' &&
          !e.called,
      timeout: const Duration(seconds: 90),
      what: '$what ready on the board',
    );
    final Map<String, Object?>? assembly = crew.assembly[uuid];
    if (multi) {
      expect(
        assembly,
        isNotNull,
        reason: '$what: cooked on several stations, it goes through the expo',
      );
      final DateTime? bumped = expo.bumped[uuid];
      expect(bumped, isNotNull, reason: '$what: the expo assembled it');
      expect(
        ready.at.isAfter(bumped!),
        isTrue,
        reason: '$what: ready only after the expo',
      );
      expoToBoard.add(ready.at.difference(bumped));
    } else {
      expect(assembly, isNull, reason: '$what: one station, no expo');
      expect(expo.bumped.containsKey(uuid), isFalse);
      final List<LineSeen> lines = crew.lines.values
          .where((LineSeen l) => l.orderUuid == uuid && l.bumpedAt != null)
          .toList();
      if (lines.isNotEmpty) {
        final DateTime last = lines
            .map((LineSeen l) => l.bumpedAt!)
            .reduce((DateTime a, DateTime b) => a.isAfter(b) ? a : b);
        bumpToBoard.add(ready.at.difference(last));
      }
    }
    await t.queue.handed(uuid);
    await board.waitFor(
      (PosEvent e) =>
          e is PosQueueEvent && e.orderUuid == uuid && e.name == 'queue.handed',
      what: '$what handed',
    );
  }

  Future<void> handOverSales(Iterable<Sale> sales) async {
    for (final Sale s in sales) {
      await handOver(
        s.uuid,
        s.till,
        multi: s.multiStation,
        what: '$s (${s.number})',
      );
    }
  }

  /// Both tills sell their part at the same time, each one after the other.
  Future<void> rush(List<Sale> a, List<Sale> b, {bool till2Offline = false}) =>
      Future.wait(<Future<void>>[
        () async {
          for (final Sale s in a) {
            await sell(till1, s);
          }
        }(),
        () async {
          for (final Sale s in b) {
            await sell(till2, s, offline: till2Offline);
          }
        }(),
      ]);

  /// The Mini App's `POST /orders`: a pickup at the branch, paid in cash at the counter.
  Future<Response<Object?>> postOrder(
    int customer,
    List<Map<String, Object?>> lines, {
    DateTime? pickupAt,
  }) => customers[customer].post<Object?>(
    '/v1/orders',
    options: Options(
      headers: <String, Object?>{'Idempotency-Key': till1.pos.http.newKey()},
    ),
    data: <String, Object?>{
      'store_id': seed.storeId,
      'branch_id': seed.branchId,
      'type': 'pickup',
      'payment_method': 'cash',
      'items': lines,
      'pickup_at': ?pickupAt?.toUtc().toIso8601String(),
    },
  );

  /// The customer's order card (`GET /orders?scope=active`).
  Future<Map<String, Object?>?> card(TgOrder o) async {
    final Response<Object?> r = await customers[o.customer].get<Object?>(
      '/v1/orders',
      queryParameters: <String, Object?>{'scope': 'active'},
    );
    final Object? data = asMap(r.data)['data'];
    final List<Map<String, Object?>> orders = data is List
        ? asList(data)
        : asList(asMap(data)['data']);
    for (final Map<String, Object?> order in orders) {
      if (order['uuid'] == o.uuid) return order;
    }
    return null;
  }

  /// A Telegram order: placed, heard and accepted at the first till (its number and its labels), checked on the
  /// customer's card (the combo parts, the packaging, the pickup time).
  Future<TgOrder> placeTelegram(TgOrder o) async {
    o.dishes = till1.menu.total(o.items);
    o.packaging = till1.menu.packaging(o.items);
    o.stations = till1.menu.stations(o.items);
    o.pickupAt = o.pickupIn == null
        ? null
        : DateTime.now().toUtc().add(o.pickupIn!);
    final Response<Object?> r;
    try {
      r = await postOrder(o.customer, <Map<String, Object?>>[
        for (final Item i in o.items) till1.menu.onlineLine(i),
      ], pickupAt: o.pickupAt);
    } on DioException catch (e) {
      fail('$o was refused: ${e.response?.statusCode} ${e.response?.data}');
    }
    final Map<String, Object?> data = asMap(asMap(r.data)['data']);
    expect(data['status'], 'new');
    o.uuid = data['order_uuid']! as String;
    step('telegram', 'placed $o');
    await till1.terminal.log!.waitFor(
      (PosEvent e) =>
          e is PosOrderEvent && e.isCreated && e.orderUuid == o.uuid,
      what: '$o: order.created',
    );
    final PosOnlineOrder accepted = await online.accept(o.uuid);
    o.acceptedAt = DateTime.now().toUtc();
    expect(accepted.status, 'accepted');
    expect(
      accepted.totalPrice,
      o.total,
      reason: '$o: the dishes ${o.dishes} and the packaging ${o.packaging}',
    );
    final Map<String, Object?> c = (await card(o))!;
    expect(money(c['total_price']), o.total, reason: '$o: the order card');
    expect(money(c['packaging_amount']), o.packaging);
    expectPackaging(
      asList(c['packaging_lines'])
          .map(
            (Map<String, Object?> l) => PosPackagingLine.fromJson(PosJson(l)),
          )
          .toList(),
      o.items,
      till1.menu,
      what: '$o card',
    );
    final List<Map<String, Object?>> cardLines = asList(c['items']);
    for (final Item i in o.items.where((Item i) => i.isCombo)) {
      final Map<String, Object?> line = cardLines.firstWhere(
        (Map<String, Object?> l) =>
            '${l['product_name'] ?? l['name']}' == i.name,
      );
      expect(
        <String>[
          for (final Map<String, Object?> p in asList(line['combo_parts']))
            '${p['label']}',
        ],
        <String>[
          for (final Part p in till1.menu.parts(i)) p.option.receiptLabel,
        ],
        reason: '$o: the parts on the card, without prices',
      );
    }
    if (o.pickupAt != null) {
      expect(
        dateOf(c['pickup_at']),
        isNotNull,
        reason: '$o: the card shows the pickup time',
      );
      expect(
        dateOf(c['pickup_at'])!.difference(o.pickupAt!).inSeconds.abs(),
        lessThanOrEqualTo(1),
      );
    }
    final PosOpenOrder queued = (await till1.pos.orders.openOrders())
        .firstWhere((PosOpenOrder x) => x.uuid == o.uuid);
    o.number = PosJson(queued.raw).objOrNull('queue')?.intOrNull('number');
    expect(
      o.number,
      isNotNull,
      reason: '$o: an online pickup takes a number when it is accepted',
    );
    await printLabels(
      till1,
      number: o.number!,
      items: o.items,
      customer: accepted.customerName,
      pickupAt: o.pickupAt,
    );
    telegram.add(o);
    return o;
  }

  Future<void> handOverTelegram(Iterable<TgOrder> orders) async {
    for (final TgOrder o in orders) {
      await handOver(
        o.uuid,
        till1,
        multi: o.multiStation,
        what: '$o (${o.number})',
      );
      final Response<Object?> r = await customers[o.customer].get<Object?>(
        '/v1/orders',
        queryParameters: <String, Object?>{'scope': 'history'},
      );
      final Object? data = asMap(r.data)['data'];
      final List<Map<String, Object?>> orders = data is List
          ? asList(data)
          : asList(asMap(data)['data']);
      expect(
        orders.firstWhere(
          (Map<String, Object?> x) => x['uuid'] == o.uuid,
        )['status'],
        'delivered',
        reason: '$o: handed over',
      );
    }
  }

  setUpAll(() async {
    final StackConfig stack = StackConfig.fromEnvironment();
    seed = stack.seed.section('fastfood');
    config = stack.withSeed(seed);
    step = StepLog(clock);
    stationIds = asMap(seed.json['stations']).map(
      (String k, Object? v) => MapEntry<String, int>(k, (v! as num).toInt()),
    );
    categories = asMap(seed.json['categories']).map(
      (String k, Object? v) => MapEntry<String, int>(k, (v! as num).toInt()),
    );
    bagRule = (asMap(seed.json['packaging'])['bag']! as num).toInt();
    cupRule = (asMap(seed.json['packaging'])['cup']! as num).toInt();
    owner = Dio(
      BaseOptions(
        baseUrl: config.apiUrl,
        headers: <String, Object?>{
          'Authorization': 'Bearer ${seed.ownerToken}',
          'Accept': 'application/json',
        },
      ),
    );
    customers = <Dio>[
      for (final Map<String, Object?> c in asList(seed.json['customers']))
        Dio(
          BaseOptions(
            baseUrl: config.apiUrl,
            headers: <String, Object?>{
              'Authorization': 'Bearer ${c['token']}',
              'Accept': 'application/json',
            },
          ),
        ),
    ];
    labelPrinter = await LabelPrinterSim.start();

    Future<Till> openTill(String key, String staff) async {
      final Cable cable = Cable();
      final SyncStore store = await SyncStore.open(MemorySyncStorage());
      final Terminal t = await Terminal.open(config, key, offlineStore: store);
      t.client.dio.interceptors.add(cable.interceptor);
      await t.signIn(staff);
      final Till till = Till(t, store, cable)
        ..queue = PosQueueRepository(t.pos.http)
        ..takeaway = PosTakeawayRepository(t.pos.http)
        ..receipts = FiscalReceiptRepository(t.pos.http);
      till.menu = FfMenu(
        await t.pos.catalog.load(
          PosChannel.takeaway,
          menuVersion: t.bootstrap.menuVersion,
        ),
        categories,
      );
      await t.listen();
      return till;
    }

    // One after the other: the first till to load its bootstrap gets the first block of the day
    till1 = await openTill('till1', 'cashier1');
    till2 = await openTill('till2', 'cashier2');
    online = OnlineOrderRepository(till1.pos.http);
    kds = await Terminal.open(config, 'kds');
    await kds.signIn('cook');
    expoScreen = await Terminal.open(config, 'expo');
    await expoScreen.signIn('expo');
    display = await Terminal.open(config, 'display');
    board = await display.listen();
    // The cooks: a few hundred ms a line (the coffee a bit longer); the fryer is paused for a while later
    crew = KitchenCrew(kds, <int, Duration>{
      stationIds['grill']!: const Duration(milliseconds: 400),
      stationIds['fryer']!: const Duration(milliseconds: 300),
      stationIds['drinks']!: const Duration(milliseconds: 150),
      stationIds['barista']!: const Duration(milliseconds: 600),
    });
    expo = ExpoWorker(expoScreen);
  });

  tearDownAll(() async {
    await crew.stop();
    await expo.stop();
    for (final Terminal t in <Terminal>[
      till1.terminal,
      till2.terminal,
      kds,
      expoScreen,
      display,
    ]) {
      await t.close();
    }
    await labelPrinter.stop();
    owner.close();
    for (final Dio c in customers) {
      c.close();
    }
    String stat(List<Duration> list) {
      if (list.isEmpty) return '-';
      final List<Duration> sorted = List<Duration>.of(list)..sort();
      return 'median ${sorted[(sorted.length - 1) ~/ 2].inMilliseconds} ms, max ${sorted.last.inMilliseconds} ms (${sorted.length})';
    }

    stdout.writeln(
      '\n[pos-e2e] fast food lunch: ${all.length} counter orders, ${telegram.length} Telegram, $stickers cup stickers '
      '($comboStickers in combos), $bagLabels bag labels; expo "Yig\'ildi" → ready on the board ${stat(expoToBoard)}; '
      'last bump → ready (one station) ${stat(bumpToBoard)}',
    );
  });

  group("Tushlik cho'qqisi (fast food)", () {
    test('the fast food format: stations with targets, quick modifiers, combos, labels, packaging and pickup rules', () async {
      for (final Till t in <Till>[till1, till2]) {
        final PosFormat f = t.terminal.bootstrap.format;
        expect(f.profile, 'fast_food');
        expect(f.firstScreen, PosFirstScreen.quickCounter);
        expect(
          f.modules.hasAll(<PosModule>[
            PosModule.quickCounter,
            PosModule.queueNumbers,
            PosModule.queueCalling,
            PosModule.kds,
            PosModule.combo,
            PosModule.quickModifiers,
            PosModule.labels,
            PosModule.kdsExpo,
            PosModule.packaging,
            PosModule.scheduledPickup,
          ]),
          isTrue,
          reason: '${t.label}: ${f.modules.enabled}',
        );
        expect(
          f.modules.has(PosModule.roundTheClock),
          isFalse,
          reason: '24/7 is off',
        );
      }
      final PosBootstrap boot = till1.terminal.bootstrap;

      // Five stations, each with its time target
      final Map<String, int?> targets = <String, int?>{
        for (final Map<String, Object?> s in asList(boot.raw['stations']))
          '${s['kind']}': (s['target_seconds'] as num?)?.toInt(),
      };
      expect(targets, <String, int?>{
        'grill': 60,
        'fryer': 20,
        'drinks': 30,
        'barista': 45,
        'expo': 30,
      });

      // Quick modifiers: the drink size one tap (0,4 l preselected), the meat as chips on the lavash
      final FfMenu menu = till1.menu;
      final PosModifierGroup size = menu.product('Pepsi').modifierGroups.single;
      expect(size.displayMode.wire, 'size');
      expect(
        size.modifiers.where((PosModifier m) => m.isDefault).single.name,
        '0,4 l',
      );
      final PosModifierGroup meat = menu
          .product('Lavash')
          .modifierGroups
          .single;
      expect(meat.displayMode.wire, 'inline');
      expect(
        meat.modifiers.where((PosModifier m) => m.isDefault).single.name,
        "Mol go'shtli",
      );

      // The combos: slots with their defaults (one tap is the default combo), every option sellable
      final PosCombo lavash = menu.product('Lavash kombo').combo!;
      expect(lavash.slots.map((PosComboSlot s) => s.name), <String>[
        'Asosiy',
        'Ichimlik',
        'Sous',
      ]);
      expect(
        lavash.slots.map((PosComboSlot s) => s.defaultOption!.receiptLabel),
        <String>[
          "1 ta lavash — katta, mol go'shtli",
          'Ichimlik — 0,4 l Pepsi',
          'Sous — achchiq',
        ],
      );
      expect(lavash.isOpen(), isTrue);
      final PosCombo burger = menu.product('Burger kombo').combo!;
      expect(burger.slots.map((PosComboSlot s) => s.name), <String>[
        'Asosiy',
        'Garnir',
        'Ichimlik',
      ]);
      expect(
        menu.option('Burger kombo', 'Garnir — Nagetslar (6 dona)').priceDelta,
        const PosMoney.som(5000),
      );

      // Stickers for the drinks and the coffee, a bag label; the label printer of the branch
      final PosLabelSettings labels = boot.labelSettings!;
      expect(labels.allItems, isFalse);
      expect(labels.categoryIds, <int>{
        categories['Ichimliklar']!,
        categories['Kofe']!,
      });
      expect(labels.perUnit && labels.bagLabel, isTrue);
      expect(labels.size, LabelSize.mm40x30);

      // Packaging and pickup rules for the offline price and the "Vaqtga" picker
      final PosTakeaway takeaway = PosTakeaway.of(boot.raw);
      expect(
        takeaway.packaging.map((PosPackagingRule r) => r.id).toSet(),
        <int>{bagRule, cupRule},
      );
      expect(takeaway.pickup!.minLeadMinutes, 20);
      expect(takeaway.pickup!.prepMinutes, 10);
      expect(takeaway.businessDay!.roundTheClock, isFalse);
      businessDate = takeaway.businessDay!.businessDate;

      // The owner points the label printer at the counter's printer (the emulator) in the panel; the tills reload
      final int printerId = (asMap(seed.json['label_printer'])['id']! as num)
          .toInt();
      await ownerCall(
        'PATCH',
        '/v1/owner/printers/$printerId',
        <String, Object?>{'address': '127.0.0.1', 'port': labelPrinter.port},
      );
      for (final Till t in <Till>[till1, till2]) {
        await t.pos.session.loadBootstrap();
        final PosPrinterInfo p = t.terminal.bootstrap.printers.firstWhere(
          (PosPrinterInfo p) => p.isLabel,
        );
        expect(p.port, labelPrinter.port);
        expect(p.connection, 'lan');
        t.shift = await t.pos.shifts.open(
          openingCash: const PosMoney.som(300000),
          note: 'Tushlik',
        );
        t.pos.session.updateShift(t.shift.toSummary());
      }
      final PosQueueBoard empty = await display.pos.display.queue();
      expect(empty.preparing, isEmpty);
      expect(empty.ready, isEmpty);
    });

    test('a pickup time too soon is refused; the owner shortens the lead for the lunch; an order is paid now for later', () async {
      final PosPickupRules rules = PosTakeaway.of(till1.terminal.bootstrap.raw)
          .pickup!;
      final List<PosPickupSlot> slots = await till1.takeaway.slots();
      expect(slots, isNotEmpty);
      final DateTime now = DateTime.now().toUtc();
      expect(
        slots.first.at.isBefore(
          now.add(Duration(minutes: rules.minLeadMinutes - 1)),
        ),
        isFalse,
        reason: 'the first slot is 20 minutes ahead',
      );
      expect(slots[1].at.difference(slots[0].at), const Duration(minutes: 15));

      scheduled = plan(
        items: const <Item>[
          Item.combo('Lavash kombo'),
          Item.dish('Kapuchino', modifiers: <String>['0,4 l']),
        ],
      );
      // The cashier opens it, then picks "Vaqtga": 5 minutes is too soon (20 is the rule)
      final int? number = await till1.store.takeQueueNumber(
        till1.queueState.businessDate!,
        till1.queueState.blocks,
      );
      final PosOrder draft = await till1.pos.orders.open(
        channel: PosChannel.takeaway,
        items: till1.menu.inputs(scheduled.items),
        queueNumber: number,
      );
      await expectLater(
        till1.takeaway.setPickupTime(
          draft.uuid,
          now.add(const Duration(minutes: 5)),
          version: draft.version,
        ),
        throwsA(
          isA<PosException>()
              .having((PosException e) => e.statusCode, 'status', 422)
              .having(
                (PosException e) => e.reason,
                'reason',
                'pickup_too_soon',
              ),
        ),
      );
      // Lunch: 1 minute ahead at the earliest, the kitchen takes it 1 minute before
      final Map<String, Object?> pickup = await ownerCall(
        'PUT',
        '/v1/owner/branches/${seed.branchId}/pickup-settings',
        <String, Object?>{
          'pickup_min_lead_minutes': 1,
          'pickup_prep_minutes': 1,
          'pickup_slot_minutes': 5,
        },
      );
      expect(pickup['pickup_min_lead_minutes'], 1);

      // Paid now for 90 seconds later: held, off the kitchen display and the board, fired 30 seconds from now
      scheduled = Sale(
        scheduled.index,
        scheduled.items,
        pickupIn: const Duration(seconds: 90),
      );
      await sell(till1, scheduled, opened: draft);
      final PosPickupTime p = scheduled.pickup!;
      expect(p.waiting, isTrue);
      expect(
        p.kitchenFireAt,
        p.pickupAt.subtract(const Duration(minutes: 1)),
        reason: 'the kitchen time is the pickup time less the prep time',
      );
      expect(p.localTime, isNotEmpty, reason: 'the talon shows the time');
      final PosOrder held = await till1.pos.orders.get(scheduled.uuid);
      expect(
        held.items.every(
          (PosOrderItem i) =>
              i.fireMode == PosFireMode.hold && i.sentAt == null,
        ),
        isTrue,
        reason: 'its lines wait',
      );
      expect(
        (await crew.fetch()).where(
          (Map<String, Object?> t) => t['uuid'] == scheduled.uuid,
        ),
        isEmpty,
        reason: 'not on the kitchen display yet',
      );
      expect(
        (await display.pos.display.queue()).preparing.map(
          (PosQueueEntry e) => e.orderUuid,
        ),
        isNot(contains(scheduled.uuid)),
      );
    });

    test("the first sale: the owner's lavash kombo — one line, the parts without prices, each part on its own station, the expo assembles it", () async {
      crew.start();
      final int stickersBefore = stickers;
      final int comboStickersBefore = comboStickers;
      final int bagsBefore = bagLabels;
      expo.start();
      final Sale s = await sell(
        till1,
        plan(
          items: const <Item>[
            Item.combo('Lavash kombo'),
            Item.dish('Pepsi', modifiers: <String>['0,5 l']),
          ],
        ),
      );
      // The owner's decision: one line, the combo's total and quantity; the parts below it without prices
      final PosBillItem line = s.bill!.items.firstWhere(
        (PosBillItem i) => i.productName == 'Lavash kombo',
      );
      expect(
        <String>[
          for (final Map<String, Object?> p in asList(
            asList(s.bill!.raw['items']).firstWhere(
              (Map<String, Object?> i) => i['product_name'] == 'Lavash kombo',
            )['combo_parts'],
          ))
            '${p['label']}',
        ],
        <String>[
          "1 ta lavash — katta, mol go'shtli",
          'Ichimlik — 0,4 l Pepsi',
          'Sous — achchiq',
        ],
      );
      expect(line.orderItemId, isPositive);
      // The fiscal receipt: one line with the combo's own MXIK, the bag and the Pepsi's cup apart
      final PosFiscalLine combo = s.receipt!.lines.singleWhere(
        (PosFiscalLine l) => l.mxikCode == mxik['Lavash kombo'],
      );
      expect(combo.amount, const PosMoney.som(45000));
      expect(
        s.receipt!.lines.where(
          (PosFiscalLine l) => l.mxikCode == mxik['Lavash'],
        ),
        isEmpty,
      );
      expect(
        s.receipt!.lines,
        hasLength(4),
        reason: 'the combo, the Pepsi, the bag, the cup',
      );

      // The kitchen: the lavash and the sauce at the grill, the drink at the drinks station, each with the combo's name
      await handOver(s.uuid, s.till, multi: true, what: '$s');
      final List<LineSeen> parts = crew.lines.values
          .where((LineSeen l) => l.orderUuid == s.uuid && l.comboName != null)
          .toList();
      expect(parts.map((LineSeen l) => l.comboName).toSet(), <String>{
        'Lavash kombo',
      });
      expect(
        <String, String>{
          for (final LineSeen l in parts)
            l.productName: stationKey(l.stationId),
        },
        <String, String>{
          'Lavash': 'grill',
          'Pepsi': 'drinks',
          'Achchiq sous': 'grill',
        },
      );
      expect(
        parts.every((LineSeen l) => l.targetSeconds != null),
        isTrue,
        reason: 'every line has its target',
      );
      final Map<String, Object?> assembly = crew.assembly[s.uuid]!;
      expect(assembly['station_id'], stationIds['expo']);
      expect(
        asList(assembly['stations'])
            .map((Map<String, Object?> x) => x['station_id'])
            .toSet(),
        <Object?>{stationIds['grill'], stationIds['drinks']},
      );
      // A sticker for each drink (the combo's Pepsi with the combo's name) and the bag label
      expect(comboStickers - comboStickersBefore, 1);
      expect(stickers - stickersBefore, 2);
      expect(bagLabels - bagsBefore, 1);
    });

    test(
      'the rush: both tills at once, pay first; one guest brings their own bag',
      () async {
        final List<Sale> a = <Sale>[
          for (var i = 0; i < 22; i++) plan(removeBag: i == 5),
        ];
        // A few orders cooked on one station only: no expo for them
        a.addAll(<Sale>[
          plan(
            items: const <Item>[
              Item.dish('Kapuchino', modifiers: <String>['0,3 l']),
            ],
          ),
          plan(items: const <Item>[Item.dish('Kartoshka fri', quantity: 2)]),
          plan(
            items: const <Item>[
              Item.dish('Fanta', quantity: 3, modifiers: <String>['0,4 l']),
            ],
          ),
        ]);
        await rush(
          <Sale>[for (var i = 0; i < a.length; i += 2) a[i]],
          <Sale>[for (var i = 1; i < a.length; i += 2) a[i]],
        );
        await handOverSales(a);
        final Sale own = a[5];
        expect(own.removeBag, isTrue);
        final PosOrder o = await own.till.pos.orders.get(own.uuid);
        final PosPackagingLine bag = o.packagingLines.firstWhere(
          (PosPackagingLine l) => l.ruleId == bagRule,
        );
        expect(bag.removed, isTrue);
        expect(bag.removedReason, bagReason);
        expect(crew.refused, isEmpty);
        expect(expo.refused, isEmpty);
      },
    );

    test('Telegram orders come in: combos, a stale pick is refused, two for a later time; cooked, assembled, handed', () async {
      // A pick of another combo is refused with its reason (the Mini App reloads the menu)
      final Map<String, Object?> wrong =
          till1.menu.onlineLine(const Item.combo('Lavash kombo'))
            ..['combo'] = <Map<String, Object?>>[
              <String, Object?>{
                'option_id': till1.menu
                    .option('Burger kombo', 'Asosiy — Gamburger (Dona)')
                    .uuid,
              },
            ];
      try {
        await postOrder(0, <Map<String, Object?>>[wrong]);
        fail('a stale pick was taken');
      } on DioException catch (e) {
        expect(e.response?.statusCode, 422);
        expect('${e.response?.data}', contains('combo_option_invalid'));
      }

      final List<TgOrder> orders = <TgOrder>[];
      for (var i = 0; i < telegramOrders; i++) {
        final List<Item> items = switch (i % 5) {
          0 => const <Item>[
            Item.combo('Lavash kombo'),
            Item.dish('Fanta', modifiers: <String>['0,3 l']),
          ],
          1 => const <Item>[
            Item.combo(
              'Burger kombo',
              picks: <String>['Garnir — Nagetslar (6 dona)'],
            ),
          ],
          2 => const <Item>[
            Item.dish('Lavash', modifiers: <String>["Tovuq go'shtli"]),
            Item.dish('Pepsi', modifiers: <String>['0,4 l']),
          ],
          3 => const <Item>[
            Item.dish('Latte', modifiers: <String>['0,4 l']),
          ],
          _ => const <Item>[
            Item.combo(
              'Lavash kombo',
              picks: <String>['Ichimlik — 0,5 l Pepsi', 'Sous — sarimsoqli'],
            ),
            Item.dish('Suv'),
          ],
        };
        // Two for a later time: the kitchen gets them 1 minute before
        final Duration? later = i == 3
            ? const Duration(seconds: 100)
            : (i == 8 ? const Duration(seconds: 130) : null);
        orders.add(
          TgOrder(i + 1, i % customers.length, items, pickupIn: later),
        );
      }

      // They come in while the tills keep selling
      final List<Sale> b = <Sale>[for (var i = 0; i < 14; i++) plan()];
      await Future.wait(<Future<void>>[
        () async {
          for (final TgOrder o in orders) {
            await placeTelegram(o);
          }
        }(),
        rush(
          <Sale>[for (var i = 0; i < b.length; i += 2) b[i]],
          <Sale>[for (var i = 1; i < b.length; i += 2) b[i]],
        ),
      ]);
      // The ones for later wait: off the kitchen display and the board
      final List<Map<String, Object?>> tickets = await crew.fetch();
      for (final TgOrder o in orders.where((TgOrder o) => o.pickupIn != null)) {
        expect(
          tickets.where((Map<String, Object?> t) => t['uuid'] == o.uuid),
          isEmpty,
          reason: '$o waits for its kitchen time',
        );
        expect(
          (await display.pos.display.queue()).preparing.map(
            (PosQueueEntry e) => e.orderUuid,
          ),
          isNot(contains(o.uuid)),
        );
      }
      await handOverSales(b);
      await handOverTelegram(orders.where((TgOrder o) => o.pickupIn == null));
      // Each combo part reached its station, with the combo's name
      for (final TgOrder o in orders.where((TgOrder o) => o.pickupIn == null)) {
        final Set<String> seen = <String>{
          for (final LineSeen l in crew.lines.values)
            if (l.orderUuid == o.uuid) stationKey(l.stationId),
        };
        expect(seen, o.stations, reason: '$o');
      }
    });

    test('the fryer falls behind: its lines turn late on the display while the other stations keep up', () async {
      crew.paused.add(stationIds['fryer']!);
      final List<Sale> c = <Sale>[
        for (var i = 0; i < 6; i++)
          plan(
            items: <Item>[
              if (i.isEven)
                const Item.combo('Burger kombo')
              else
                const Item.dish('Nagetslar'),
              if (i % 3 == 0)
                const Item.dish('Pepsi', modifiers: <String>['0,3 l']),
            ],
          ),
      ];
      await rush(
        <Sale>[for (var i = 0; i < c.length; i += 2) c[i]],
        <Sale>[for (var i = 1; i < c.length; i += 2) c[i]],
      );
      final int fryer = stationIds['fryer']!;
      // Fries are late after 20 s, nuggets (their own target) after 30 s
      await eventuallyTrue(
        () => c.every(
          (Sale s) => crew.lines.values
              .where(
                (LineSeen l) => l.orderUuid == s.uuid && l.stationId == fryer,
              )
              .every((LineSeen l) => l.lateSeen),
        ),
        what: 'every waiting fryer line late on the display',
        timeout: const Duration(seconds: 60),
      );
      final List<LineSeen> fryerLines = crew.lines.values
          .where(
            (LineSeen l) =>
                c.any((Sale s) => s.uuid == l.orderUuid) &&
                l.stationId == fryer,
          )
          .toList();
      expect(fryerLines, isNotEmpty);
      expect(
        fryerLines
            .where((LineSeen l) => l.productName == 'Nagetslar')
            .every((LineSeen l) => l.targetSeconds == 30),
        isTrue,
        reason: "the nuggets' own target",
      );
      expect(
        fryerLines
            .where((LineSeen l) => l.productName == 'Kartoshka fri')
            .every((LineSeen l) => l.targetSeconds == 20),
        isTrue,
      );
      // The grill and the drinks of the same orders were bumped in time
      final List<LineSeen> others = crew.lines.values
          .where(
            (LineSeen l) =>
                c.any((Sale s) => s.uuid == l.orderUuid) &&
                l.stationId != fryer,
          )
          .toList();
      expect(
        others.every((LineSeen l) => !l.lateSeen && l.readyAt != null),
        isTrue,
      );
      // The burger kombos wait on the expo for the fryer: nothing is ready on the board yet
      expect(
        board.where(
          (PosEvent e) =>
              e is PosQueueEvent &&
              e.name == 'queue.ready' &&
              c.any((Sale s) => s.uuid == e.orderUuid),
        ),
        isEmpty,
      );
      crew.paused.remove(fryer);
      await handOverSales(c);
    });

    test('the second till loses the internet, sells a combo offline and syncs', () async {
      till2.cable.cut = true;
      till2.pos.connectivity.reportFailure(
        const SocketException('e2e: the cable is out'),
      );
      expect(till2.pos.offline!.active, isTrue);
      final List<Sale> off = <Sale>[
        plan(
          items: const <Item>[
            Item.combo(
              'Lavash kombo',
              picks: <String>['Ichimlik — 0,4 l Fanta'],
            ),
          ],
        ),
        plan(
          items: const <Item>[
            Item.combo(
              'Burger kombo',
              picks: <String>['Garnir — Nagetslar (6 dona)'],
            ),
            Item.dish('Pepsi', modifiers: <String>['0,5 l']),
          ],
        ),
        plan(
          items: const <Item>[
            Item.dish('Lavash', modifiers: <String>["Mol go'shtli"]),
            Item.dish('Suv', quantity: 2),
          ],
        ),
        plan(
          items: const <Item>[
            Item.dish('Chizburger'),
            Item.dish('Kartoshka fri'),
          ],
        ),
      ];
      expect(off, hasLength(offlineOrders));
      final List<Sale> on = <Sale>[for (var i = 0; i < 4; i++) plan()];
      await rush(on, off, till2Offline: true);
      expect(till2.store.pendingOrders, offlineOrders);
      await handOverSales(on);

      till2.cable.cut = false;
      till2.pos.connectivity.reportReachable();
      final SyncDrainResult sync = await till2.pos.offline!.syncNow();
      expect(sync.error, isNull);
      expect(sync.left, 0);
      final Map<String, SyncOperation> opened = <String, SyncOperation>{
        for (final SyncOperation op in till2.store.answered)
          if (op.type == SyncOpType.orderOpen)
            op.payload['order']! as String: op,
      };
      for (final Sale s in off) {
        final SyncOperation op = opened[s.localUuid]!;
        expect(
          op.status,
          SyncOpStatus.applied,
          reason: '${op.reason} ${op.message}',
        );
        s.uuid = till2.store.resolve(s.localUuid!);
        final PosOrder synced = await till2.pos.orders.get(s.uuid);
        expect(
          synced.status,
          'delivered',
          reason: 'paid offline, closed on sync',
        );
        expect(synced.queue!.number, s.number);
        expect(
          synced.totalPrice,
          s.total,
          reason:
              '$s: the server agrees with the till (no offline_price_mismatch)',
        );
        expectPackaging(
          synced.packagingLines,
          s.items,
          till2.menu,
          what: '$s synced',
        );
        for (final Item i in s.items.where((Item i) => i.isCombo)) {
          final PosOrderItem line = synced.items.firstWhere(
            (PosOrderItem l) => l.productName == i.name,
          );
          expect(
            <String>[for (final PosComboPart p in line.comboParts!) p.label],
            <String>[
              for (final Part p in till2.menu.parts(i)) p.option.receiptLabel,
            ],
          );
        }
        final PosBilling bills = await till2.pos.billing.bills(s.uuid);
        s.bill = bills.bills.firstWhere((PosBill b) => b.isPaid);
        s.receipt = await till2.receipts.get(s.bill!.fiscal.receiptUuid!);
        expectReceipt(s);
        await till2.store.noteQueueNumber(
          till2.queueState.businessDate!,
          s.number,
        );
      }
      await handOverSales(off);
    });

    test('the order for later fires to the kitchen at its kitchen time, and so do the Telegram ones', () async {
      final PosPickupTime p = scheduled.pickup!;
      final PosOrder fired = await eventually<PosOrder>(
        () async {
          final PosOrder o = await till1.pos.orders.get(scheduled.uuid);
          return o.pickup?.kitchenFiredAt != null ? o : null;
        },
        what: 'the order for later fired',
        timeout: const Duration(seconds: 120),
        every: const Duration(milliseconds: 500),
      );
      final DateTime firedAt = fired.pickup!.kitchenFiredAt!;
      expect(
        firedAt.isBefore(p.kitchenFireAt!),
        isFalse,
        reason: 'not before its kitchen time',
      );
      expect(
        firedAt.difference(p.kitchenFireAt!),
        lessThan(const Duration(seconds: 15)),
        reason:
            'at its kitchen time (the minute job, run every few seconds here)',
      );
      expect(fired.items.every((PosOrderItem i) => i.sentAt != null), isTrue);
      final HeardEvent preparing = await board.waitFor(
        (PosEvent e) =>
            e is PosQueueEvent &&
            e.orderUuid == scheduled.uuid &&
            e.name == 'queue.preparing',
        what: 'the number on the board when it fired',
      );
      expect(
        preparing.at.toUtc().isAfter(
          p.kitchenFireAt!.subtract(const Duration(seconds: 2)),
        ),
        isTrue,
      );
      await handOverSales(<Sale>[scheduled]);

      final List<TgOrder> later = telegram
          .where((TgOrder o) => o.pickupIn != null)
          .toList();
      await handOverTelegram(later);
      for (final TgOrder o in later) {
        final List<LineSeen> lines = crew.lines.values
            .where((LineSeen l) => l.orderUuid == o.uuid)
            .toList();
        expect(lines, isNotEmpty);
        // The kitchen's clock starts when the order reached it, not when it was accepted long before
        expect(
          lines.where((LineSeen l) => l.lateSeen),
          isEmpty,
          reason:
              '$o: fired just now, not late (${lines.map((LineSeen l) => '${l.productName} ${l.targetSeconds}').join(', ')})',
        );
      }
    });

    test('the combo fiscal mode is switched to split: the parts are fiscal lines whose shares add up to the tiyin', () async {
      await ownerCall('PUT', '/v1/owner/fiscal/settings', <String, Object?>{
        'fiscal_settings': <String, Object?>{'combo_fiscal_mode': 'split'},
      });
      final Map<String, Object?> settings = await ownerCall(
        'GET',
        '/v1/owner/fiscal/settings',
      );
      expect('$settings', contains('split'));
      fiscalMode = 'split';
      final List<Sale> d = <Sale>[
        plan(items: const <Item>[Item.combo('Lavash kombo')]),
        plan(
          items: const <Item>[
            Item.combo(
              'Lavash kombo',
              picks: <String>['Ichimlik — 0,5 l Pepsi', 'Sous — sarimsoqli'],
            ),
          ],
        ),
        plan(
          items: const <Item>[
            Item.combo(
              'Burger kombo',
              quantity: 2,
              picks: <String>['Garnir — Nagetslar (6 dona)'],
            ),
            Item.dish('Suv'),
          ],
        ),
      ];
      await rush(<Sale>[d[0], d[2]], <Sale>[d[1]]);
      await handOverSales(d);
      // Back to one line per combo (the owner's decision)
      await ownerCall('PUT', '/v1/owner/fiscal/settings', <String, Object?>{
        'fiscal_settings': <String, Object?>{'combo_fiscal_mode': 'single'},
      });
      fiscalMode = 'single';
      final int left = counterOrders - all.length;
      expect(left, greaterThan(0));
      final List<Sale> e = <Sale>[for (var i = 0; i < left; i++) plan()];
      await rush(
        <Sale>[for (var i = 0; i < e.length; i += 2) e[i]],
        <Sale>[for (var i = 1; i < e.length; i += 2) e[i]],
      );
      await handOverSales(e);
      expect(all, hasLength(counterOrders));
      expect(telegram, hasLength(telegramOrders));
      final PosQueueBoard end = await display.pos.display.queue();
      expect(
        end.preparing.map((PosQueueEntry e) => '${e.number} ${e.orderUuid}'),
        isEmpty,
      );
      expect(
        end.ready.map((PosQueueEntry e) => '${e.number} ${e.orderUuid}'),
        isEmpty,
      );
      expect(crew.refused, isEmpty);
      expect(expo.refused, isEmpty);
    });

    test('every combo part reached its station; every order of several stations was assembled on the expo', () {
      for (final Sale s in all) {
        final Set<String> seen = <String>{
          for (final LineSeen l in crew.lines.values)
            if (l.orderUuid == s.uuid) stationKey(l.stationId),
        };
        expect(seen, s.stations, reason: '$s');
        expect(expo.bumped.containsKey(s.uuid), s.multiStation, reason: '$s');
      }
      final int comboParts = crew.lines.values
          .where((LineSeen l) => l.comboName != null)
          .length;
      expect(comboParts, greaterThan(20));
      // Stickers: every drink and coffee, those of combos with the combo's name; a bag label per order
      expect(comboStickers, greaterThan(5));
      expect(
        bagLabels,
        all.where((Sale s) => !s.offline).length + telegram.length,
      );
      expect(
        labelPrinter.printed.every(
          (({String title, String text, TsplDocument doc}) p) =>
              p.doc.size == LabelSize.mm40x30,
        ),
        isTrue,
      );
    });

    test('Z on both tills: the receipts are the sales, the packaging apart, the drawer is right', () async {
      for (final Till t in <Till>[till1, till2]) {
        final PosMoney card = PosMoney.sum(<PosMoney>[
          for (final Sale s in t.sales)
            if (s.card) s.total,
        ]);
        final PosMoney cash = PosMoney.sum(<PosMoney>[
          for (final Sale s in t.sales)
            if (!s.card) s.total,
        ]);
        final PosMoney packaging = PosMoney.sum(
          t.sales.map((Sale s) => s.packaging),
        );
        final PosMoney receipts = PosMoney.sum(
          t.sales.map((Sale s) => s.receipt!.total),
        );
        final PosShiftWithReport x = await t.pos.shifts.xReport(t.shift.uuid);
        final Map<String, Object?> sales = asMap(x.report!.raw['sales']);
        expect(sales['bills'], t.sales.length, reason: t.label);
        expect(
          tenderAmount(x.report!.raw, 'card'),
          card,
          reason: '${t.label}: card',
        );
        expect(
          tenderAmount(x.report!.raw, 'cash'),
          cash,
          reason: '${t.label}: cash',
        );
        expect(
          money(sales['bills_total']),
          receipts,
          reason: '${t.label}: the receipts add up to the sales',
        );
        expect(
          money(sales['packaging']),
          packaging,
          reason: '${t.label}: the packaging on its own row',
        );
        expect(
          money(sales['subtotal']),
          PosMoney.sum(t.sales.map((Sale s) => s.dishes)),
          reason: '${t.label}: the dishes without the packaging',
        );
        final PosMoney expected = x.report!.expectedCash;
        expect(expected, const PosMoney.som(300000) + cash);
        final PosShift z = await t.pos.shifts.close(
          t.shift.uuid,
          countedBreakdown: banknotes(expected),
        );
        expect(z.status, 'closed');
        expect(
          z.difference,
          PosMoney.zero,
          reason: '${t.label}: the drawer is right',
        );
        expect(
          money(
            asMap(
              asMap(z.raw['summary'] ?? z.summary!.raw)['sales'],
            )['packaging'],
          ),
          packaging,
          reason: '${t.label}: Z',
        );
        t.pos.session.updateShift(null);
      }
    });

    test('the prep time report matches the timestamps the kitchen display showed', () async {
      // When each online line reached the kitchen: its acceptance, or for a later pickup its kitchen time (when the
      // display starts showing it), whichever is later
      final Map<String, DateTime> startOf = <String, DateTime>{};
      for (final MapEntry<String, Map<String, Object?>> t
          in crew.tickets.entries) {
        final DateTime? accepted = dateOf(t.value['accepted_at']);
        final Object? pickup = t.value['pickup'];
        final DateTime? fireAt = pickup is Map
            ? dateOf(pickup['kitchen_fire_at'])
            : null;
        final DateTime? start =
            fireAt != null && (accepted == null || fireAt.isAfter(accepted))
            ? fireAt
            : accepted;
        if (start != null) startOf[t.key] = start;
      }
      final List<LineSeen> lines = crew.lines.values.toList();
      for (final LineSeen l in lines) {
        l.startedAt = l.online ? startOf[l.orderUuid] : l.sentAt;
        expect(l.startedAt, isNotNull, reason: '${l.orderUuid}/${l.id}');
        expect(
          l.readyAt,
          isNotNull,
          reason: '${l.orderUuid}/${l.id} ${l.productName} was bumped',
        );
      }

      final Response<Object?> r = await owner.get<Object?>(
        '/v1/owner/reports/kitchen-times',
        queryParameters: <String, Object?>{
          'branch_id': seed.branchId,
          'from': businessDate,
          'to': businessDate,
        },
      );
      final Map<String, Object?> report = asMap(asMap(r.data)['data']);
      Map<String, Object?> stats(Iterable<LineSeen> group) {
        final List<int> seconds = <int>[
          for (final LineSeen l in group) l.seconds!,
        ]..sort();
        final List<LineSeen> withTarget = group
            .where((LineSeen l) => l.targetSeconds != null)
            .toList();
        return <String, Object?>{
          'count': seconds.length,
          'median_seconds': percentile(seconds, 0.5),
          'p90_seconds': percentile(seconds, 0.9),
          'with_target': withTarget.length,
          'over_target': withTarget
              .where((LineSeen l) => l.seconds! > l.targetSeconds!)
              .length,
        };
      }

      Map<String, Object?> pick(Map<String, Object?> row) => <String, Object?>{
        for (final String k in <String>[
          'count',
          'median_seconds',
          'p90_seconds',
          'with_target',
          'over_target',
        ])
          k: row[k],
      };
      expect(
        pick(asMap(report['summary'])),
        stats(lines),
        reason: 'the whole kitchen',
      );
      for (final Map<String, Object?> row in asList(report['stations'])) {
        final int? id = (row['station_id'] as num?)?.toInt();
        expect(
          pick(row),
          stats(lines.where((LineSeen l) => l.stationId == id)),
          reason: 'station ${row['name']}',
        );
      }
      final Map<String, Object?> fryer = asList(report['stations']).firstWhere(
        (Map<String, Object?> s) => s['station_id'] == stationIds['fryer'],
      );
      expect(
        (fryer['over_target']! as num).toInt(),
        greaterThanOrEqualTo(4),
        reason: 'the fryer fell behind',
      );
      final Map<String, Object?> grill = asList(report['stations']).firstWhere(
        (Map<String, Object?> s) => s['station_id'] == stationIds['grill'],
      );
      expect(grill['over_target'], 0, reason: 'the grill kept up');

      // The expo: from the last line ready to "Yig'ildi", every assembled order
      final Map<String, Object?> expoRow = asMap(report['expo']);
      final List<int> assembly = <int>[];
      for (final MapEntry<String, DateTime?> e in expo.assembledAt.entries) {
        final List<DateTime> ready = <DateTime>[
          for (final LineSeen l in lines)
            if (l.orderUuid == e.key && l.readyAt != null) l.readyAt!,
        ];
        if (ready.isEmpty || e.value == null) continue;
        assembly.add(
          e.value!
              .difference(
                ready.reduce((DateTime a, DateTime b) => a.isAfter(b) ? a : b),
              )
              .inSeconds
              .clamp(0, 1 << 30),
        );
      }
      assembly.sort();
      expect(expoRow['count'], assembly.length);
      expect(expoRow['median_seconds'], percentile(assembly, 0.5));
      expect(expoRow['station_id'], stationIds['expo']);
    });
  });
}

/// Polls [probe] until it is true.
Future<void> eventuallyTrue(
  bool Function() probe, {
  required String what,
  Duration timeout = const Duration(seconds: 30),
}) async {
  final Stopwatch watch = Stopwatch()..start();
  while (!probe()) {
    if (watch.elapsed > timeout) {
      throw TimeoutException(
        'Timed out after ${timeout.inSeconds}s waiting for $what',
      );
    }
    await Future<void>.delayed(const Duration(milliseconds: 200));
  }
}

/// Polls until [probe] returns a value (not `null`).
Future<T> eventually<T extends Object>(
  Future<T?> Function() probe, {
  required String what,
  Duration timeout = const Duration(seconds: 30),
  Duration every = const Duration(milliseconds: 200),
}) async {
  final Stopwatch watch = Stopwatch()..start();
  Object? last;
  while (watch.elapsed < timeout) {
    try {
      final T? value = await probe();
      if (value != null) return value;
    } on Object catch (e) {
      last = e;
    }
    await Future<void>.delayed(every);
  }
  throw TimeoutException(
    'Timed out after ${timeout.inSeconds}s waiting for $what${last == null ? '' : ' (last error: $last)'}',
  );
}
