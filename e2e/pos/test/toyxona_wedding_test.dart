/// P6-59 "200 mehmonli to'y" against the real stack: the wedding hall of the seed (`toyxona`, profile `toyxona`, its
/// own store "Rizqim To'yxona") sells an event months of work in one day. Two managers (the till and the web panel)
/// hold the same hall and slot at once: one wins, the other is told `slot_taken`. The hold is frozen on the contract
/// day and numbered; the deposit is paid in cash at the till (an ADVANCE receipt, the event `confirmed`); the payment
/// reminder command is idempotent; the interim is paid in two parts (card at the till, a bank transfer the owner
/// records); a guest change re-plans the unpaid rows only; a room inside the booked hall cannot be sold for that time;
/// the production sheet and the osh calculator give the numbers for 200 guests. On the day the event opens as a tab,
/// drinks and skewers go to the kitchen, 185 guests come (billed at the guaranteed 200), another event has more guests
/// than guaranteed; the final settlement is ONE sale receipt at the event total with every advance as its prepayment
/// part ("chek summasi = schyot summasi") and the rest paid at the till; an over-paid event gives the excess back; a
/// third event cancelled 20 days ahead refunds 70 % and keeps 30 % (their receipts); X and Z on the till (banquet
/// advances, cash expected, difference 0), the owner's "Tadbirlar" report and its Excel file, and the waiters' event
/// pay. The till, the manager's tablet and the kitchen display are pos_data clients; the panel and the owner call the
/// HTTP API directly. Nothing about speed is recorded.
@Timeout(Duration(minutes: 8))
library;

import 'dart:async';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/backend.dart';
import 'support/kitchen.dart';
import 'support/menu.dart';
import 'support/stack.dart';
import 'support/xlsx.dart';

const PosMoney openingCash = PosMoney.som(500000);

Map<String, Object?> asMap(Object? value) =>
    (value! as Map<Object?, Object?>).cast<String, Object?>();

List<Map<String, Object?>> asList(Object? value) => <Map<String, Object?>>[
  for (final Object? e in (value as List<Object?>?) ?? const <Object?>[])
    asMap(e),
];

PosMoney money(Object? value) => PosMoney.parse(value);

PosMoney som(int value) => PosMoney.som(value);

double number(Object? value) => double.parse('$value');

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

/// The JSON API through a terminal's own HTTP layer (pos_data's `PosHttp`: its token, its staff PIN session, its
/// Idempotency-Key on every write): the banquet screens' calls, whatever pos_data model is still being written.
class Api {
  Api(this.terminal);

  final Terminal terminal;

  Future<Map<String, Object?>> get(
    String path, [
    Map<String, Object?>? query,
  ]) => terminal.pos.http.get(
    '/v1$path',
    (Object? d) => d == null ? <String, Object?>{} : asMap(d),
    query: query,
  );

  Future<Map<String, Object?>> send(
    String method,
    String path, [
    Object? body,
    String? key,
  ]) => terminal.pos.http.mutate(
    method,
    '/v1$path',
    (Object? d) => d == null ? <String, Object?>{} : asMap(d),
    body: body ?? const <String, Object?>{},
    idempotencyKey: key,
  );

  /// The server's refusal of [call]: its reason (`data.reason`).
  Future<PosException> refused(Future<Object?> Function() call) async {
    try {
      await call();
    } on PosException catch (e) {
      return e;
    }
    fail('the server accepted what it should refuse');
  }
}

/// A web panel session (a normal sign-in token): every answer is returned, the caller checks the status.
class Panel {
  Panel(String apiUrl, String token)
    : dio = Dio(
        BaseOptions(
          baseUrl: apiUrl,
          headers: <String, Object?>{
            'Authorization': 'Bearer $token',
            'Accept': 'application/json',
          },
          validateStatus: (_) => true,
        ),
      );

  final Dio dio;

  Future<Response<Object?>> call(
    String method,
    String path, {
    Object? data,
    Map<String, Object?>? query,
    String? key,
  }) => dio.request<Object?>(
    '/v1$path',
    data: data,
    queryParameters: query,
    options: Options(
      method: method,
      headers: <String, Object?>{
        if (method != 'GET') 'Idempotency-Key': key ?? newIdempotencyKey(),
      },
    ),
  );

  /// `data` of a successful answer (fails the test with the server's message otherwise).
  Future<Map<String, Object?>> ok(
    String method,
    String path, {
    Object? data,
    Map<String, Object?>? query,
  }) async {
    final Response<Object?> r = await call(
      method,
      path,
      data: data,
      query: query,
    );
    expect(
      r.statusCode! < 300,
      isTrue,
      reason: '$method $path: ${r.statusCode} ${r.data}',
    );
    final Object? d = asMap(r.data)['data'];
    return d == null ? <String, Object?>{} : asMap(d);
  }

  void close() => dio.close();
}

/// What the test paid on the till, to check the X report and the drawer against.
class Ledger {
  PosMoney cashAdvances = PosMoney.zero;
  PosMoney cardAdvances = PosMoney.zero;
  int advanceCount = 0;
  PosMoney cashRefunds = PosMoney.zero;
  PosMoney cardRefunds = PosMoney.zero;
  int refundCount = 0;
  PosMoney cashSales = PosMoney.zero;
  PosMoney cardSales = PosMoney.zero;

  void advance(String tender, PosMoney amount) {
    advanceCount++;
    if (tender == 'cash') {
      cashAdvances += amount;
    } else {
      cardAdvances += amount;
    }
  }

  void refund(String tender, PosMoney amount) {
    refundCount++;
    if (tender == 'cash') {
      cashRefunds += amount;
    } else {
      cardRefunds += amount;
    }
  }
}

void main() {
  late Seed seed;
  late StackConfig config;
  late Terminal till; // the cashier's till, with the shift
  late Terminal tablet; // the first manager's till
  late Terminal kds;
  late Api cashier;
  late Api manager;
  late Panel panel; // the second manager, in the web panel
  late Panel owner;
  late FiscalReceiptRepository receipts;
  late Menu menu;
  late PosShift shift;
  late String today;
  final Ledger ledger = Ledger();

  late int white;
  late int blue;
  late Map<String, Object?> packages;
  late Map<String, Object?> extras;

  // The events of the drill
  late String wedding; // A: 200 guests, the white hall tonight
  late String second; // C: the blue hall in 3 days, more guests than guaranteed
  late String cancelled; // B: the white hall in 20 days, cancelled
  late String
  overpaid; // D: the blue hall today (day slot), paid over its total

  String day(int offset) =>
      DateTime.parse('${today}T00:00:00Z')
          .add(Duration(days: offset))
          .toIso8601String()
          .substring(0, 10);

  int packageId(String name) => (asMap(packages[name])['id']! as num).toInt();
  int extraId(String name) => (asMap(extras[name])['id']! as num).toInt();

  Future<Map<String, Object?>> schedule(String uuid) =>
      manager.get('/pos/banquets/$uuid/payments');

  Future<List<Map<String, Object?>>> rows(String uuid) async =>
      asList((await schedule(uuid))['rows']);

  /// Takes a schedule row (or [amount] of it) at the till; the ADVANCE receipt comes at once.
  Future<Map<String, Object?>> pay(
    String event,
    Map<String, Object?> row, {
    String tender = 'cash',
    PosMoney? amount,
    String? key,
  }) async {
    final Map<String, Object?> data = await cashier.send(
      'POST',
      '/pos/banquets/$event/payments/${row['uuid']}/pay',
      <String, Object?>{
        'tender': tender,
        'amount': ?amount?.toApiString(),
        if (tender == 'card') 'card_type': 'humo',
        if (tender == 'card') 'transaction_id': 'RRN-${row['uuid']}',
        'shift_uuid': shift.uuid,
      },
      key,
    );
    final Map<String, Object?> entry = asMap(data['entry']);
    final PosMoney paid = money(entry['amount']);
    expect(paid, amount ?? money(row['amount']));
    // The ADVANCE receipt of exactly that money, in its form
    final String? receipt = asMap(entry['fiscal'])['receipt_uuid'] as String?;
    expect(receipt, isNotNull, reason: 'an ADVANCE receipt at once');
    final PosFiscalReceiptDetail r = await receipts.get(receipt!);
    expect(r.type, 'advance');
    expect(r.total, paid);
    expect(r.payments.single.form, tender == 'cash' ? 'cash' : 'card');
    return data;
  }

  Map<String, Object?> holdBody({
    required String date,
    required String slot,
    required int hall,
    required String package,
    required int guaranteed,
    required int minimum,
    required String customer,
    required String phone,
    String? title,
    List<Map<String, Object?>> withExtras = const <Map<String, Object?>>[],
  }) => <String, Object?>{
    'event_date': date,
    'slot': slot,
    'hall_ids': <int>[hall],
    'event_type': 'toy',
    'title': ?title,
    'customer_name': customer,
    'customer_phone': phone,
    'guests_guaranteed': guaranteed,
    'guests_minimum': minimum,
    'banquet_package_id': packageId(package),
    'extras': withExtras,
  };

  /// The receipt of a bill once it is paid (its sale receipt).
  Future<PosFiscalReceiptDetail> saleOf(Map<String, Object?> bill) async {
    final PosBilling billing = await till.pos.billing.bills(
      bill['order_uuid']! as String,
    );
    final PosBill paid = billing.bills.singleWhere(
      (PosBill b) => b.uuid == bill['uuid'],
    );
    final String? uuid = paid.fiscal.receiptUuid;
    expect(uuid, isNotNull, reason: 'the sale receipt of the final bill');
    return receipts.get(uuid!);
  }

  setUpAll(() async {
    final StackConfig stack = StackConfig.fromEnvironment();
    seed = stack.seed.section('toyxona');
    config = stack.withSeed(seed);
    today = seed.json['business_date']! as String;
    final Map<String, Object?> halls = asMap(seed.json['halls']);
    white = (asMap(halls['white'])['id']! as num).toInt();
    blue = (asMap(halls['blue'])['id']! as num).toInt();
    packages = asMap(seed.json['packages']);
    extras = asMap(seed.json['extras']);

    till = await Terminal.open(config, 'till1');
    tablet = await Terminal.open(config, 'till2');
    kds = await Terminal.open(config, 'kds');
    await till.signIn('cashier');
    await tablet.signIn('manager1');
    await kds.signIn('cook');
    cashier = Api(till);
    manager = Api(tablet);
    receipts = FiscalReceiptRepository(till.pos.http);
    menu = Menu(
      await till.pos.catalog.load(
        PosChannel.hall,
        menuVersion: till.bootstrap.menuVersion,
      ),
    );
    panel = Panel(
      config.apiUrl,
      asMap(asMap(seed.json['staff'])['manager2'])['token']! as String,
    );
    owner = Panel(config.apiUrl, seed.ownerToken);
    shift = await till.pos.shifts.open(
      openingCash: openingCash,
      note: "To'y kuni",
    );
    till.pos.session.updateShift(shift.toSummary());
  });

  tearDownAll(() async {
    panel.close();
    owner.close();
    await till.close();
    await tablet.close();
    await kds.close();
  });

  test('two managers hold the same hall and slot at once: exactly one wins, the other hears slot_taken', () async {
    final Map<String, Object?> body =
        holdBody(
          date: today,
          slot: 'evening',
          hall: white,
          package: "To'y paketi",
          guaranteed: 180,
          minimum: 150,
          customer: 'Karimovlar oilasi',
          phone: '+998901234567',
          title: "Aziz va Malika to'yi",
          withExtras: <Map<String, Object?>>[
            <String, Object?>{'extra_id': extraId('Tort'), 'quantity': '1'},
            <String, Object?>{'extra_id': extraId('Dekor'), 'quantity': '1'},
            <String, Object?>{
              'extra_id': extraId('Mineral suv'),
              'quantity': '1',
            },
          ],
        )..addAll(<String, Object?>{
          'quote_currency': 'USD',
          'quote_rate': '12800',
        });

    final List<Object> results = await Future.wait(<Future<Object>>[
      manager
          .send('POST', '/pos/banquets', body)
          .then<Object>((Map<String, Object?> e) => e)
          .catchError((Object e) => e),
      panel
          .call(
            'POST',
            '/pos/banquets',
            data: <String, Object?>{
              ...body,
              'customer_name': 'Rahimovlar oilasi',
              'customer_phone': '+998907654321',
            },
          )
          .then<Object>((Response<Object?> r) => r),
    ]);
    final Object fromTill = results[0];
    final Response<Object?> web = results[1] as Response<Object?>;
    final bool tabletWon = fromTill is Map<String, Object?>;
    final bool webWon = web.statusCode == 201;
    expect(
      tabletWon != webWon,
      isTrue,
      reason:
          'exactly one hold wins (till: $fromTill, panel: ${web.statusCode})',
    );
    if (tabletWon) {
      expect(web.statusCode, 409);
      expect(asMap(asMap(web.data)['data'])['reason'], 'slot_taken');
      wedding = fromTill['uuid']! as String;
    } else {
      expect((fromTill as PosException).reason, 'slot_taken');
      wedding = asMap(asMap(web.data)['data'])['uuid']! as String;
    }

    final Map<String, Object?> event = await manager.get(
      '/pos/banquets/$wedding',
    );
    expect(event['status'], 'hold');
    expect(event['hold_expires_at'], isNotNull);
    expect(event['guests_billed'], 180);
    // 180 000 × 180 + the white hall's evening 6 000 000 + cake 1 500 000 + decor 2 000 000 + water 6 000 × 180
    expect(money(event['package_total']), som(32400000));
    expect(money(event['hall_total']), som(6000000));
    expect(money(event['extras_total']), som(4580000));
    expect(money(event['total']), som(42980000));
    // "Dollarda aytilsa ham hisob so'mda": the quote is for display only
    expect(asMap(event['quote'])['currency'], 'USD');
    // The schedule from the store's template: 30 % deposit, 40 % 14 days before (today: the event is today), the rest
    final List<Map<String, Object?>> plan = await rows(wedding);
    expect(
      plan.map(
        (Map<String, Object?> r) =>
            '${r['kind']} ${r['amount']} ${r['due_date']}',
      ),
      <String>[
        'deposit 12894000.00 $today',
        'interim 17192000.00 $today',
        'final 12894000.00 $today',
      ],
    );
    // The calendar shows it under the white hall, not overdue (the deposit is due today)
    final Map<String, Object?> calendar = await manager.get(
      '/pos/banquets/calendar',
      <String, Object?>{'from': today, 'to': today},
    );
    final Map<String, Object?> whiteHall = asList(calendar['halls']).firstWhere(
      (Map<String, Object?> h) => (h['id']! as num).toInt() == white,
    );
    final Map<String, Object?> chip = asList(whiteHall['events'])
        .singleWhere((Map<String, Object?> e) => e['uuid'] == wedding);
    expect(chip['status'], 'hold');
    expect(chip['overdue'], isFalse);
  });

  test('the contract day: frozen prices and the contract number', () async {
    final Map<String, Object?> before = await manager.get(
      '/pos/banquets/$wedding',
    );
    final Map<String, Object?> frozen = await manager.send(
      'POST',
      '/pos/banquets/$wedding/freeze',
      <String, Object?>{'version': before['version']},
    );
    expect(frozen['frozen'], isTrue);
    expect(
      asList(frozen['lines'])
          .every((Map<String, Object?> l) => l['frozen'] == true),
      isTrue,
    );
    final Map<String, Object?> numbered = await manager.send(
      'POST',
      '/pos/banquets/$wedding/contract/number',
    );
    expect(numbered['contract_no'], '${today.substring(0, 4)}-0001');
    final String text = numbered['text']! as String;
    expect(text, startsWith("RIZQIM TO'YXONA"));
    expect(text, contains('Karimovlar oilasi'));
    expect(text, contains('${today.substring(0, 4)}-0001'));
    // Numbered once: asking again keeps the number
    expect(
      (await manager.send(
        'POST',
        '/pos/banquets/$wedding/contract/number',
      ))['contract_no'],
      '${today.substring(0, 4)}-0001',
    );
  });

  test('the deposit in cash at the till: an ADVANCE receipt, the event confirmed; the same key pays once', () async {
    final Map<String, Object?> deposit = (await rows(wedding)).first;
    final String key = newIdempotencyKey();
    final Map<String, Object?> paid = await pay(wedding, deposit, key: key);
    ledger.advance('cash', som(12894000));
    expect(asMap(asMap(paid['schedule'])['event'])['status'], 'confirmed');
    // The till retries with the same key (a lost answer): the same payment, nothing more in the drawer
    final Map<String, Object?> again = await cashier.send(
      'POST',
      '/pos/banquets/$wedding/payments/${deposit['uuid']}/pay',
      <String, Object?>{'tender': 'cash', 'shift_uuid': shift.uuid},
      key,
    );
    expect(asMap(again['entry'])['uuid'], asMap(paid['entry'])['uuid']);
    final Map<String, Object?> s = await schedule(wedding);
    expect(
      asList(s['entries'])
          .where((Map<String, Object?> e) => e['type'] == 'payment'),
      hasLength(1),
    );
    expect(money(asMap(s['summary'])['paid']), som(12894000));
    expect(
      (await manager.get('/pos/banquets/$wedding'))['hold_expires_at'],
      isNull,
    );
  });

  test('a guest change re-plans the unpaid rows only', () async {
    final Map<String, Object?> before = await manager.get(
      '/pos/banquets/$wedding',
    );
    final Map<String, Object?> changed = await manager.send(
      'PATCH',
      '/pos/banquets/$wedding',
      <String, Object?>{'guests_guaranteed': 200, 'version': before['version']},
    );
    // 180 000 × 200 + 6 000 000 + 3 500 000 + 6 000 × 200
    expect(money(changed['total']), som(46700000));
    final List<Map<String, Object?>> plan = await rows(wedding);
    expect(
      plan.map(
        (Map<String, Object?> r) =>
            '${r['kind']} ${r['status']} ${r['amount']}',
      ),
      <String>[
        'deposit paid 12894000.00',
        'interim due 17192000.00',
        // the difference (3 720 000) goes onto the last unpaid row
        'final due 16614000.00',
      ],
    );
    // No guest warning at the 200 threshold itself
    expect(
      asList(
        (await manager.get('/pos/banquets/$wedding/warnings'))['warnings'],
      ).where((Map<String, Object?> w) => w['code'] == 'guests_over_threshold'),
      isEmpty,
    );
  });

  test('the second event (3 days ahead) and the payment reminders: the command run twice sends once', () async {
    final Map<String, Object?> c = await manager.send(
      'POST',
      '/pos/banquets',
      holdBody(
        date: day(3),
        slot: 'evening',
        hall: blue,
        package: 'Oddiy paket',
        guaranteed: 100,
        minimum: 80,
        customer: 'Yusupovlar',
        phone: '+998935550011',
        title: 'Sardor va Nigora',
      ),
    );
    second = c['uuid']! as String;
    // 120 000 × 100 + the blue hall's evening 4 000 000
    expect(money(c['total']), som(16000000));
    final List<Map<String, Object?>> plan = await rows(second);
    expect(
      plan.map(
        (Map<String, Object?> r) =>
            '${r['kind']} ${r['amount']} ${r['due_date']}',
      ),
      <String>[
        'deposit 4800000.00 $today',
        'interim 6400000.00 $today',
        'final 4800000.00 ${day(3)}',
      ],
    );
    await pay(second, plan.first);
    ledger.advance('cash', som(4800000));

    // The final row is due in 3 days (a reminder day): one reminder, once
    final String first = await artisan(<String>[
      'pos:banquet-payment-reminders',
    ]);
    expect(first, contains('1 banquet payment reminder(s) sent'));
    final String again = await artisan(<String>[
      'pos:banquet-payment-reminders',
    ]);
    expect(again, contains('0 banquet payment reminder(s) sent'));
  });

  test('the interim in two parts: a card payment at the till and a bank transfer the owner records', () async {
    final Map<String, Object?> interim = (await rows(wedding))[1];
    await pay(wedding, interim, tender: 'card', amount: som(10000000));
    ledger.advance('card', som(10000000));
    final List<Map<String, Object?>> split = await rows(wedding);
    expect(
      split.map(
        (Map<String, Object?> r) =>
            '${r['kind']} ${r['status']} ${r['amount']}',
      ),
      <String>[
        'deposit paid 12894000.00',
        'interim paid 10000000.00',
        'interim due 7192000.00',
        'final due 16614000.00',
      ],
    );
    final Map<String, Object?> rest = split[2];
    final Map<String, Object?> transfer = await owner.ok(
      'POST',
      '/pos/banquets/$wedding/payments/${rest['uuid']}/bank-transfer',
      data: <String, Object?>{'reference': 'PP-0457', 'paid_on': today},
    );
    expect(asMap(transfer['entry'])['type'], 'transfer');
    // No cash receipt for a transfer (the invoice is outside the system)
    expect(asMap(asMap(transfer['entry'])['fiscal'])['receipt_uuid'], isNull);
    final Map<String, Object?> s = await schedule(wedding);
    expect(money(asMap(s['summary'])['paid']), som(30086000));
    expect(money(asMap(s['summary'])['outstanding']), som(16614000));
    expect(
      asList(s['entries'])
          .map((Map<String, Object?> e) => '${e['type']} ${e['tender']}'),
      <String>['payment cash', 'payment card', 'transfer bank_transfer'],
    );
  });

  test(
    'a room inside the booked hall cannot be sold for the event time',
    () async {
      final Map<String, Object?> event = await manager.get(
        '/pos/banquets/$wedding',
      );
      final DateTime starts = DateTime.parse(event['starts_at']! as String);
      final Map<String, Object?> vip = asList(seed.json['rooms']).firstWhere(
        (Map<String, Object?> r) => (r['hall_id']! as num).toInt() == white,
      );
      final PosException e = await manager.refused(
        () => tablet.pos.bookings.create(
          PosBookingInput(
            diningTableId: (vip['id']! as num).toInt(),
            startsAt: starts.add(const Duration(hours: 1)),
            endsAt: starts.add(const Duration(hours: 3)),
            guestsPlanned: 8,
            contactName: 'Olimov',
            contactPhone: '+998901112244',
          ),
        ),
      );
      expect(e.reason, 'banquet_conflict');
      expect(e.statusCode, 409);
    },
  );

  test('the production sheet and the osh calculator for 200 guests', () async {
    final Map<String, Object?> sheet = await manager.get(
      '/pos/banquets/$wedding/production-sheet',
    );
    final Map<String, Object?> guests = asMap(sheet['guests']);
    expect(guests['base'], 200);
    // A 5 % reserve: 210 guests cooked for
    expect(guests['planned'], 210);
    final Map<String, Map<String, Object?>> dishes =
        <String, Map<String, Object?>>{
          for (final Map<String, Object?> station in asList(sheet['stations']))
            for (final Map<String, Object?> dish in asList(station['dishes']))
              dish['name']! as String: <String, Object?>{
                ...dish,
                'station': station['name'],
              },
        };
    expect(number(dishes["To'y oshi"]!['quantity']), 210); // 1 portion a guest
    expect(number(dishes["To'y oshi"]!['kg']), 73.5); // 350 g a portion
    expect(dishes["To'y oshi"]!['station'], 'Qozon');
    expect(number(dishes['Qazi']!['quantity']), 21); // 0.1 kg a guest
    expect(dishes['Qazi']!['station'], 'Issiq sex');
    expect(number(dishes['Achichuk']!['quantity']), 31.5);
    expect(number(dishes['Non']!['quantity']), 210);
    expect(number(dishes['Choy']!['quantity']), 52.5);
    expect(dishes['Choy']!['station'], 'Bar');
    // The osh of the package: 210 portions, 6 guests a kg → ⌈420 / 6⌉ / 2 = 35 kg
    final Map<String, Object?> osh = asMap(sheet['osh']);
    expect(osh['persons'], 210);
    expect(number(osh['osh_kg']), 35);

    // The calculator for 200 guests: ⌈400 / 6⌉ / 2 = 33.5 kg; rice and carrot 1, meat 0.8, oil 0.2 a kg; one 50 kg kazan
    final Map<String, Object?> calc = await manager.get(
      '/pos/banquets/osh-calculator',
      <String, Object?>{'persons': 200},
    );
    expect(number(calc['osh_kg']), 33.5);
    expect(
      <String, double>{
        for (final Map<String, Object?> i in asList(calc['ingredients']))
          i['key']! as String: number(i['quantity']),
      },
      <String, double>{'rice': 33.5, 'meat': 26.8, 'carrot': 33.5, 'oil': 6.7},
    );
    expect(
      asList(calc['kazans'])
          .map((Map<String, Object?> k) => '${k['size_kg']}x${k['count']}'),
      <String>['50x1'],
    );
  });

  test('cancelled 20 days ahead: 30 % kept as a sale of the cancellation fee, the rest refunded with its receipts', () async {
    final Map<String, Object?> b = await manager.send(
      'POST',
      '/pos/banquets',
      holdBody(
        date: day(20),
        slot: 'evening',
        hall: white,
        package: 'Oddiy paket',
        guaranteed: 120,
        minimum: 80,
        customer: 'Tursunovlar',
        phone: '+998977770022',
      ),
    );
    cancelled = b['uuid']! as String;
    expect(money(b['total']), som(20400000)); // 120 000 × 120 + 6 000 000
    final List<Map<String, Object?>> plan = await rows(cancelled);
    expect(
      plan.map(
        (Map<String, Object?> r) =>
            '${r['kind']} ${r['amount']} ${r['due_date']}',
      ),
      <String>[
        'deposit 6120000.00 $today',
        'interim 8160000.00 ${day(6)}',
        'final 6120000.00 ${day(20)}',
      ],
    );
    await pay(cancelled, plan[0]);
    ledger.advance('cash', som(6120000));
    await pay(cancelled, plan[1], tender: 'card', amount: som(2000000));
    ledger.advance('card', som(2000000));

    final Map<String, Object?> quote = await manager.get(
      '/pos/banquets/$cancelled/cancellation',
    );
    expect(quote['days_before'], 20);
    expect(quote['rule_percent'], '30');
    expect(money(quote['paid']), som(8120000));
    expect(money(quote['retained']), som(2436000));
    expect(money(quote['refund']), som(5684000));
    // Kept from the oldest money first (the cash deposit); the rest of it from the drawer, the card part by card
    expect(money(quote['cash_refund']), som(3684000));

    // The manager cancels at the cashier's till (the drawer the cash comes from)
    await till.signIn('manager1');
    final Map<String, Object?> after = await cashier.send(
      'POST',
      '/pos/banquets/$cancelled/cancellation',
      <String, Object?>{'reason': "Kuyov tomoni sanani o'zgartirdi"},
    );
    await till.signIn('cashier');
    ledger.refund('cash', som(3684000));
    ledger.refund('card', som(2000000));
    expect(asMap(after['event'])['status'], 'cancelled');
    final List<Map<String, Object?>> entries = asList(after['entries']);
    final List<Map<String, Object?>> refunds = entries
        .where((Map<String, Object?> e) => e['type'] == 'refund')
        .toList();
    expect(
      refunds
          .map((Map<String, Object?> e) => '${e['tender']} ${e['amount']}')
          .toSet(),
      <String>{'cash 3684000.00', 'card 2000000.00'},
    );
    for (final Map<String, Object?> e in refunds) {
      final PosFiscalReceiptDetail r = await receipts.get(
        asMap(e['fiscal'])['receipt_uuid']! as String,
      );
      expect(r.type, 'refund');
      expect(r.total, money(e['amount']));
      expect(r.originalReceiptUuid, e['original_receipt_uuid']);
    }
    final Map<String, Object?> retain = entries.singleWhere(
      (Map<String, Object?> e) => e['type'] == 'retain',
    );
    expect(money(retain['amount']), som(2436000));
    final PosFiscalReceiptDetail kept = await receipts.get(
      asMap(retain['fiscal'])['receipt_uuid']! as String,
    );
    expect(kept.type, 'sale');
    expect(kept.total, som(2436000));
    expect(kept.lines.single.name, 'Bekor qilish haqi');
    expect(
      kept.payments.every((PosFiscalPaymentPart p) => p.prepayment),
      isTrue,
    );
    // The hall is free again for that evening
    expect(
      asList((await schedule(cancelled))['rows'])
          .where((Map<String, Object?> r) => r['status'] == 'due'),
      isEmpty,
    );
  });

  test('an over-paid event: the bill is covered by the advances and the excess goes back against its receipt', () async {
    final Map<String, Object?> d = await manager.send(
      'POST',
      '/pos/banquets',
      holdBody(
        date: today,
        slot: 'day',
        hall: blue,
        package: 'Oddiy paket',
        guaranteed: 100,
        minimum: 80,
        customer: 'Qodirovlar',
        phone: '+998946660033',
      ),
    );
    overpaid = d['uuid']! as String;
    expect(money(d['total']), som(14500000)); // 120 000 × 100 + 2 500 000
    for (final Map<String, Object?> row in await rows(overpaid)) {
      await pay(overpaid, row);
      ledger.advance('cash', money(row['amount']));
    }
    // Fewer guests after everything was paid: 85 × 120 000 + 2 500 000 = 12 700 000, 1 800 000 over
    final Map<String, Object?> now = await manager.get(
      '/pos/banquets/$overpaid',
    );
    expect(
      money(
        (await manager.send(
          'PATCH',
          '/pos/banquets/$overpaid',
          <String, Object?>{'guests_guaranteed': 85, 'version': now['version']},
        ))['total'],
      ),
      som(12700000),
    );
    await cashier.send('POST', '/pos/banquets/$overpaid/start');
    final Map<String, Object?> settled = await cashier.send(
      'POST',
      '/pos/banquets/$overpaid/settle',
      <String, Object?>{'shift_uuid': shift.uuid},
    );
    final Map<String, Object?> bill = asMap(settled['bill']);
    expect(money(bill['total']), som(12700000));
    expect(bill['status'], 'paid');
    expect(money(asMap(settled['settlement'])['excess']), som(1800000));
    if (asMap(asMap(settled['settlement'])['settlement'])['excess_status'] ==
        'pending') {
      // The cashier may not refund: the manager types the PIN
      final ApprovalToken approval = await till.pos.approvals.request(
        permission: PosPermissions.refund,
        userId: seed.staff('manager1').id,
        pin: seed.staff('manager1').pin!,
      );
      final Map<String, Object?> refunded = await cashier.send(
        'POST',
        '/pos/banquets/$overpaid/settlement/refund-excess',
        <String, Object?>{'approval_token': approval.token},
      );
      expect(asMap(refunded['settlement'])['excess_status'], 'refunded');
    }
    ledger.refund('cash', som(1800000));
    final Map<String, Object?> s = await schedule(overpaid);
    expect(asMap(s['event'])['status'], 'settled');
    final Map<String, Object?> back = asList(s['entries'])
        .singleWhere((Map<String, Object?> e) => e['type'] == 'refund');
    expect(money(back['amount']), som(1800000));
    final PosFiscalReceiptDetail r = await receipts.get(
      asMap(back['fiscal'])['receipt_uuid']! as String,
    );
    expect(r.type, 'refund');
    expect(r.total, som(1800000));
    // The one sale receipt: the whole total, all of it prepaid
    final PosFiscalReceiptDetail sale = await saleOf(bill);
    expect(sale.type, 'sale');
    expect(sale.total, som(12700000));
    expect(
      sale.payments.every((PosFiscalPaymentPart p) => p.prepayment),
      isTrue,
    );
    expect(
      PosMoney.sum(sale.payments.map((PosFiscalPaymentPart p) => p.amount)),
      som(12700000),
    );
  });

  test('the wedding day: the tab, drinks and skewers to the kitchen, 185 guests billed at 200, ONE sale receipt', () async {
    final Map<String, Object?> started = await cashier.send(
      'POST',
      '/pos/banquets/$wedding/start',
    );
    expect(asMap(started['event'])['status'], 'in_progress');
    final String orderUuid = asMap(started['event'])['order_uuid']! as String;
    // The waiters of the evening
    final Map<String, Object?> staff = await manager.send(
      'PUT',
      '/pos/banquets/$wedding/staff',
      <String, Object?>{
        'user_ids': <int>[seed.staff('waiter1').id, seed.staff('waiter2').id],
      },
    );
    expect(asList(staff['staff']), hasLength(2));

    // The extras ordered during the evening are ordinary lines of the tab, sent to the kitchen
    final PosOrder tab = await till.pos.orders.get(orderUuid);
    final List<Dish> extrasOrdered = <Dish>[
      Dish.of('Coca-Cola 1L', 10),
      Dish.of('Shashlik', 20),
    ];
    await till.pos.orders.addRound(
      orderUuid,
      version: tab.version,
      items: menu.inputs(extrasOrdered),
      send: true,
    );
    final KitchenTicket ticket = await eventually<KitchenTicket>(() async {
      for (final KitchenTicket t
          in (await kds.client.kitchen.tickets()).tickets) {
        if (t.uuid == orderUuid) return t;
      }
      return null;
    }, what: "the event's kitchen ticket");
    expect(
      ticket.lines
          .map((KitchenLine l) => '${l.productName} ${l.quantity.toInt()}')
          .toSet(),
      <String>{'Coca-Cola 1L 10', 'Shashlik 20'},
    );
    for (final KitchenLine line in ticket.lines) {
      await kds.client.kitchen.bumpLine(orderUuid, line.id);
    }

    // 185 came: the bill is for the guaranteed 200
    final Map<String, Object?> event = await manager.get(
      '/pos/banquets/$wedding',
    );
    final Map<String, Object?> counted = await manager.send(
      'PATCH',
      '/pos/banquets/$wedding/guests',
      <String, Object?>{'guests_actual': 185, 'version': event['version']},
    );
    expect(asMap(asMap(counted['settlement'])['guests'])['billed'], 200);
    final Map<String, Object?> preview = await cashier.get(
      '/pos/banquets/$wedding/settlement',
    );
    expect(money(preview['package_total']), som(36000000));
    expect(money(preview['hall_total']), som(6000000));
    expect(
      money(preview['extras_total']),
      som(4700000),
    ); // 1 500 000 + 2 000 000 + 6 000 × 200
    final PosMoney orders = menu.total(
      extrasOrdered,
    ); // 10 × 15 000 + 20 × 25 000
    expect(money(asMap(preview['orders'])['total']), orders);
    final PosMoney total = som(46700000) + orders;
    expect(money(preview['total']), total);
    expect(money(asMap(preview['advances'])['total']), som(30086000));
    final PosMoney due = total - som(30086000);
    expect(money(preview['due']), due);

    final Map<String, Object?> settled = await cashier.send(
      'POST',
      '/pos/banquets/$wedding/settle',
      <String, Object?>{'shift_uuid': shift.uuid},
    );
    final Map<String, Object?> bill = asMap(settled['bill']);
    expect(money(bill['total']), total);
    expect(money(bill['paid_amount']), som(30086000));
    expect(money(bill['due_amount']), due);
    // The rest at the till: 10 000 000 by card, the rest in cash
    final PosMoney card = som(10000000);
    final PosBillResult paid = await till.pos.billing.pay(
      bill['uuid']! as String,
      tenders: <PosTenderInput>[
        PosTenderInput.card(
          card,
          cardType: 'uzcard',
          transactionId: 'RRN-TOY-1',
        ),
        PosTenderInput.cash(due - card),
      ],
      shiftUuid: shift.uuid,
    );
    expect(paid.bill.status, 'paid');
    ledger.cashSales += due - card;
    ledger.cardSales += card;
    expect(asMap((await schedule(wedding))['event'])['status'], 'settled');

    // ONE sale receipt at the full total; every advance (the transfer too) is its prepayment part
    final PosFiscalReceiptDetail sale = await saleOf(bill);
    expect(sale.type, 'sale');
    expect(sale.total, total, reason: 'chek summasi = schyot summasi');
    expect(sale.total, paid.bill.total);
    final List<PosFiscalPaymentPart> prepaid = sale.payments
        .where((PosFiscalPaymentPart p) => p.prepayment)
        .toList();
    expect(
      PosMoney.sum(prepaid.map((PosFiscalPaymentPart p) => p.amount)),
      som(30086000),
    );
    expect(
      PosMoney.sum(sale.payments.map((PosFiscalPaymentPart p) => p.amount)),
      total,
    );
    expect(
      sale.lines.map((PosFiscalLine l) => l.name),
      containsAll(<String>[
        "Banket paketi: To'y paketi",
        'Zal ijarasi: Oq zal',
        'Tort',
        'Dekor',
        'Mineral suv',
      ]),
    );
  });

  test('the second event: more guests than guaranteed are billed', () async {
    await cashier.send('POST', '/pos/banquets/$second/start');
    final Map<String, Object?> event = await manager.get(
      '/pos/banquets/$second',
    );
    await manager.send(
      'PATCH',
      '/pos/banquets/$second/guests',
      <String, Object?>{'guests_actual': 120, 'version': event['version']},
    );
    final Map<String, Object?> preview = await cashier.get(
      '/pos/banquets/$second/settlement',
    );
    expect(asMap(preview['guests'])['billed'], 120);
    expect(money(preview['total']), som(18400000)); // 120 000 × 120 + 4 000 000
    final Map<String, Object?> settled = await cashier.send(
      'POST',
      '/pos/banquets/$second/settle',
      <String, Object?>{'shift_uuid': shift.uuid},
    );
    final Map<String, Object?> bill = asMap(settled['bill']);
    expect(money(bill['due_amount']), som(13600000));
    final PosBillResult paid = await till.pos.billing.pay(
      bill['uuid']! as String,
      tenders: <PosTenderInput>[PosTenderInput.cash(som(13600000))],
      shiftUuid: shift.uuid,
    );
    expect(paid.bill.status, 'paid');
    ledger.cashSales += som(13600000);
    final PosFiscalReceiptDetail sale = await saleOf(bill);
    expect(sale.total, som(18400000));
    expect(
      PosMoney.sum(
        sale.payments
            .where((PosFiscalPaymentPart p) => p.prepayment)
            .map((PosFiscalPaymentPart p) => p.amount),
      ),
      som(4800000),
    );
  });

  test('X and Z on the till: banquet advances and refunds, cash expected, difference 0', () async {
    final PosShiftWithReport x = await till.pos.shifts.xReport(shift.uuid);
    final Map<String, Object?> report = x.report!.raw;
    final Map<String, Object?> banquets = asMap(report['banquets']);
    final Map<String, Object?> taken = asMap(banquets['taken']);
    expect(taken['count'], ledger.advanceCount);
    expect(money(taken['amount']), ledger.cashAdvances + ledger.cardAdvances);
    expect(money(taken['cash']), ledger.cashAdvances);
    expect(money(taken['card']), ledger.cardAdvances);
    final Map<String, Object?> refunded = asMap(banquets['refunded']);
    expect(refunded['count'], ledger.refundCount);
    expect(money(refunded['amount']), ledger.cashRefunds + ledger.cardRefunds);
    expect(money(refunded['cash']), ledger.cashRefunds);
    // The drawer: the opening, the cash advances, the cash rest of the final bills, less the cash given back
    final PosMoney expected =
        openingCash +
        ledger.cashAdvances +
        ledger.cashSales -
        ledger.cashRefunds;
    expect(x.report!.expectedCash, expected);
    final PosShift z = await till.pos.shifts.close(
      shift.uuid,
      countedBreakdown: banknotes(expected),
    );
    expect(z.status, 'closed');
    expect(z.difference, PosMoney.zero);
    till.pos.session.updateShift(null);
  });

  test('the owner\'s "Tadbirlar" report and its Excel file', () async {
    final Map<String, Object?> report = await owner.ok(
      'GET',
      '/owner/pos/banquets/report',
      query: <String, Object?>{'from': today, 'to': day(20)},
    );
    final Map<String, Map<String, Object?>> rows =
        <String, Map<String, Object?>>{
          for (final Map<String, Object?> r in asList(report['rows']))
            r['uuid']! as String: r,
        };
    final PosMoney weddingTotal = money(rows[wedding]!['total']);
    // A settled event: everything paid (the advances and the rest on the final bill), nothing outstanding
    expect(rows[wedding]!['status'], 'settled');
    expect(money(rows[wedding]!['paid']), weddingTotal);
    expect(money(rows[wedding]!['outstanding']), PosMoney.zero);
    expect(rows[wedding]!['guests'], 200);
    expect(money(rows[second]!['paid']), som(18400000));
    expect(rows[second]!['guests'], 120);
    // The over-paid one: 14 500 000 received, 1 800 000 given back
    expect(money(rows[overpaid]!['paid']), som(14500000));
    expect(money(rows[overpaid]!['refunded']), som(1800000));
    expect(rows[cancelled]!['status'], 'cancelled');
    expect(money(rows[cancelled]!['retained']), som(2436000));
    expect(money(rows[cancelled]!['refunded']), som(5684000));

    expect(report['events'], 3);
    expect(report['cancelled'], 1);
    expect(report['guests'], 200 + 120 + 85);
    expect(
      money(report['total']),
      weddingTotal + som(18400000) + som(12700000),
    );
    expect(money(report['outstanding']), PosMoney.zero);
    expect(money(report['retained']), som(2436000));
    expect(money(report['refunded']), som(5684000) + som(1800000));
    expect(
      money(report['paid']),
      weddingTotal + som(18400000) + som(14500000) + som(8120000),
    );

    final Response<Object?> x = await owner.dio.get<Object?>(
      '/v1/owner/pos/banquets/export',
      queryParameters: <String, Object?>{'from': today, 'to': day(20)},
      options: Options(responseType: ResponseType.bytes),
    );
    expect(x.statusCode, 200);
    expect(x.headers.value('content-type'), contains('spreadsheetml'));
    final XlsxFile file = XlsxFile.parse(x.data! as List<int>);
    expect(file.sheetNames, <String>['Tadbirlar', 'Jami']);
    expect(file.allText, contains('Karimovlar oilasi'));
    expect(file.allText, contains('Tursunovlar'));
  });

  test("the waiters' pay: the settled wedding counts once for each waiter assigned", () async {
    final Map<String, Object?> statement = await owner.ok(
      'POST',
      '/owner/staff-pay/statements',
      data: <String, Object?>{'branch_id': seed.branchId, 'date': today},
    );
    final Map<int, Map<String, Object?>> lines = <int, Map<String, Object?>>{
      for (final Map<String, Object?> l in asList(statement['lines']))
        (l['user_id']! as num).toInt(): l,
    };
    for (final String key in <String>['waiter1', 'waiter2']) {
      final Map<String, Object?> line = lines[seed.staff(key).id]!;
      final Map<String, PosMoney> parts = <String, PosMoney>{
        for (final Map<String, Object?> part in asList(line['components']))
          part['type']! as String: money(part['amount']),
      };
      expect(parts, <String, PosMoney>{'event_rate': som(250000)}, reason: key);
      expect(money(line['gross']), som(250000), reason: key);
    }
  });
}
