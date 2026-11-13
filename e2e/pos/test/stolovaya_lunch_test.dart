/// P6-49 "Stolovaya tushligi" against the real stack: a stolovaya (profile `stolovaya`, its own store in the seed,
/// `stolovaya`) at lunch, with corporate talons, dynamic QR payments, gift certificates and a promo code. Two tills sell
/// about fifty trays over the counter (today's daily menu and the kompleks): the staff of "Paxta Tekstil" eat on the
/// company's account by their talon code or phone (no manager PIN: the account does not ask for one), within their
/// 40 000 so'm a day; one tries a second lunch over the limit and pays cash, a member who has left is refused, the
/// abonement member's second meal is refused. Click, Payme and UzQR QRs are confirmed by the providers: the Click and
/// Payme webhooks of the stack are called with correctly signed sample payloads for `pos_qr_<uuid>` (the till never
/// confirms by hand), UzQR through the sandbox gateway's simulate endpoint; a duplicate callback is a no-op, a wrong
/// amount, a wrong key, an expired QR and a QR of a bill that changed are refused. One bill is paid with a certificate
/// and a QR; a certificate is sold (an advance receipt) and spent on two bills, a third use is refused, a blocked one
/// is refused, two tills spend one certificate at the same time and one wins. One QR payment is refunded through the
/// UzQR sandbox, one through Click's merchant API (the test's fake: refused first, the refund is not made; then
/// reversed), a Payme partial refund is refused before any money moves, and one certificate payment goes back onto the
/// certificate. Every sale receipt is the bill's total ("chek summasi = schyot summasi"), there is exactly one per bill
/// and none from Click or Payme (no double fiscal). Z on both tills (by tender with the QR providers and the
/// certificates, cash expected), and the corporate monthly statement (member × day) and its Excel file match the
/// charges. At the end: webhook → "paid" on the till latencies.
@Timeout(Duration(minutes: 10))
library;

import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:test/test.dart';

import 'support/digest.dart';
import 'support/menu.dart';
import 'support/stack.dart';
import 'support/xlsx.dart';

const PosMoney openingCash = PosMoney.som(300000);
const String promoCode = 'STOLOVAYA10';

Map<String, Object?> asMap(Object? value) =>
    (value! as Map<Object?, Object?>).cast<String, Object?>();

List<Map<String, Object?>> asList(Object? value) => <Map<String, Object?>>[
  for (final Object? e in (value as List<Object?>?) ?? const <Object?>[])
    asMap(e),
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

/// CRC-16/CCITT-FALSE, the checksum of an EMV QR payload (tag 63).
String crc16(String data) {
  var crc = 0xffff;
  for (final int byte in utf8.encode(data)) {
    crc ^= byte << 8;
    for (var bit = 0; bit < 8; bit++) {
      crc = (crc & 0x8000) != 0
          ? ((crc << 1) ^ 0x1021) & 0xffff
          : (crc << 1) & 0xffff;
    }
  }
  return crc.toRadixString(16).toUpperCase().padLeft(4, '0');
}

/// A person of a corporate account as the seed lists them.
class Member {
  Member(Map<String, Object?> j)
    : uuid = j['uuid']! as String,
      name = j['name']! as String,
      code = j['code']! as String,
      phone = j['phone']! as String,
      active = j['active']! as bool;

  final String uuid;
  final String name;
  final String code;
  final String phone;
  final bool active;
}

/// One tray at the till: what is on it, and how it was paid.
class Tray {
  Tray(this.label, this.dishes);

  final String label;
  final List<Dish> dishes;

  late Till till;
  late PosOrder order;
  late PosBill bill;

  /// What the test paid it with, by tender (the Z expectation).
  final Map<String, PosMoney> tenders = <String, PosMoney>{};

  /// The QR provider of its `qr` part, if any.
  String? qrProvider;
  PosQrPayment? qr;

  /// The corporate member charged (talon code).
  String? memberCode;
  PosFiscalReceiptDetail? receipt;

  void paidWith(String tender, PosMoney amount) =>
      tenders[tender] = (tenders[tender] ?? PosMoney.zero) + amount;

  @override
  String toString() => label;
}

/// A till of the stolovaya: the terminal, its menu, its shift and what it sold.
class Till {
  Till(this.terminal);

  final Terminal terminal;
  late Menu menu;
  late PosShift shift;
  late FiscalReceiptRepository receipts;
  late EventLog log;
  final List<Tray> trays = <Tray>[];

  /// Gift certificates sold here (cash).
  final List<PosVoucher> vouchersSold = <PosVoucher>[];

  /// Refunds made here: (method, amount).
  final List<PosRefund> refunds = <PosRefund>[];

  PosData get pos => terminal.pos;
  String get label => terminal.label;
}

/// The test's Click merchant API (the backend's CLICK_MERCHANT_API_URL points here): answers a payment reversal
/// (DELETE /v2/merchant/payment/reversal/{service_id}/{payment_id}) after checking the `Auth` header
/// (`merchant_user_id:sha1(timestamp + secret_key):timestamp`). While [refuse] is on it answers Click's refusal.
class FakeClickMerchantApi {
  FakeClickMerchantApi(this.merchantUserId, this.secret, this.serviceId);

  final String merchantUserId;
  final String secret;
  final String serviceId;
  bool refuse = false;
  final List<String> reversed = <String>[];
  final List<String> problems = <String>[];
  HttpServer? _server;

  Future<void> start(int port) async {
    final HttpServer server = await HttpServer.bind(
      InternetAddress.loopbackIPv4,
      port,
    );
    _server = server;
    server.listen((HttpRequest request) async {
      final List<String> parts = request.uri.pathSegments;
      final String auth = request.headers.value('Auth') ?? '';
      final List<String> a = auth.split(':');
      final bool signed =
          a.length == 3 &&
          a[0] == merchantUserId &&
          a[1] == sha1Hex('${a[2]}$secret');
      Map<String, Object?> body;
      // DELETE /v2/merchant/payment/reversal/{service_id}/{payment_id}
      if (request.method != 'DELETE' ||
          parts.length != 6 ||
          parts.sublist(0, 4).join('/') != 'v2/merchant/payment/reversal') {
        problems.add('${request.method} ${request.uri}');
        request.response.statusCode = 404;
        body = <String, Object?>{'error_code': -404, 'error_note': 'Not found'};
      } else if (!signed) {
        problems.add('bad Auth header: $auth');
        request.response.statusCode = 401;
        body = <String, Object?>{
          'error_code': -16,
          'error_note': 'Authentication failed',
        };
      } else if (parts[4] != serviceId) {
        problems.add('another service: ${parts[4]}');
        body = <String, Object?>{
          'error_code': -16,
          'error_note': 'Wrong service',
        };
      } else if (refuse) {
        body = <String, Object?>{
          'error_code': -5017,
          'error_note': "To'lovni bekor qilish muddati o'tgan",
        };
      } else {
        reversed.add(parts.last);
        body = <String, Object?>{'error_code': 0, 'payment_id': parts.last};
      }
      request.response.headers.contentType = ContentType.json;
      request.response.write(jsonEncode(body));
      await request.response.close();
    });
  }

  Future<void> stop() async => _server?.close(force: true);
}

void main() {
  late StackConfig config;
  late Seed seed;
  late Map<String, Object?> section;
  late Dio owner;
  late Dio hooks;
  late Till till1;
  late Till till2;
  late Staff manager;
  late FakeClickMerchantApi clickApi;
  late List<Member> paxta;
  late List<Member> abonement;
  late String corporateUuid;
  late String subscriptionUuid;
  late String nasiyaUuid;
  late String businessDate;
  late Map<String, Object?> clickMerchant;
  late Map<String, Object?> paymeMerchant;
  late List<Map<String, Object?>> certificates;

  final List<Tray> all = <Tray>[];
  final Random random = Random(649);

  /// Webhook (or the bank's simulate call) sent → the till hears `qr.paid` / has the paid bill again.
  final Map<String, List<Duration>> heardLatency = <String, List<Duration>>{};
  final Map<String, List<Duration>> paidLatency = <String, List<Duration>>{};

  /// The corporate charges the test made: talon code → (amount, meals).
  final Map<String, PosMoney> charged = <String, PosMoney>{};
  final Map<String, int> meals = <String, int>{};

  // The tray whose QR is left to expire; the certificate sold at the till; the trays refunded later
  late Tray expiring;
  late PosQrPayment expiringQr;
  late PosVoucher sold;
  late Tray raceWinner;
  final List<Tray> uzqrTrays = <Tray>[];
  final List<Tray> clickTrays = <Tray>[];
  final List<Tray> paymeTrays = <Tray>[];

  var clickTrans = 7700490000;
  ({String trans, Object? prepareId}) lastClick = (trans: '', prepareId: null);
  var paymeTrans = 0;
  var rpcId = 0;

  // --- The till's own steps -------------------------------------------------------------------------------------

  /// The tray is rung up: an order on the hall channel (one guest, no table: the counter of a stolovaya), sent.
  Future<Tray> ring(Till t, Tray tray) async {
    tray.till = t;
    tray.order = await t.pos.orders.open(
      channel: PosChannel.hall,
      guestsCount: 1,
      items: t.menu.inputs(tray.dishes),
      send: true,
    );
    return tray;
  }

  /// "To'lash": the bill of the tray (one bill), at the dishes' total less [discount].
  Future<Tray> bill(Tray tray, {PosMoney discount = PosMoney.zero}) async {
    final Till t = tray.till;
    final PosOrder order = await t.pos.orders.get(tray.order.uuid);
    tray.order = order;
    final PosBilling billing = await t.pos.billing.createBills(
      order.uuid,
      version: order.version,
    );
    tray.bill = billing.bills.firstWhere(
      (PosBill b) => !b.isPaid && b.status != 'void',
    );
    expect(
      tray.bill.total,
      t.menu.total(tray.dishes) - discount,
      reason:
          '$tray: the bill is the dishes (no service charge in a stolovaya)',
    );
    return tray;
  }

  Future<Tray> open(Till t, Tray tray) async => bill(await ring(t, tray));

  /// The bill is paid: its payments are what the test paid, its sale receipt is the bill's total (prepayment = the
  /// certificate part, credit = the account part) and the tray counts for the till's Z.
  Future<Tray> finish(Tray tray, PosBill paid) async {
    expect(paid.isPaid, isTrue, reason: '$tray is paid');
    expect(paid.uuid, tray.bill.uuid);
    final Map<String, PosMoney> byTender = <String, PosMoney>{};
    for (final PosPayment p in paid.payments) {
      if (p.status != null && p.status != 'paid') continue;
      byTender[p.tender] = (byTender[p.tender] ?? PosMoney.zero) + p.amount;
    }
    expect(byTender, tray.tenders, reason: '$tray: the payments of the bill');
    expect(PosMoney.sum(byTender.values), paid.total);
    tray.bill = paid;
    final String? receiptUuid = paid.fiscal.receiptUuid;
    expect(receiptUuid, isNotNull, reason: '$tray has its sale receipt');
    final PosFiscalReceiptDetail r = await tray.till.receipts.get(receiptUuid!);
    expect(r.receiptType, FiscalReceiptType.sale, reason: '$tray');
    expect(r.total, paid.total, reason: '$tray: chek summasi = schyot summasi');
    expect(
      PosMoney.sum(r.payments.map((PosFiscalPaymentPart p) => p.amount)),
      paid.total,
    );
    expect(
      PosMoney.sum(r.lines.map((PosFiscalLine l) => l.amount)),
      paid.total,
    );
    expect(
      PosMoney.sum(<PosMoney>[
        for (final PosFiscalPaymentPart p in r.payments)
          if (p.prepayment) p.amount,
      ]),
      tray.tenders['voucher'] ?? PosMoney.zero,
      reason:
          '$tray: the certificate part is prepayment (its advance receipt was issued when it was sold)',
    );
    expect(
      PosMoney.sum(<PosMoney>[
        for (final PosFiscalPaymentPart p in r.payments)
          if (p.credit) p.amount,
      ]),
      tray.tenders['account'] ?? PosMoney.zero,
      reason: '$tray: the account part is credit',
    );
    tray.receipt = r;
    tray.till.trays.add(tray);
    all.add(tray);
    return tray;
  }

  Future<Tray> pay(
    Tray tray,
    List<PosTenderInput> tenders, {
    String? approvalPermission,
  }) async {
    final Till t = tray.till;
    Future<PosBillResult> send(String? token) => t.pos.billing.pay(
      tray.bill.uuid,
      tenders: tenders,
      shiftUuid: t.shift.uuid,
      approvalToken: token,
    );
    final PosBillResult result = approvalPermission == null
        ? await send(null)
        : await runWithApproval<PosBillResult>(
            send,
            approve: (String needed, PosException cause) async {
              expect(needed, approvalPermission);
              return t.pos.approvals.request(
                permission: needed,
                userId: manager.id,
                pin: manager.pin!,
              );
            },
          );
    for (final PosTenderInput i in tenders) {
      tray.paidWith(i.tender.wire, i.amount);
    }
    if (!result.bill.isPaid) {
      tray.bill = result.bill;
      return tray;
    }
    return finish(tray, result.bill);
  }

  /// Cash with the change (the note above the total).
  PosTenderInput cash(PosMoney amount) => PosTenderInput.cash(
    amount,
    tendered: PosMoney.som((amount.tiyin / 100 / 10000).ceil() * 10000),
  );

  PosTenderInput card(PosMoney amount) => PosTenderInput.card(
    amount,
    cardType: random.nextBool() ? 'uzcard' : 'humo',
    transactionId: '${700000000000 + random.nextInt(99999999)}',
    cardLast4: '${1000 + random.nextInt(9000)}',
  );

  Future<T> approved<T>(
    Till t,
    Future<T> Function(String? token) action,
    String permission,
  ) => runWithApproval<T>(
    action,
    approve: (String needed, PosException cause) async {
      expect(needed, permission, reason: 'the permission the server asks for');
      return t.pos.approvals.request(
        permission: needed,
        userId: manager.id,
        pin: manager.pin!,
      );
    },
  );

  Matcher refusedWith(String reason, [int? status]) => throwsA(
    isA<PosException>()
        .having((PosException e) => e.reason, 'reason', reason)
        .having((PosException e) => e.statusCode, 'status', status ?? anything),
  );

  // --- The providers --------------------------------------------------------------------------------------------

  String signTime() {
    final DateTime n = DateTime.now().toUtc().add(const Duration(hours: 5));
    String two(int v) => v.toString().padLeft(2, '0');
    return '${n.year}-${two(n.month)}-${two(n.day)} ${two(n.hour)}:${two(n.minute)}:${two(n.second)}';
  }

  /// Click's SHOP API call (form fields, `sign_string` md5 with the store's own secret key).
  Future<Map<String, Object?>> click(
    int action,
    PosQrPayment qr, {
    required String trans,
    String? amount,
    Object? prepareId,
    String? secret,
  }) async {
    final String sum = amount ?? qr.amount.toApiString();
    final String account = 'pos_qr_${qr.uuid}';
    final String serviceId = clickMerchant['service_id']! as String;
    final String time = signTime();
    final String key = secret ?? clickMerchant['secret_key']! as String;
    final Response<Object?> r = await hooks.post<Object?>(
      '/v1/payments/click/webhook',
      data: <String, Object?>{
        'click_trans_id': trans,
        'service_id': serviceId,
        'click_paydoc_id': '${int.parse(trans) + 1000000}',
        'merchant_trans_id': account,
        'amount': sum,
        'action': '$action',
        'error': '0',
        'error_note': 'Success',
        'sign_time': time,
        if (action == 1) 'merchant_prepare_id': '$prepareId',
        'sign_string': md5Hex(
          '$trans$serviceId$key$account${action == 1 ? '$prepareId' : ''}$sum$action$time',
        ),
      },
      options: Options(contentType: Headers.formUrlEncodedContentType),
    );
    expect(r.statusCode, 200, reason: 'Click is always answered 200');
    return asMap(r.data);
  }

  /// Payme's Merchant API call (JSON-RPC, HTTP Basic `Paycom:<the store's own key>`).
  Future<Map<String, Object?>> payme(
    String method,
    Map<String, Object?> params, {
    String? key,
  }) async {
    final Response<Object?> r = await hooks.post<Object?>(
      '/v1/payments/payme/webhook',
      data: <String, Object?>{
        'jsonrpc': '2.0',
        'id': ++rpcId,
        'method': method,
        'params': params,
      },
      options: Options(
        headers: <String, Object?>{
          'Authorization':
              'Basic ${base64.encode(utf8.encode('Paycom:${key ?? paymeMerchant['secret_key']}'))}',
        },
      ),
    );
    expect(r.statusCode, 200);
    final Map<String, Object?> body = asMap(r.data);
    expect(body['id'], rpcId);
    return body;
  }

  Map<String, Object?> paymeAccount(PosQrPayment qr) => <String, Object?>{
    'order_id': 'pos_qr_${qr.uuid}',
  };

  /// The cashier asks for a QR of the bill (all it owes, or [amount]); the till shows it and pushes it to the display.
  Future<PosQrPayment> showQr(
    Tray tray,
    PosQrProvider provider, {
    PosMoney? amount,
  }) async {
    final Till t = tray.till;
    final PosQrPayment qr = await t.pos.qr.create(
      tray.bill.uuid,
      provider: provider,
      amount: amount?.toApiString(),
      shiftUuid: t.shift.uuid,
    );
    expect(qr.status, PosQrStatus.pending);
    expect(qr.provider, provider);
    expect(qr.billUuid, tray.bill.uuid);
    expect(qr.amount, amount ?? tray.bill.dueAmount);
    expect(qr.payload, isNotEmpty);
    expect(qr.held, isFalse);
    expect(qr.simulated, provider == PosQrProvider.uzqr);
    // The customer display's QR is the same text
    expect(qr.display.payload, qr.payload);
    expect(qr.display.provider, provider.wire);
    tray.qr = qr;
    return qr;
  }

  /// The provider said "paid": the till hears `qr.paid`, takes the bill again and the paid flow goes on with no tap.
  Future<Tray> heardPaid(Tray tray, PosQrPayment qr, DateTime sent) async {
    final Till t = tray.till;
    final HeardEvent heard = await t.log.waitFor(
      (PosEvent e) => e is PosQrPaymentChanged && e.qrUuid == qr.uuid && e.paid,
      what: 'qr.paid of $tray',
    );
    final PosQrPaymentChanged event = heard.event as PosQrPaymentChanged;
    expect(event.billUuid, tray.bill.uuid);
    expect(event.provider, qr.provider!.wire);
    final PosBilling billing = await t.pos.billing.bills(tray.order.uuid);
    final DateTime at = DateTime.now();
    final String p = qr.provider!.wire;
    (heardLatency[p] ??= <Duration>[]).add(heard.at.difference(sent));
    (paidLatency[p] ??= <Duration>[]).add(at.difference(sent));
    tray.paidWith('qr', qr.amount);
    tray.qrProvider = p;
    final PosBill bill = billing.bills.firstWhere(
      (PosBill b) => b.uuid == tray.bill.uuid,
    );
    final PosPayment payment = bill.payments.firstWhere(
      (PosPayment x) => x.tender == 'qr',
    );
    expect(payment.amount, qr.amount);
    expect(
      payment.reference,
      isNotEmpty,
      reason: 'the provider\'s transaction id',
    );
    final PosQrPayment polled = await t.pos.qr.poll(qr.uuid);
    expect(polled.status, PosQrStatus.paid);
    expect(polled.paymentUuid, payment.uuid);
    if (bill.isPaid) return finish(tray, bill);
    tray.bill = bill;
    return tray;
  }

  /// Click pays it: Prepare (held), Complete (paid).
  Future<Tray> clickPays(Tray tray, PosQrPayment qr) async {
    final String trans = '${++clickTrans}';
    final Map<String, Object?> prepare = await click(0, qr, trans: trans);
    expect(prepare['error'], 0, reason: 'Prepare: $prepare');
    expect(prepare['merchant_trans_id'], 'pos_qr_${qr.uuid}');
    final Object? prepareId = prepare['merchant_prepare_id'];
    lastClick = (trans: trans, prepareId: prepareId);
    expect((await tray.till.pos.qr.poll(qr.uuid)).held, isTrue);
    final DateTime sent = DateTime.now();
    final Map<String, Object?> complete = await click(
      1,
      qr,
      trans: trans,
      prepareId: prepareId,
    );
    expect(complete['error'], 0, reason: 'Complete: $complete');
    expect(complete['error_note'], 'Success');
    return heardPaid(tray, qr, sent);
  }

  /// Payme pays it: CheckPerformTransaction (no `detail`: Payme registers no receipt), CreateTransaction,
  /// PerformTransaction.
  Future<Tray> paymePays(Tray tray, PosQrPayment qr) async {
    final String trans = 'e2e${(++paymeTrans).toString().padLeft(21, '0')}';
    final Map<String, Object?> check = await payme(
      'CheckPerformTransaction',
      <String, Object?>{'amount': qr.amount.tiyin, 'account': paymeAccount(qr)},
    );
    expect(check['error'], isNull, reason: 'CheckPerform: $check');
    final Map<String, Object?> allowed = asMap(check['result']);
    expect(allowed['allow'], isTrue);
    expect(
      allowed.containsKey('detail'),
      isFalse,
      reason: 'no fiscal detail: Rizqim issues the receipt, Payme must not (no double fiscal)',
    );
    final Map<String, Object?> created = await payme(
      'CreateTransaction',
      <String, Object?>{
        'id': trans,
        'time': DateTime.now().millisecondsSinceEpoch,
        'amount': qr.amount.tiyin,
        'account': paymeAccount(qr),
      },
    );
    expect(asMap(created['result'])['state'], 1, reason: 'Create: $created');
    final DateTime sent = DateTime.now();
    final Map<String, Object?> performed = await payme(
      'PerformTransaction',
      <String, Object?>{'id': trans},
    );
    expect(
      asMap(performed['result'])['state'],
      2,
      reason: 'Perform: $performed',
    );
    return heardPaid(tray, qr, sent);
  }

  /// The bank's sandbox confirms an UzQR (the customer paid in their bank app).
  Future<Tray> uzqrPays(Tray tray, PosQrPayment qr) async {
    final String payload = qr.payload;
    expect(payload, startsWith('000201'), reason: 'an EMV merchant QR');
    expect(payload, contains('5303860'), reason: 'in so\'m');
    expect(
      payload,
      contains(
        '54${qr.amount.toApiString().length.toString().padLeft(2, '0')}${qr.amount.toApiString()}',
      ),
    );
    expect(payload.substring(payload.length - 8, payload.length - 4), '6304');
    expect(
      payload.substring(payload.length - 4),
      crc16(payload.substring(0, payload.length - 4)),
      reason: 'the EMV CRC',
    );
    final DateTime sent = DateTime.now();
    final PosQrPayment paid = await tray.till.pos.qr.simulatePaid(qr.uuid);
    expect(paid.status, PosQrStatus.paid);
    return heardPaid(tray, qr, sent);
  }

  /// A plain tray of today's menu (deterministic).
  List<Dish> lunch() {
    final List<List<Dish>> menus = <List<Dish>>[
      <Dish>[Dish.of('Kompleks tushlik', 1)],
      <Dish>[Dish.of('Kompleks tushlik', 1), Dish.of('Choy', 1)],
      <Dish>[Dish.of('Palov', 1), Dish.of('Achichuk', 1), Dish.of('Non', 1)],
      <Dish>[Dish.of('Mastava', 1), Dish.of('Non', 1), Dish.of('Choy', 1)],
      <Dish>[
        Dish.of('Kotlet pyure', 1),
        Dish.of('Vinegret', 1),
        Dish.of('Kompot', 1),
      ],
      <Dish>[Dish.of('Mastava', 1), Dish.of('Palov', 1), Dish.of('Non', 1)],
    ];
    return menus[random.nextInt(menus.length)];
  }

  setUpAll(() async {
    final StackConfig stack = StackConfig.fromEnvironment();
    seed = stack.seed.section('stolovaya');
    section = seed.json;
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
    // The providers' servers: no token, any answer is read (Click and Payme always get 200)
    hooks = Dio(
      BaseOptions(
        baseUrl: config.apiUrl,
        headers: <String, Object?>{'Accept': 'application/json'},
        validateStatus: (_) => true,
      ),
    );
    final Map<String, Object?> merchant = asMap(section['merchant']);
    clickMerchant = asMap(merchant['click']);
    paymeMerchant = asMap(merchant['payme']);
    final Map<String, Object?> corporate = asMap(section['corporate']);
    corporateUuid = corporate['uuid']! as String;
    paxta = asList(corporate['members']).map(Member.new).toList();
    final Map<String, Object?> subscription = asMap(section['subscription']);
    subscriptionUuid = subscription['uuid']! as String;
    abonement = asList(subscription['members']).map(Member.new).toList();
    nasiyaUuid = asMap(section['nasiya'])['uuid']! as String;
    certificates = asList(section['vouchers']);

    clickApi = FakeClickMerchantApi(
      clickMerchant['merchant_user_id']! as String,
      clickMerchant['secret_key']! as String,
      clickMerchant['service_id']! as String,
    );
    final String? port = Platform.environment['POS_E2E_CLICK_API_PORT'];
    if (port != null && port.isNotEmpty) await clickApi.start(int.parse(port));

    Future<Till> openTill(String key, String staff) async {
      final Terminal t = await Terminal.open(config, key);
      await t.signIn(staff);
      final Till till = Till(t)
        ..receipts = FiscalReceiptRepository(t.pos.http)
        ..menu = Menu(
          await t.pos.catalog.load(
            PosChannel.hall,
            menuVersion: t.bootstrap.menuVersion,
          ),
        );
      till.log = await t.listen();
      return till;
    }

    till1 = await openTill('till1', 'cashier1');
    till2 = await openTill('till2', 'cashier2');
  });

  tearDownAll(() async {
    for (final Till t in <Till>[till1, till2]) {
      await t.terminal.close();
    }
    await clickApi.stop();
    owner.close();
    hooks.close();
    String stats(List<Duration> d) {
      final List<int> ms = d.map((Duration x) => x.inMilliseconds).toList()
        ..sort();
      return 'median ${ms[(ms.length - 1) ~/ 2]} ms, max ${ms.last} ms (n=${ms.length})';
    }

    if (heardLatency.isNotEmpty) {
      stdout.writeln(
        '\n[pos-e2e] stolovaya: ${all.length} trays; webhook → qr.paid heard on the till / → the paid bill on the till:',
      );
      for (final String p in heardLatency.keys) {
        stdout.writeln(
          '  $p: ${stats(heardLatency[p]!)} / ${stats(paidLatency[p]!)}',
        );
      }
    }
  });

  group('Stolovaya tushligi', () {
    test('the digests the webhooks are signed with are right (RFC 1321, FIPS 180 vectors)', () {
      expect(md5Hex(''), 'd41d8cd98f00b204e9800998ecf8427e');
      expect(
        md5Hex('The quick brown fox jumps over the lazy dog'),
        '9e107d9d372bb6826bd81d3542a419d6',
      );
      expect(sha1Hex('abc'), 'a9993e364706816aba3e25717850c26c9cd0d89d');
      expect(crc16('123456789'), '29B1');
    });

    test('the format is stolovaya with QR payments and certificates; the owner shortens the QR expiry; shifts open', () async {
      for (final Till t in <Till>[till1, till2]) {
        final PosFormat f = t.terminal.bootstrap.format;
        expect(f.profile, 'stolovaya');
        expect(f.firstScreen, PosFirstScreen.quickCounter);
        expect(
          f.modules.hasAll(<PosModule>[
            PosModule.quickCounter,
            PosModule.dailyMenu,
            PosModule.comboMeal,
            PosModule.customerAccounts,
            PosModule.corporateAccounts,
            PosModule.qrPayments,
            PosModule.vouchers,
          ]),
          isTrue,
          reason: '${t.label}: ${f.modules.enabled}',
        );
        expect(
          f.modules.has(PosModule.serviceCharge),
          isFalse,
          reason: 'VM 75',
        );
        final PosQrSettings qr = t.terminal.bootstrap.qrPayments;
        expect(qr.enabled, isTrue);
        expect(qr.providers, <PosQrProvider>[
          PosQrProvider.click,
          PosQrProvider.payme,
          PosQrProvider.uzqr,
        ]);
        expect(qr.defaultProvider, PosQrProvider.click);
      }

      // The owner's panel: the store's own merchants are set up; the QR lives 1 minute at this branch
      final Response<Object?> shown = await owner.get<Object?>(
        '/v1/owner/pos/qr-payments',
        queryParameters: <String, Object?>{'branch_id': seed.branchId},
      );
      final String text = jsonEncode(asMap(shown.data)['data']);
      expect(
        text,
        isNot(contains(clickMerchant['secret_key']! as String)),
        reason: 'secrets are never shown',
      );
      expect(text, isNot(contains(paymeMerchant['secret_key']! as String)));
      final Response<Object?> saved = await owner.put<Object?>(
        '/v1/owner/pos/qr-payments',
        data: <String, Object?>{
          'branch_id': seed.branchId,
          'expiry_minutes': 1,
        },
      );
      final Map<String, Object?> view = asMap(asMap(saved.data)['data']);
      expect(asMap(view['settings'])['expiry_minutes'], 1);
      expect(asMap(view['settings'])['default_provider'], 'click');
      // The store's own merchant accounts (Click, Payme) and the UzQR gateway are set up; no sandbox provider
      expect(
        <String, Object?>{
          for (final Map<String, Object?> m in asList(view['merchants']))
            m['provider']! as String: '${m['configured']}/${m['driver']}',
        },
        <String, Object?>{
          'click': 'true/click',
          'payme': 'true/payme',
          'uzqr': 'true/uzqr:sandbox',
        },
      );
      for (final Till t in <Till>[till1, till2]) {
        await t.pos.session.loadBootstrap();
        expect(t.terminal.bootstrap.qrPayments.expiryMinutes, 1);
        t.shift = await t.pos.shifts.open(
          openingCash: openingCash,
          note: 'Tushlik',
        );
        t.pos.session.updateShift(t.shift.toSummary());
      }
    });

    test('today\'s menu: a daily dish not on it is refused; the kompleks is one line at its fixed price', () async {
      final PosCatalog catalog = till1.menu.catalog;
      expect(catalog.isOffMenu(till1.menu.product("Sho'rva")), isTrue);
      expect(catalog.isOffMenu(till1.menu.product('Mastava')), isFalse);
      await expectLater(
        till1.pos.orders.open(
          channel: PosChannel.hall,
          guestsCount: 1,
          items: till1.menu.inputs(<Dish>[Dish.of("Sho'rva", 1)]),
          send: true,
        ),
        throwsA(
          isA<PosException>().having(
            (PosException e) => e.statusCode,
            'status',
            422,
          ),
        ),
      );
      expect(
        till1.menu.variant('Kompleks tushlik').price,
        const PosMoney.som(32000),
      );
    });

    test('a Click QR left on the till (it will expire); a QR the till may not confirm itself', () async {
      expiring = await open(
        till2,
        Tray('E kompleks', <Dish>[Dish.of('Kompleks tushlik', 1)]),
      );
      expiringQr = await showQr(expiring, PosQrProvider.click);
      expect(expiringQr.expiresAt, isNotNull);
      final Duration life = expiringQr.expiresAt!.difference(
        DateTime.now().toUtc(),
      );
      expect(
        life.inSeconds,
        inInclusiveRange(30, 65),
        reason: 'the owner\'s 1 minute',
      );
      // Click's QR is the customer's payment in the Click app: only Click's callback confirms it
      await expectLater(
        till2.pos.qr.simulatePaid(expiringQr.uuid),
        refusedWith('qr_not_simulated', 409),
      );
    });

    test('lunch of Paxta Tekstil: eleven members by their talon (one by phone) on both tills, no manager PIN', () async {
      final List<List<Dish>> trays = <List<Dish>>[
        <Dish>[Dish.of('Kompleks tushlik', 1), Dish.of('Choy', 1)], // 34 000
        <Dish>[
          Dish.of('Palov', 1),
          Dish.of('Achichuk', 1),
          Dish.of('Non', 1),
          Dish.of('Choy', 1),
        ], // 33 000
        <Dish>[
          Dish.of('Mastava', 1),
          Dish.of('Kotlet pyure', 1),
          Dish.of('Non', 1),
        ], // 37 000
        <Dish>[Dish.of('Kompleks tushlik', 1)], // 32 000
        <Dish>[
          Dish.of('Mastava', 1),
          Dish.of('Palov', 1),
          Dish.of('Non', 1),
        ], // 39 000
        <Dish>[
          Dish.of('Kotlet pyure', 1),
          Dish.of('Vinegret', 1),
          Dish.of('Kompot', 1),
        ], // 31 000
        <Dish>[Dish.of('Kompleks tushlik', 1), Dish.of('Kompot', 1)], // 36 000
        <Dish>[
          Dish.of('Palov', 1),
          Dish.of('Vinegret', 1),
          Dish.of('Non', 1),
          Dish.of('Choy', 1),
        ], // 34 000
        <Dish>[
          Dish.of('Mastava', 1),
          Dish.of('Kotlet pyure', 1),
          Dish.of('Choy', 1),
        ], // 36 000
        <Dish>[Dish.of('Kompleks tushlik', 1), Dish.of('Non', 1)], // 35 000
        <Dish>[Dish.of('Palov', 1), Dish.of('Mastava', 1)], // 36 000
      ];

      Future<void> member(Till t, int i) async {
        final Member m = paxta[i];
        final bool byPhone = i == 4;
        final PosCorporateLookup before = await t.pos.accounts.lookupMember(
          code: byPhone ? null : m.code.toLowerCase(),
          phone: byPhone ? m.phone : null,
        );
        expect(before.accountUuid, corporateUuid);
        expect(before.companyName, 'Paxta Tekstil MChJ');
        expect(before.memberName, m.name);
        // Review S5: a phone lookup gives the talon code masked (the charge by phone needs none)
        expect(before.code, byPhone ? isNot(m.code) : m.code);
        expect(before.talonMode, 'daily_limit');
        expect(before.chargeApproval, isFalse);
        expect(
          before.memberPhone,
          isNot(m.phone),
          reason: 'the phone is masked at the till',
        );
        expect(before.dailyLimit, const PosMoney.som(40000));
        expect(before.dailyAvailable, const PosMoney.som(40000));
        businessDate = before.businessDate;
        final Tray tray = await open(t, Tray('P${i + 1} ${m.code}', trays[i]));
        expect(before.allows(tray.bill.total), isTrue);
        tray.memberCode = m.code;
        await pay(tray, <PosTenderInput>[
          PosTenderInput.account(
            tray.bill.total,
            accountUuid: corporateUuid,
            memberCode: byPhone ? null : m.code,
            memberPhone: byPhone ? m.phone : null,
          ),
        ]);
        charged[m.code] = (charged[m.code] ?? PosMoney.zero) + tray.bill.total;
        meals[m.code] = (meals[m.code] ?? 0) + 1;
        final PosCorporateLookup after = await t.pos.accounts.lookupMember(
          code: m.code,
        );
        expect(after.usedToday, tray.bill.total);
        expect(after.mealsToday, 1);
        expect(
          after.dailyAvailable,
          const PosMoney.som(40000) - tray.bill.total,
        );
      }

      await Future.wait(<Future<void>>[
        () async {
          for (var i = 0; i < 11; i += 2) {
            await member(till1, i);
          }
        }(),
        () async {
          for (var i = 1; i < 11; i += 2) {
            await member(till2, i);
          }
        }(),
      ]);
      expect(charged, hasLength(11));
    });

    test('the limits: a second lunch over the daily limit is refused (cash), a tea within it is charged, a member who left is refused (card)', () async {
      // Aziza ate 34 000: another 25 000 goes over her 40 000
      final Member aziza = paxta[0];
      final Tray second = await open(
        till1,
        Tray('P1 again', <Dish>[Dish.of('Palov', 1), Dish.of('Non', 1)]),
      );
      final PosCorporateLookup look = await till1.pos.accounts.lookupMember(
        code: aziza.code,
      );
      expect(
        look.allows(second.bill.total),
        isFalse,
        reason: 'the till shows it before charging',
      );
      await expectLater(
        till1.pos.billing.pay(
          second.bill.uuid,
          tenders: <PosTenderInput>[
            PosTenderInput.account(
              second.bill.total,
              accountUuid: corporateUuid,
              memberCode: aziza.code,
            ),
          ],
          shiftUuid: till1.shift.uuid,
        ),
        refusedWith('member_daily_limit', 422),
      );
      await pay(second, <PosTenderInput>[cash(second.bill.total)]);

      // Bekzod ate 33 000: a tea with bread (5 000) still fits
      final Member bekzod = paxta[1];
      final Tray tea = await open(
        till2,
        Tray('P2 tea', <Dish>[Dish.of('Choy', 1), Dish.of('Non', 1)]),
      );
      tea.memberCode = bekzod.code;
      await pay(tea, <PosTenderInput>[
        PosTenderInput.account(
          tea.bill.total,
          accountUuid: corporateUuid,
          memberCode: bekzod.code,
        ),
      ]);
      charged[bekzod.code] = charged[bekzod.code]! + tea.bill.total;
      meals[bekzod.code] = meals[bekzod.code]! + 1;
      expect(
        (await till2.pos.accounts.lookupMember(code: bekzod.code))
            .dailyAvailable,
        const PosMoney.som(2000),
      );

      // Murod has left the company: his talon is switched off
      final Member left = paxta[11];
      expect(left.active, isFalse);
      final PosCorporateLookup gone = await till2.pos.accounts.lookupMember(
        code: left.code,
      );
      expect(gone.memberActive, isFalse);
      final Tray refused = await open(
        till2,
        Tray('P12 inactive', <Dish>[Dish.of('Kompleks tushlik', 1)]),
      );
      await expectLater(
        till2.pos.billing.pay(
          refused.bill.uuid,
          tenders: <PosTenderInput>[
            PosTenderInput.account(
              refused.bill.total,
              accountUuid: corporateUuid,
              memberCode: left.code,
            ),
          ],
          shiftUuid: till2.shift.uuid,
        ),
        refusedWith('member_inactive', 422),
      );
      await pay(refused, <PosTenderInput>[card(refused.bill.total)]);
    });

    test('the abonement: one meal a day, the second one refused; the nasiya customer with the manager\'s approval', () async {
      final Member nodir = abonement[0];
      final Tray first = await open(
        till1,
        Tray('A1', <Dish>[Dish.of('Kompleks tushlik', 1)]),
      );
      final PosCorporateLookup look = await till1.pos.accounts.lookupMember(
        code: nodir.code,
      );
      expect(look.talonMode, 'subscription');
      expect(look.mealsLeft, 1);
      await pay(first, <PosTenderInput>[
        PosTenderInput.account(
          first.bill.total,
          accountUuid: subscriptionUuid,
          memberCode: nodir.code,
        ),
      ]);
      expect(
        (await till1.pos.accounts.lookupMember(code: nodir.code)).mealsLeft,
        0,
      );
      final Tray again = await open(
        till1,
        Tray('A1 again', <Dish>[Dish.of('Mastava', 1), Dish.of('Non', 1)]),
      );
      await expectLater(
        till1.pos.billing.pay(
          again.bill.uuid,
          tenders: <PosTenderInput>[
            PosTenderInput.account(
              again.bill.total,
              accountUuid: subscriptionUuid,
              memberCode: nodir.code,
            ),
          ],
          shiftUuid: till1.shift.uuid,
        ),
        refusedWith('member_meals_used', 422),
      );
      await pay(again, <PosTenderInput>[cash(again.bill.total)]);
      final Tray oydin = await open(
        till2,
        Tray('A2', <Dish>[Dish.of('Palov', 1), Dish.of('Non', 1)]),
      );
      await pay(oydin, <PosTenderInput>[
        PosTenderInput.account(
          oydin.bill.total,
          accountUuid: subscriptionUuid,
          memberCode: abonement[1].code,
        ),
      ]);

      // A charge on a corporate account without a member is refused: the talon names who ate
      final Tray anonymous = await open(
        till2,
        Tray('A anonymous', <Dish>[Dish.of('Choy', 1), Dish.of('Non', 1)]),
      );
      await expectLater(
        till2.pos.billing.pay(
          anonymous.bill.uuid,
          tenders: <PosTenderInput>[
            PosTenderInput.account(
              anonymous.bill.total,
              accountUuid: corporateUuid,
            ),
          ],
          shiftUuid: till2.shift.uuid,
        ),
        refusedWith('member_required', 422),
      );
      await pay(anonymous, <PosTenderInput>[cash(anonymous.bill.total)]);

      // The old nasiya: the manager approves it
      final Tray nasiya = await open(
        till1,
        Tray('N Shavkat aka', <Dish>[
          Dish.of('Kompleks tushlik', 1),
          Dish.of('Kompot', 1),
        ]),
      );
      await pay(nasiya, <PosTenderInput>[
        PosTenderInput.account(nasiya.bill.total, accountUuid: nasiyaUuid),
      ], approvalPermission: PosPermissions.customerAccountsManage);
    });

    test('Click QR: the store\'s own Click confirms through the webhook, the till never taps; a duplicate Complete is a no-op; a wrong amount and a wrong key are refused', () async {
      for (var i = 0; i < 5; i++) {
        final Till t = i.isEven ? till1 : till2;
        final Tray tray = await open(t, Tray('C${i + 1}', lunch()));
        final PosQrPayment qr = await showQr(tray, PosQrProvider.click);
        expect(
          qr.payload,
          contains('click'),
          reason: 'the Click checkout link: ${qr.payload}',
        );
        expect(qr.payload, contains('pos_qr_${qr.uuid}'));
        if (i == 0) {
          // Not the store's key: Click's signature check fails, nothing is held
          final Map<String, Object?> forged = await click(
            0,
            qr,
            trans: '${++clickTrans}',
            secret: 'not-the-store-key',
          );
          expect(forged['error'], -1);
          // A Prepare for another sum (one so'm more): refused, the customer is not charged
          final Map<String, Object?> wrong = await click(
            0,
            qr,
            trans: '${++clickTrans}',
            amount: (qr.amount + const PosMoney.som(1)).toApiString(),
          );
          expect(wrong['error'], -2, reason: '$wrong');
          final PosQrPayment still = await t.pos.qr.poll(qr.uuid);
          expect(still.status, PosQrStatus.pending);
          expect(still.held, isFalse);
        }
        await clickPays(tray, qr);
        clickTrays.add(tray);
        if (i == 1) {
          // Click sends the Complete again (it did not get the answer): the same success, no second payment
          final Map<String, Object?> again = await click(
            1,
            qr,
            trans: lastClick.trans,
            prepareId: lastClick.prepareId,
          );
          expect(again['error'], 0, reason: '$again');
          expect(again['error_note'], 'Already confirmed');
          final PosBilling billing = await t.pos.billing.bills(tray.order.uuid);
          expect(
            billing.bills
                .firstWhere((PosBill b) => b.uuid == tray.bill.uuid)
                .payments
                .where((PosPayment p) => p.tender == 'qr'),
            hasLength(1),
            reason: 'a duplicate callback is a no-op',
          );
          // A new Prepare for a paid request: "already paid"
          final Map<String, Object?> late = await click(
            0,
            qr,
            trans: '${++clickTrans}',
          );
          expect(late['error'], -4);
        }
      }
    });

    test('the cashier takes cash while Click completes the same bill: exactly one of them is taken', () async {
      final Tray tray = await open(
        till1,
        Tray('C race', <Dish>[
          Dish.of('Kompleks tushlik', 1),
          Dish.of('Non', 1),
        ]),
      );
      final PosQrPayment qr = await showQr(tray, PosQrProvider.click);
      final String trans = '${++clickTrans}';
      final Map<String, Object?> prepare = await click(0, qr, trans: trans);
      expect(prepare['error'], 0);
      final DateTime sent = DateTime.now();
      final List<Object?> outcome = await Future.wait(<Future<Object?>>[
        click(1, qr, trans: trans, prepareId: prepare['merchant_prepare_id']),
        till1.pos.billing
            .pay(
              tray.bill.uuid,
              tenders: <PosTenderInput>[cash(tray.bill.total)],
              shiftUuid: till1.shift.uuid,
            )
            .then<Object?>((PosBillResult r) => r, onError: (Object e) => e),
      ]);
      final Map<String, Object?> complete = outcome[0]! as Map<String, Object?>;
      final bool qrWon = complete['error'] == 0;
      final bool cashWon = outcome[1] is PosBillResult;
      expect(
        qrWon != cashWon,
        isTrue,
        reason: 'one payment only: $complete / ${outcome[1]}',
      );
      if (qrWon) {
        expect(outcome[1], isA<PosException>());
        await heardPaid(tray, qr, sent);
      } else {
        expect(
          complete['error'],
          -9,
          reason: 'Click gives the money back: $complete',
        );
        tray.paidWith('cash', tray.bill.total);
        await finish(tray, (outcome[1]! as PosBillResult).bill);
        expect(
          (await till1.pos.qr.poll(qr.uuid)).status,
          PosQrStatus.cancelled,
        );
      }
      printOnFailure('the race: ${qrWon ? 'Click' : 'cash'} won');
    });

    test('Payme QR: CheckPerform without fiscal detail, Create, Perform; a repeated Perform is a no-op; SetFiscalData is never recorded; a wrong sum and a wrong key are refused', () async {
      for (var i = 0; i < 5; i++) {
        final Till t = i.isEven ? till2 : till1;
        final Tray tray = await open(t, Tray('M${i + 1}', lunch()));
        final PosQrPayment qr = await showQr(tray, PosQrProvider.payme);
        // Payme's checkout link: base64 of "m=<merchant>;ac.order_id=pos_qr_<uuid>;a=<tiyin>;..."
        expect(qr.payload, startsWith('https://checkout.paycom.uz/'));
        final String params = utf8.decode(
          base64.decode(base64.normalize(qr.payload.split('/').last)),
        );
        expect(params, contains('m=${paymeMerchant['merchant_id']}'));
        expect(params, contains('ac.order_id=pos_qr_${qr.uuid}'));
        expect(params, contains('a=${qr.amount.tiyin}'));
        if (i == 0) {
          final Map<String, Object?> forged = await payme(
            'CheckPerformTransaction',
            <String, Object?>{
              'amount': qr.amount.tiyin,
              'account': paymeAccount(qr),
            },
            key: 'not-the-store-key',
          );
          expect(asMap(forged['error'])['code'], -32504);
          final Map<String, Object?> wrong = await payme(
            'CheckPerformTransaction',
            <String, Object?>{
              'amount': qr.amount.tiyin + 100,
              'account': paymeAccount(qr),
            },
          );
          expect(asMap(wrong['error'])['code'], -31001, reason: '$wrong');
        }
        if (i == 2) {
          // The customer opens Payme and gives up: Payme cancels its transaction, the QR is cancelled, the till
          // makes a new one
          final String trans =
              'e2e${(++paymeTrans).toString().padLeft(21, '0')}';
          final Map<String, Object?> created = await payme(
            'CreateTransaction',
            <String, Object?>{
              'id': trans,
              'time': DateTime.now().millisecondsSinceEpoch,
              'amount': qr.amount.tiyin,
              'account': paymeAccount(qr),
            },
          );
          expect(asMap(created['result'])['state'], 1);
          expect((await t.pos.qr.poll(qr.uuid)).held, isTrue);
          final Map<String, Object?> cancelled = await payme(
            'CancelTransaction',
            <String, Object?>{'id': trans, 'reason': 3},
          );
          expect(asMap(cancelled['result'])['state'], -1, reason: '$cancelled');
          await t.log.waitFor(
            (PosEvent e) =>
                e is PosQrPaymentChanged &&
                e.qrUuid == qr.uuid &&
                e.status == 'cancelled',
            what: 'qr.cancelled by Payme',
          );
          final Map<String, Object?> state = await payme(
            'CheckTransaction',
            <String, Object?>{'id': trans},
          );
          expect(asMap(state['result'])['state'], -1);
          expect(asMap(state['result'])['reason'], 3);
          // A Perform of the cancelled transaction: refused, no money
          final Map<String, Object?> perform = await payme(
            'PerformTransaction',
            <String, Object?>{'id': trans},
          );
          expect(perform['error'], isNotNull, reason: '$perform');
          await paymePays(tray, await showQr(tray, PosQrProvider.payme));
        } else {
          await paymePays(tray, qr);
        }
        paymeTrays.add(tray);
        if (i == 1) {
          final String trans = 'e2e${paymeTrans.toString().padLeft(21, '0')}';
          final Map<String, Object?> again = await payme(
            'PerformTransaction',
            <String, Object?>{'id': trans},
          );
          expect(
            asMap(again['result'])['state'],
            2,
            reason: 'the same answer: $again',
          );
          final Map<String, Object?> state = await payme(
            'CheckTransaction',
            <String, Object?>{'id': trans},
          );
          expect(asMap(state['result'])['state'], 2);
          final PosBilling billing = await t.pos.billing.bills(tray.order.uuid);
          expect(
            billing.bills
                .firstWhere((PosBill b) => b.uuid == tray.bill.uuid)
                .payments
                .where((PosPayment p) => p.tender == 'qr'),
            hasLength(1),
          );
          // Payme reports its own fiscal data for the transaction: answered, never recorded as a receipt
          final Map<String, Object?> fiscal = await payme(
            'SetFiscalData',
            <String, Object?>{
              'id': trans,
              'type': 'PERFORM',
              'fiscal_data': <String, Object?>{
                'receipt_id': 1,
                'status_code': 0,
                'message': 'accepted',
                'terminal_id': 'EP000000000001',
                'fiscal_sign': '000000000001',
                'qr_code_url': 'https://ofd.soliq.uz/check?t=EP000000000001',
                'date': '20261113120000',
              },
            },
          );
          expect(asMap(fiscal['result'])['success'], isTrue);
          // A new transaction on a paid request: "already paid"
          final Map<String, Object?> late = await payme(
            'CheckPerformTransaction',
            <String, Object?>{
              'amount': qr.amount.tiyin,
              'account': paymeAccount(qr),
            },
          );
          expect(asMap(late['error'])['code'], -31051);
        }
      }
    });

    test('UzQR: the dynamic EMV QR of the sandbox gateway, confirmed by the bank\'s simulate call', () async {
      for (var i = 0; i < 4; i++) {
        final Till t = i.isEven ? till1 : till2;
        final Tray tray = await open(t, Tray('U${i + 1}', lunch()));
        final PosQrPayment qr = await showQr(tray, PosQrProvider.uzqr);
        await uzqrPays(tray, qr);
        uzqrTrays.add(tray);
      }
    });

    test('a bill changed after its QR was shown cancels it: the provider is refused, the new bill\'s QR is paid', () async {
      final Tray tray = await open(
        till2,
        Tray('B changed', <Dish>[Dish.of('Palov', 1), Dish.of('Non', 1)]),
      );
      final PosQrPayment old = await showQr(tray, PosQrProvider.payme);
      // The guest takes a tea too: the order changes under its bill
      final PosOrder order = await till2.pos.orders.get(tray.order.uuid);
      tray.order = await till2.pos.orders.addRound(
        order.uuid,
        version: order.version,
        items: till2.menu.inputs(<Dish>[Dish.of('Choy', 1)]),
        send: true,
      );
      await till2.log.waitFor(
        (PosEvent e) =>
            e is PosQrPaymentChanged &&
            e.qrUuid == old.uuid &&
            e.status == 'cancelled',
        what: 'qr.cancelled of the changed bill',
      );
      final PosQrPayment cancelled = await till2.pos.qr.poll(old.uuid);
      expect(cancelled.status, PosQrStatus.cancelled);
      expect(cancelled.cancelReason, 'bill_changed');
      final Map<String, Object?> refused = await payme(
        'CheckPerformTransaction',
        <String, Object?>{
          'amount': old.amount.tiyin,
          'account': paymeAccount(old),
        },
      );
      expect(
        asMap(refused['error'])['code'],
        -31051,
        reason: 'the money is not taken: $refused',
      );
      final Tray rebilled =
          Tray('B changed', <Dish>[...tray.dishes, Dish.of('Choy', 1)])
            ..till = till2
            ..order = tray.order;
      await bill(rebilled);
      expect(rebilled.bill.uuid, isNot(tray.bill.uuid));
      final PosQrPayment fresh = await showQr(rebilled, PosQrProvider.payme);
      await paymePays(rebilled, fresh);
    });

    test('one bill paid with a certificate and a QR; a blocked certificate is refused', () async {
      final Map<String, Object?> a = certificates[0];
      final String code = a['code']! as String;
      final PosVoucherLookup before = await till1.pos.vouchers.lookup(code);
      expect(before.voucher.balance, money(a['value']));
      expect(before.voucher.status, PosVoucherStatus.active);
      final Tray tray = await open(
        till1,
        Tray('V+Q mixed', <Dish>[
          Dish.of('Mastava', 1),
          Dish.of('Kotlet pyure', 1),
          Dish.of('Non', 1),
        ]),
      );
      const PosMoney part = PosMoney.som(20000);
      await pay(tray, <PosTenderInput>[
        PosTenderInput.voucher(part, code: code),
      ]);
      expect(tray.bill.isPaid, isFalse);
      expect(tray.bill.dueAmount, tray.bill.total - part);
      final PosQrPayment qr = await showQr(tray, PosQrProvider.click);
      expect(
        qr.amount,
        tray.bill.total - part,
        reason: 'the QR is for the rest',
      );
      await clickPays(tray, qr);
      expect(tray.tenders, <String, PosMoney>{
        'voucher': part,
        'qr': qr.amount,
      });
      expect(
        (await till1.pos.vouchers.lookup(code)).voucher.balance,
        money(a['value']) - part,
      );

      final Map<String, Object?> c = certificates[2];
      expect(c['blocked'], isTrue);
      final Tray blocked = await open(
        till2,
        Tray('V blocked', <Dish>[Dish.of('Kompleks tushlik', 1)]),
      );
      await expectLater(
        till2.pos.billing.pay(
          blocked.bill.uuid,
          tenders: <PosTenderInput>[
            PosTenderInput.voucher(
              blocked.bill.total,
              code: c['code']! as String,
            ),
          ],
          shiftUuid: till2.shift.uuid,
        ),
        refusedWith('voucher_blocked', 409),
      );
      await pay(blocked, <PosTenderInput>[cash(blocked.bill.total)]);
    });

    test('a certificate sold at the till (advance receipt) is spent on two bills; a third use is refused', () async {
      sold = await till2.pos.vouchers.sell(
        const PosVoucherSale(
          faceValue: PosMoney.som(50000),
          tender: PosTender.cash,
          buyerName: 'Laylo',
          buyerPhone: '+998901234567',
        ),
        shiftUuid: till2.shift.uuid,
      );
      till2.vouchersSold.add(sold);
      expect(sold.balance, const PosMoney.som(50000));
      expect(sold.status, PosVoucherStatus.active);
      expect(sold.print.code, sold.code);
      expect(
        sold.print.qr,
        contains(sold.code.replaceAll('-', '').substring(0, 4)),
      );
      expect(sold.print.value, const PosMoney.som(50000));
      expect(sold.buyerPhone, isNot('+998901234567'), reason: 'masked');
      expect(sold.fiscalReceiptUuid, isNotNull);
      final PosFiscalReceiptDetail advance = await till2.receipts.get(
        sold.fiscalReceiptUuid!,
      );
      expect(advance.receiptType, FiscalReceiptType.advance);
      expect(advance.total, const PosMoney.som(50000));

      final Tray first = await open(
        till1,
        Tray('S1', <Dish>[Dish.of('Kompleks tushlik', 1)]),
      );
      await pay(first, <PosTenderInput>[
        PosTenderInput.voucher(first.bill.total, code: sold.code),
      ]);
      final PosMoney left = const PosMoney.som(50000) - first.bill.total;
      expect(
        (await till1.pos.vouchers.lookup(sold.code)).voucher.balance,
        left,
      );
      final Tray second = await open(
        till2,
        Tray('S2', <Dish>[
          Dish.of('Palov', 1),
          Dish.of('Achichuk', 1),
          Dish.of('Non', 1),
          Dish.of('Kompot', 1),
        ]),
      );
      await pay(second, <PosTenderInput>[
        PosTenderInput.voucher(
          left,
          code: sold.code.toLowerCase().replaceAll('-', ' '),
        ),
        cash(second.bill.total - left),
      ]);
      final PosVoucherLookup used = await till2.pos.vouchers.lookup(sold.code);
      expect(used.voucher.balance, PosMoney.zero);
      expect(used.voucher.status, PosVoucherStatus.used);
      expect(
        used.entries.where((PosVoucherEntry e) => e.type == 'redemption'),
        hasLength(2),
      );
      final Tray third = await open(
        till1,
        Tray('S3', <Dish>[Dish.of('Choy', 1), Dish.of('Non', 1)]),
      );
      await expectLater(
        till1.pos.billing.pay(
          third.bill.uuid,
          tenders: <PosTenderInput>[
            PosTenderInput.voucher(third.bill.total, code: sold.code),
          ],
          shiftUuid: till1.shift.uuid,
        ),
        refusedWith('voucher_used', 409),
      );
      await pay(third, <PosTenderInput>[cash(third.bill.total)]);
    });

    test('two tills spend one certificate at the same moment: one wins, the other is refused and pays cash', () async {
      final Map<String, Object?> b = certificates[1];
      final String code = b['code']! as String;
      final Tray one = await open(
        till1,
        Tray('R1', <Dish>[
          Dish.of('Kompleks tushlik', 1),
          Dish.of('Kompot', 1),
        ]),
      );
      final Tray two = await open(
        till2,
        Tray('R2', <Dish>[Dish.of('Kompleks tushlik', 1), Dish.of('Choy', 1)]),
      );
      final List<Object?> outcome = await Future.wait(<Future<Object?>>[
        for (final Tray tray in <Tray>[one, two])
          tray.till.pos.billing
              .pay(
                tray.bill.uuid,
                tenders: <PosTenderInput>[
                  PosTenderInput.voucher(tray.bill.total, code: code),
                ],
                shiftUuid: tray.till.shift.uuid,
              )
              .then<Object?>((PosBillResult r) => r, onError: (Object e) => e),
      ]);
      final List<int> won = <int>[
        for (var i = 0; i < 2; i++)
          if (outcome[i] is PosBillResult) i,
      ];
      expect(won, hasLength(1), reason: '$outcome');
      final int w = won.single;
      final Tray winner = w == 0 ? one : two;
      final Tray loser = w == 0 ? two : one;
      final Object? refusal = outcome[1 - w];
      expect(
        refusal,
        isA<PosException>().having(
          (PosException e) => e.reason,
          'reason',
          'voucher_insufficient',
        ),
      );
      winner.paidWith('voucher', winner.bill.total);
      await finish(winner, (outcome[w]! as PosBillResult).bill);
      raceWinner = winner;
      await pay(loser, <PosTenderInput>[cash(loser.bill.total)]);
      expect(
        (await till1.pos.vouchers.lookup(code)).voucher.balance,
        money(b['value']) - winner.bill.total,
      );
    });

    test('a promo code at the till: the discount line with the code; the bill and its receipt are the discounted total', () async {
      final Tray tray = await ring(
        till1,
        Tray('Promo', <Dish>[
          Dish.of('Palov', 1),
          Dish.of('Mastava', 1),
          Dish.of('Non', 1),
          Dish.of('Kompot', 1),
        ]),
      );
      await expectLater(
        till1.pos.vouchers.applyPromo(
          tray.order.uuid,
          version: tray.order.version,
          code: 'NOSUCHCODE',
        ),
        refusedWith('promo_not_found', 404),
      );
      final PosOrder discounted = await till1.pos.vouchers.applyPromo(
        tray.order.uuid,
        version: tray.order.version,
        code: promoCode.toLowerCase(),
      );
      expect(discounted.discountReason, 'promo:$promoCode');
      // 10% of 43 000 is 4 300 (under the 5 000 cap)
      expect(discounted.discount, const PosMoney.som(4300));
      tray.order = discounted;
      await bill(tray, discount: const PosMoney.som(4300));
      expect(tray.bill.discount, const PosMoney.som(4300));
      await pay(tray, <PosTenderInput>[card(tray.bill.total)]);
    });

    test(
      'the rest of the hour: plain trays on both tills at once, cash and card',
      () async {
        final int plain = max(0, 50 - all.length - 1);
        final List<Tray> trays = <Tray>[
          for (var i = 0; i < plain; i++) Tray('T${i + 1}', lunch()),
        ];
        await Future.wait(<Future<void>>[
          for (final Till t in <Till>[till1, till2])
            () async {
              for (var i = t == till1 ? 0 : 1; i < trays.length; i += 2) {
                final Tray tray = await open(t, trays[i]);
                await pay(tray, <PosTenderInput>[
                  if (i % 3 == 0)
                    card(tray.bill.total)
                  else
                    cash(tray.bill.total),
                ]);
              }
            }(),
        ]);
      },
    );

    test('the QR left on the till expires: Click\'s callback for it is refused, the tray pays cash', () async {
      final DateTime until = expiringQr.expiresAt!.add(
        const Duration(seconds: 2),
      );
      final Duration wait = until.difference(DateTime.now().toUtc());
      if (!wait.isNegative) await Future<void>.delayed(wait);
      final Map<String, Object?> prepare = await click(
        0,
        expiringQr,
        trans: '${++clickTrans}',
      );
      expect(
        prepare['error'],
        -9,
        reason: 'Click does not take the money: $prepare',
      );
      await till2.log.waitFor(
        (PosEvent e) =>
            e is PosQrPaymentChanged &&
            e.qrUuid == expiringQr.uuid &&
            e.status == 'expired',
        what: 'qr.expired',
      );
      expect(
        (await till2.pos.qr.poll(expiringQr.uuid)).status,
        PosQrStatus.expired,
      );
      final Map<String, Object?> complete = await click(
        1,
        expiringQr,
        trans: '$clickTrans',
        prepareId: 1,
      );
      expect(complete['error'], isNot(0));
      await pay(expiring, <PosTenderInput>[cash(expiring.bill.total)]);
      expect(
        all.length,
        greaterThanOrEqualTo(50),
        reason: 'about fifty trays in the hour',
      );
    });

    test('refunds: UzQR through the sandbox reversal, Click through its merchant API (refused first: no refund), a Payme part refused, a certificate payment back onto the certificate', () async {
      Future<PosRefund> refund(
        Tray tray,
        PosPayment p,
        PosMoney amount,
        String reason,
      ) => approved<PosRefund>(
        tray.till,
        (String? token) => tray.till.pos.billing.refund(
          paymentUuid: p.uuid,
          amount: amount,
          reason: reason,
          shiftUuid: tray.till.shift.uuid,
          approvalToken: token,
        ),
        PosPermissions.refund,
      );
      PosPayment paymentOf(Tray tray, String tender) =>
          tray.bill.payments.firstWhere((PosPayment p) => p.tender == tender);
      Future<void> checkRefund(
        Tray tray,
        PosRefund r,
        PosMoney amount, {
        bool prepayment = false,
      }) async {
        expect(r.amount, amount);
        final PosFiscalReceiptDetail receipt =
            r.fiscal.receipt ??
            await tray.till.receipts.get(r.fiscal.receiptUuid!);
        expect(receipt.receiptType, FiscalReceiptType.refund);
        expect(receipt.total, amount);
        expect(receipt.originalReceiptUuid, tray.receipt!.uuid);
        expect(
          PosMoney.sum(<PosMoney>[
            for (final PosFiscalPaymentPart p in receipt.payments)
              if (p.prepayment) p.amount,
          ]),
          prepayment ? amount : PosMoney.zero,
        );
        tray.till.refunds.add(r);
      }

      // UzQR: the sandbox gateway reverses it
      final Tray u = uzqrTrays.first;
      final PosRefund ur = await refund(
        u,
        paymentOf(u, 'qr'),
        u.bill.total,
        'Taom sovuq edi',
      );
      await checkRefund(u, ur, u.bill.total);
      final PosQrPayment uq = await u.till.pos.qr.poll(u.qr!.uuid);
      expect(uq.status, PosQrStatus.refunded);
      expect(uq.refundedAmount, u.bill.total);

      // Click: the merchant API refuses first — the refund is not made (never a silent cash refund), then reverses
      final Tray c = clickTrays[2];
      if (Platform.environment['POS_E2E_CLICK_API_PORT'] != null) {
        clickApi.refuse = true;
        await expectLater(
          refund(
            c,
            paymentOf(c, 'qr'),
            c.bill.total,
            "Mijoz noto'g'ri to'ladi",
          ),
          refusedWith('qr_refund_failed', 409),
        );
        expect((await c.till.pos.billing.bills(c.order.uuid)).refunds, isEmpty);
        expect((await c.till.pos.qr.poll(c.qr!.uuid)).status, PosQrStatus.paid);
        clickApi.refuse = false;
        final PosRefund cr = await refund(
          c,
          paymentOf(c, 'qr'),
          c.bill.total,
          "Mijoz noto'g'ri to'ladi",
        );
        await checkRefund(c, cr, c.bill.total);
        expect(clickApi.problems, isEmpty);
        expect(
          clickApi.reversed,
          hasLength(1),
          reason: 'one reversal of the payment',
        );
        expect(clickApi.reversed.single, paymentOf(c, 'qr').reference);
        expect(
          (await c.till.pos.qr.poll(c.qr!.uuid)).status,
          PosQrStatus.refunded,
        );
      }

      // Payme: only the whole payment goes back; a part is refused before any money moves
      final Tray m = paymeTrays.first;
      await expectLater(
        refund(
          m,
          paymentOf(m, 'qr'),
          const PosMoney.som(2000),
          'Choy berilmadi',
        ),
        refusedWith('qr_partial_refund_unsupported', 422),
      );

      // A corporate lunch: the kompot was not given; the part goes back onto the company's account and the member's day
      final Tray p7 = all.firstWhere((Tray t) => t.label.startsWith('P7 '));
      const PosMoney kompot = PosMoney.som(4000);
      final PosRefund pr = await refund(
        p7,
        paymentOf(p7, 'account'),
        kompot,
        'Kompot berilmadi',
      );
      await checkRefund(p7, pr, kompot);
      charged[p7.memberCode!] = charged[p7.memberCode!]! - kompot;
      final PosCorporateLookup p7day = await p7.till.pos.accounts.lookupMember(
        code: p7.memberCode,
      );
      expect(p7day.usedToday, p7.bill.total - kompot);
      expect(p7day.mealsToday, 1, reason: 'a part refunded: still a meal');

      // The certificate part goes back onto the certificate, never as cash
      final Tray v = raceWinner;
      final String code = certificates[1]['code']! as String;
      final PosMoney before = (await v.till.pos.vouchers.lookup(code))
          .voucher
          .balance;
      final PosRefund vr = await refund(
        v,
        paymentOf(v, 'voucher'),
        v.bill.total,
        'Buyurtma bekor qilindi',
      );
      await checkRefund(v, vr, v.bill.total, prepayment: true);
      final PosVoucherLookup after = await v.till.pos.vouchers.lookup(code);
      expect(after.voucher.balance, before + v.bill.total);
      expect(
        after.entries.where((PosVoucherEntry e) => e.type == 'refund'),
        hasLength(1),
      );
    });

    test('no double fiscal: one sale receipt per bill, the advance of the certificate, the refunds; none from Click or Payme', () async {
      final List<Map<String, Object?>> receipts = <Map<String, Object?>>[];
      for (var page = 1; ; page++) {
        final Response<Object?> r = await owner.get<Object?>(
          '/v1/owner/fiscal/receipts',
          queryParameters: <String, Object?>{
            'branch_id': seed.branchId,
            'per_page': 100,
            'page': page,
          },
        );
        final Map<String, Object?> data = asMap(asMap(r.data)['data']);
        receipts.addAll(asList(data['data']));
        if ((data['current_page']! as num) >= (data['last_page']! as num)) {
          break;
        }
      }
      expect(
        receipts.where(
          (Map<String, Object?> r) =>
              r['driver'] == 'external' ||
              r['provider'] == 'click' ||
              r['provider'] == 'payme',
        ),
        isEmpty,
        reason: 'no receipt recorded from Click or Payme for a POS QR',
      );
      final List<Map<String, Object?>> sales = receipts
          .where((Map<String, Object?> r) => r['type'] == 'sale')
          .toList();
      expect(
        sales,
        hasLength(all.length),
        reason: 'one sale receipt per paid bill',
      );
      expect(
        sales
            .map(
              (Map<String, Object?> r) =>
                  '${r['subject_type']}:${r['subject_id']}',
            )
            .toSet(),
        hasLength(all.length),
        reason: 'never two for one sale',
      );
      expect(
        sales.map((Map<String, Object?> r) => r['uuid']).toSet(),
        all.map((Tray t) => t.receipt!.uuid).toSet(),
      );
      expect(
        PosMoney.sum(sales.map((Map<String, Object?> r) => money(r['total']))),
        PosMoney.sum(all.map((Tray t) => t.bill.total)),
      );
      final List<Map<String, Object?>> advances = receipts
          .where((Map<String, Object?> r) => r['type'] == 'advance')
          .toList();
      expect(advances.map((Map<String, Object?> r) => r['uuid']), <Object?>[
        sold.fiscalReceiptUuid,
      ]);
      final List<PosRefund> refunds = <PosRefund>[
        ...till1.refunds,
        ...till2.refunds,
      ];
      expect(
        receipts.where((Map<String, Object?> r) => r['type'] == 'refund'),
        hasLength(refunds.length),
      );

      // Click's own fiscal submission never ran for these payments; Payme's SetFiscalData was only logged
      final String? logFile = Platform.environment['POS_E2E_LOG_FILE'];
      if (logFile != null && logFile.isNotEmpty) {
        final String log = File(logFile).readAsStringSync();
        expect(log, isNot(contains('Click fiscal data failed')));
        expect(
          log,
          contains('Payme SetFiscalData for a POS QR payment ignored'),
        );
      }
    });

    test('Z on both tills: by tender with the QR providers and the certificates, cash expected, the drawer is right', () async {
      for (final Till t in <Till>[till1, till2]) {
        PosMoney sum(String tender) => PosMoney.sum(<PosMoney>[
          for (final Tray tray in t.trays)
            tray.tenders[tender] ?? PosMoney.zero,
        ]);
        int count(String tender) => t.trays
            .where((Tray tray) => tray.tenders.containsKey(tender))
            .length;
        final PosShiftWithReport x = await t.pos.shifts.xReport(t.shift.uuid);
        final Map<String, Object?> report = x.report!.raw;
        for (final String tender in <String>[
          'cash',
          'card',
          'qr',
          'account',
          'voucher',
        ]) {
          expect(
            tenderAmount(report, tender),
            sum(tender),
            reason: '${t.label}: $tender on the X report',
          );
        }
        for (final PosQrProviderTotal p in x.report!.qrProviders) {
          if (p.provider == 'manual') {
            expect(p.count, 0, reason: 'no static QR confirmed by hand');
            continue;
          }
          final List<Tray> of = t.trays
              .where((Tray tray) => tray.qrProvider == p.provider)
              .toList();
          expect(p.count, of.length, reason: '${t.label}: ${p.provider}');
          expect(
            p.amount,
            PosMoney.sum(of.map((Tray tray) => tray.tenders['qr']!)),
            reason: '${t.label}: ${p.provider}',
          );
        }
        final PosShiftVouchers v = x.report!.vouchers;
        expect(v.soldCount, t.vouchersSold.length);
        expect(
          v.soldCash,
          PosMoney.sum(t.vouchersSold.map((PosVoucher s) => s.faceValue)),
        );
        expect(v.redeemedCount, count('voucher'));
        expect(v.redeemedAmount, sum('voucher'));
        expect(v.drawerCash, v.soldCash);
        final Map<String, Object?> refunds = asMap(report['refunds']);
        expect(refunds['count'], t.refunds.length);
        expect(
          money(refunds['amount']),
          PosMoney.sum(t.refunds.map((PosRefund r) => r.amount)),
        );
        expect(
          money(asMap(report['sales'])['bills_total']),
          PosMoney.sum(t.trays.map((Tray tray) => tray.bill.total)),
        );
        // The drawer: the opening, the cash of the trays and the certificate sold for cash (no cash refund)
        final PosMoney expected = openingCash + sum('cash') + v.soldCash;
        expect(
          x.report!.expectedCash,
          expected,
          reason: '${t.label}: cash expected',
        );
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
        t.pos.session.updateShift(null);
      }
    });

    test('the corporate monthly statement (member × day) is the charges; its Excel file opens', () async {
      final String month = businessDate.substring(0, 7);
      final Response<Object?> r = await owner.get<Object?>(
        '/v1/pos/customer-accounts/$corporateUuid/corporate-statement',
        queryParameters: <String, Object?>{'month': month},
      );
      final Map<String, Object?> s = asMap(asMap(r.data)['data']);
      expect(asMap(s['account'])['company_name'], 'Paxta Tekstil MChJ');
      final Map<String, Map<String, Object?>> rows =
          <String, Map<String, Object?>>{
            for (final Map<String, Object?> row in asList(s['rows']))
              if (row['member'] != null)
                asMap(row['member'])['code']! as String: row,
          };
      for (final MapEntry<String, PosMoney> e in charged.entries) {
        final Map<String, Object?> row = rows[e.key]!;
        expect(money(row['amount']), e.value, reason: e.key);
        expect(row['meals'], meals[e.key], reason: e.key);
        final List<Map<String, Object?>> cells = asList(row['cells']);
        expect(cells, hasLength(1), reason: 'one business day');
        expect(cells.single['date'], businessDate);
        expect(money(cells.single['amount']), e.value);
      }
      for (final MapEntry<String, Map<String, Object?>> e in rows.entries) {
        if (!charged.containsKey(e.key)) {
          expect(
            money(e.value['amount']),
            PosMoney.zero,
            reason: '${e.key} ate nothing',
          );
        }
      }
      final PosMoney total = PosMoney.sum(charged.values);
      expect(money(s['total']), total);
      expect(s['meals'], meals.values.fold<int>(0, (int a, int b) => a + b));
      final Map<String, Object?> day = asList(s['day_totals'])
          .firstWhere((Map<String, Object?> d) => d['date'] == businessDate);
      expect(money(day['amount']), total);
      expect(money(s['balance']), total, reason: 'nothing repaid yet');

      final Response<List<int>> x = await owner.get<List<int>>(
        '/v1/pos/customer-accounts/$corporateUuid/corporate-statement/export',
        queryParameters: <String, Object?>{'month': month},
        options: Options(responseType: ResponseType.bytes),
      );
      expect(x.headers.value('content-type'), contains('spreadsheetml'));
      final List<int> bytes = x.data!;
      expect(bytes.sublist(0, 4), <int>[
        0x50,
        0x4b,
        0x03,
        0x04,
      ], reason: 'a ZIP (xlsx)');
      final XlsxFile file = XlsxFile.parse(bytes);
      expect(file.sheetNames, <String>['Hisobot', 'Hisob-faktura']);
      final String text = file.allText;
      // The owner (customer_accounts.manage) sees every talon code in full
      for (final Member m in paxta.take(11)) {
        expect(text, contains(m.code));
        expect(text, contains(m.name));
      }

      // Review S1: a reader with reports.view only (the owner lets cashiers see reports) gets the codes masked
      Future<void> cashiersSeeReports(bool? allowed) async {
        await owner.put<Object?>(
          '/v1/owner/permissions',
          data: <String, Object?>{
            'role': 'cashier',
            'permission': 'reports.view',
            'allowed': allowed,
          },
        );
      }

      await cashiersSeeReports(true);
      try {
        final Dio reader = till1.terminal.client.dio;
        final Map<String, Object?> masked = asMap(
          asMap(
            (await reader.get<Object?>(
              '/v1/pos/customer-accounts/$corporateUuid/corporate-statement',
              queryParameters: <String, Object?>{'month': month},
            )).data,
          )['data'],
        );
        expect(masked['masked'], isTrue);
        expect(money(masked['total']), total);
        final Set<String> codes = <String>{
          for (final Map<String, Object?> row in asList(masked['rows']))
            if (row['member'] != null) asMap(row['member'])['code']! as String,
        };
        for (final Member m in paxta) {
          expect(codes, isNot(contains(m.code)), reason: m.name);
        }
        final XlsxFile readerFile = XlsxFile.parse(
          (await reader.get<List<int>>(
            '/v1/pos/customer-accounts/$corporateUuid/corporate-statement/export',
            queryParameters: <String, Object?>{'month': month},
            options: Options(responseType: ResponseType.bytes),
          )).data!,
        );
        for (final Member m in paxta.take(11)) {
          expect(readerFile.allText, isNot(contains(m.code)), reason: m.name);
          expect(readerFile.allText, contains(m.name));
        }
      } finally {
        await cashiersSeeReports(null);
      }
    });
  });
}
