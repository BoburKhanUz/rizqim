/// P6 stage B "Internet uzildi" against the real stack: a restaurant (its own store in the seed, `lan`) with the till as
/// the branch's local bridge hub (a real LanHub over the till's PosOffline), two waiters' phones, the kitchen display and
/// the customer display as LAN clients on localhost. Every device reaches the "cloud" (Laravel and Reverb) through the
/// test's own link, which the test cuts in the middle of the service: the hall keeps working over the LAN. Seven hall
/// orders on two waiters (one opened online before the cut and continued over the LAN) and a takeaway at the till; the
/// kitchen bumps, the waiters serve, the customer display shows the till's cart and "tayyor"; the waiters ask for the
/// bills and each prints by itself on the till's (emulated) receipt printer, once; the money is taken at the till only.
/// Spoofing fails: a wrong device key, someone else's device, a device the branch does not have, another phone's grant,
/// a forged grant, an operation without a grant. Then the internet returns: the hub's journal drains to POST /pos/sync
/// in order, the LAN operations with their origin device and the staff member signed in there; the server shows every
/// order on the right device and waiter, the bills paid with their receipts, nothing in "Sinxronlash xatolari", no
/// second print of a bill; the devices go back to the cloud only when the hub says its queue reached the server; X and Z
/// add up, the drawer is right. At the end: the latencies (waiter send → KDS ticket, bump → waiter, bill request →
/// printed pre-check), all over the LAN.
///
/// The cashier app's own glue (publishing `cart.display` and `queue.ready`) is a Flutter app this pure-Dart test cannot
/// load: the test plays it with the same pieces. The bill print over the LAN runs the app's own logic (pos_lan
/// LanBillPrints: when the hub prints, the preview bill, the on-disk record; review P6 C9); only the paper is the test's
/// (pos_data BillPrintRequest and its dedupe, pos_core's pre-check layout, pos_hardware's ESC/POS emulator).
@Timeout(Duration(minutes: 6))
library;

import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_api/rizqim_api.dart' show AuthScope;
import 'package:test/test.dart';

import 'support/lan.dart';
import 'support/menu.dart';
import 'support/stack.dart';

Map<String, Object?> asMap(Object? value) =>
    (value! as Map<Object?, Object?>).cast<String, Object?>();

List<Map<String, Object?>> asList(Object? value) => <Map<String, Object?>>[
  for (final Object? e in (value as List<Object?>?) ?? const <Object?>[])
    asMap(e),
];

final Random _random = Random.secure();

/// A device-made order id (the phones make their own, as the apps do).
String uuid4() {
  final List<int> b = List<int>.generate(16, (_) => _random.nextInt(256));
  b[6] = (b[6] & 0x0f) | 0x40;
  b[8] = (b[8] & 0x3f) | 0x80;
  final String h = b.map((int x) => x.toRadixString(16).padLeft(2, '0')).join();
  return '${h.substring(0, 8)}-${h.substring(8, 12)}-${h.substring(12, 16)}-'
      '${h.substring(16, 20)}-${h.substring(20)}';
}

/// Banknotes for an amount (whole 1000 so'm here), largest first.
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

/// A device in the hall on the LAN: its terminal (paired, signed in online before the cut) and its bridge client.
class HallDevice {
  HallDevice(this.label, this.terminal, this.credentials, this.app)
    : client = LanClient(credentials: credentials, app: app) {
    // Listening from the start: nothing the hub says is missed
    inbox = LanInbox(client.events);
  }

  final String label;
  final Terminal terminal;
  final LanCredentials credentials;
  final LanApp app;
  final LanClient client;
  late final LanInbox inbox;
  LanMode mode = LanMode.cloud;

  /// Who the hub takes this device's operations from.
  Staff? get staff => terminal.staff;

  /// [LanModeRule] with what the device knows now.
  LanMode evaluate({required bool cloudReachable}) => mode = LanModeRule.next(
    current: mode,
    cloudReachable: cloudReachable,
    hubReachable: client.connected,
    hubDrained: client.hubDrained,
  );

  /// The phone's own server request (the floor, as the waiter app polls it): does the cloud answer?
  Future<bool> cloudAnswers() async {
    try {
      await terminal.client.dio.get<Object?>('/v1/pos/floor');
      return true;
    } on DioException catch (e) {
      return e.response != null;
    }
  }

  Future<LanOpResult> op(
    String type,
    Map<String, Object?> payload, {
    String? opId,
  }) => client.send(type, payload, opId: opId);

  /// The operation, which the hub must take.
  Future<LanOpResult> ok(String type, Map<String, Object?> payload) async {
    final LanOpResult r = await op(type, payload);
    expect(
      r.accepted,
      isTrue,
      reason: '$label $type: ${r.reason} ${r.message}',
    );
    return r;
  }
}

/// One table of the evening.
class HallTable {
  HallTable(this.name, this.dishes, {required this.waiter, this.guests = 2});

  final String name;
  final List<Dish> dishes;
  final HallDevice waiter;
  final int guests;
  late int tableId;
  late String uuid;

  /// The till's view of the order (server JSON shape) as the hub last answered it.
  Map<String, Object?> view = const <String, Object?>{};
  PosBill? bill;
  late List<PosTenderInput> tenders;

  List<Map<String, Object?>> get items => asList(view['items']);
}

void main() {
  late StackConfig stack;
  late StackConfig config;
  late Seed seed;
  late Dio owner;
  late CloudLink api;
  late CloudLink reverb;
  late ReceiptPrinterSim printer;
  late SyncStore tillStore;
  late Terminal till;
  late Terminal display;
  late HallDevice waiter1;
  late HallDevice waiter2;
  late HallDevice kds;
  late HallDevice screen;
  late Menu menu;
  late PosFloor floor;
  late PosShift shift;
  late Set<int> kdsStations;
  late PosOfflineLanApplier applier;
  late LanHub hub;
  late int beaconPort;
  late String tillUuid;

  final Map<String, HallTable> tables = <String, HallTable>{};
  String takeaway = ''; // the till's own takeaway order
  late PosMoney takeawayTotal;
  final List<String> lanOps =
      <String>[]; // the LAN operations the hub took, in order
  final List<Map<String, Object?>> syncSent =
      <Map<String, Object?>>[]; // POST /pos/sync operations, as sent
  final Map<String, Map<String, Object?>> syncResults =
      <String, Map<String, Object?>>{};
  final List<Duration> ticketLatency = <Duration>[];
  final List<Duration> readyLatency = <Duration>[];
  final List<Duration> printLatency = <Duration>[];
  final Map<String, DateTime> billAskedAt = <String, DateTime>{};
  final List<({String order, String requestId, String text})> prints =
      <({String order, String requestId, String text})>[];
  final BillPrintRequests seenRequests = BillPrintRequests();
  final Map<String, PosBill> previews =
      <String, PosBill>{}; // the pre-check printed per order
  final List<StreamSubscription<Object?>> subs =
      <StreamSubscription<Object?>>[];
  const PosMoney openingCash = PosMoney.som(300000);
  PosMoney cashIn = PosMoney.zero;
  PosMoney cardIn = PosMoney.zero;
  int bills = 0;

  Map<String, Object?> line(String dish, Object qty) => <String, Object?>{
    'variant_id': menu.variant(dish).uuid,
    'quantity': '$qty',
  };

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

  /// The branch's receipt printer on the LAN (the till is the printing till: the bills are its to print).
  PosPrinterInfo receiptPrinter() => till.bootstrap.printers.firstWhere(
    (PosPrinterInfo p) =>
        p.isReceipt &&
        p.id == (asMap(seed.json['receipt_printer'])['id']! as num).toInt(),
  );

  // --- the cashier app's glue on the hub (see the library comment) -------------------------------------------------

  /// The hub's automatic bill print is pos_lan's LanBillPrints, the same object the cashier app runs (review P6 C9):
  /// it decides (offline, auto_print_bill on, a receipt printer at hand), prints the order's whole unbilled amount as
  /// one preview bill in the same step, and the request goes into the journal with `printed_on_lan: true` only when it
  /// went to the printer. Only the paper is the test's: the till's emulated receipt printer ([printPreCheck]).
  late LanBillPrints lanBills;

  /// The paper of one pre-check (the cashier app's BillPrintService with its pre-check layout): once per request (pos_data
  /// BillPrintRequests, the request id is the op id), on the till's receipt printer.
  Future<bool> printPreCheck(
    BillPrintRequest request,
    PosBilling billing,
    PosOrder order,
  ) async {
    final String ref = request.orderUuid;
    if (!request.isFor(tillUuid) || !seenRequests.accept(request)) {
      return false;
    }
    final PosBill open = billing.bills.singleWhere(
      (PosBill b) => b.uuid == request.billUuid,
    );
    previews[ref] = open;
    final PreCheck check = PreCheck(
      header: const ReceiptHeader(storeName: 'Rizqim Lokal Restoran'),
      printedAt: DateTime.now(),
      tableLabel: order.table?.name,
      waiter: order.waiter?.name,
      guests: order.guestsCount,
      openedAt: order.openedAt?.toLocal(),
      orderCode: order.shortCode == null ? null : '#${order.shortCode}',
      items: <PreCheckItem>[
        for (final PosBillItem i in open.items)
          PreCheckItem(
            name: i.productName,
            quantity: ItemQuantity(i.quantity, unit: i.unit),
            unitPrice: menu.variant(i.productName).price,
            amount: i.amount,
          ),
      ],
      charges: <BillCharge>[
        if (!open.serviceChargeAmount.isZero)
          BillCharge(BillChargeKind.serviceCharge, open.serviceChargeAmount),
      ],
    );
    final PosPrinterInfo info = receiptPrinter();
    final String text = await printer.send(
      'Schyot ${order.table?.name ?? order.shortCode}',
      layoutPreCheck(
        check,
        info.paperWidth == 58 ? PaperWidth.mm58 : PaperWidth.mm80,
      ),
      info.address!,
      info.port!,
    );
    prints.add((order: ref, requestId: request.requestId, text: text));
    final DateTime? asked = billAskedAt[ref];
    if (asked != null) printLatency.add(DateTime.now().difference(asked));
    return true;
  }

  /// The till's journal: which operations came over the LAN (the bills print in [lanBills] as they are asked for).
  void watchTillJournal() {
    var last = till.pos.offline!.store.lastSeq;
    subs.add(
      till.pos.offline!.store.changes.listen((_) {
        final List<SyncOperation> fresh = till.pos.offline!.store
            .operationsAfter(last);
        if (fresh.isNotEmpty) last = fresh.last.seq;
        for (final SyncOperation op in fresh) {
          if (op.origin != null) lanOps.add(op.opId);
        }
      }),
    );
    // The takeaway's number on the customer display once its last dish is ready (the till publishes queue.ready)
    subs.add(
      applier.events.listen((LanEvent e) {
        if (e.type != LanMsg.kitchenItemStatus ||
            e.payload['order'] != takeaway) {
          return;
        }
        final PosOrder o = till.pos.offline!.order(takeaway);
        final bool allReady = o.items.every(
          (PosOrderItem i) =>
              !kdsStations.contains(i.stationId) ||
              i.kitchenStatus == PosKitchenStatus.ready,
        );
        if (allReady) {
          hub.publish(LanMsg.queueReady, <String, Object?>{
            'order': takeaway,
            'number': null,
            'short_code': o.shortCode,
          });
        }
      }),
    );
  }

  // The waiter's phone hears the line ready (kitchen.item_status) — measured asynchronously
  final List<Future<void>> readyWaits = <Future<void>>[];
  void Function(String, Object, DateTime) latencyOfReady = (_, _, _) {};

  /// The cook bumps every line of [order] at a station with a display that is still in the kitchen.
  Future<void> cookAll(String order) async {
    // The KDS works from what the hub shows it (a reconnected KDS reads the whole state)
    final Map<String, Object?> state = await kds.client.state();
    final Map<String, Object?> o = asList(state['open_orders']).firstWhere(
      (Map<String, Object?> x) => x['uuid'] == order,
      orElse: () => asMap(till.pos.offline!.book.order(order)),
    );
    final Map<String, Object?> full = o.containsKey('items')
        ? o
        : asMap(till.pos.offline!.book.order(order));
    for (final Map<String, Object?> l in asList(full['items'])) {
      if (l['kitchen_status'] == 'sent' &&
          kdsStations.contains(l['station_id'])) {
        final DateTime at = DateTime.now();
        final LanOpResult r = await kds.ok(
          LanOpType.kitchenBump,
          <String, Object?>{'order': order, 'line': l['id']},
        );
        expect(r.order, isNotNull);
        latencyOfReady(order, l['id']!, at);
      }
    }
  }

  setUpAll(() async {
    stack = StackConfig.fromEnvironment();
    seed = stack.seed.section('lan');
    api = await CloudLink.start(Uri.parse(stack.apiUrl));
    reverb = await CloudLink.start(Uri.parse(stack.reverbUrl));
    config = stack
        .withSeed(seed)
        .through(
          apiUrl:
              'http://127.0.0.1:${api.localPort}${Uri.parse(stack.apiUrl).path}',
          reverbUrl: 'ws://127.0.0.1:${reverb.localPort}',
        );
    // The owner works from home: the partner web is not behind the restaurant's link
    owner = Dio(
      BaseOptions(
        baseUrl: stack.apiUrl,
        headers: <String, Object?>{
          'Authorization': 'Bearer ${seed.ownerToken}',
          'Accept': 'application/json',
        },
      ),
    );
    tillUuid = asMap(seed.terminal('cashier')['device'])['uuid']! as String;

    // The till's receipt printer is the emulator; the owner picks the till as the LAN hub
    printer = await ReceiptPrinterSim.start();
    final int printerId = (asMap(seed.json['receipt_printer'])['id']! as num)
        .toInt();
    await owner.patch<Object?>(
      '/v1/owner/printers/$printerId',
      data: <String, Object?>{'address': '127.0.0.1', 'port': printer.port},
    );
    final Map<String, Object?> lanView = await ownerCall(
      'PUT',
      '/v1/owner/pos/lan?branch_id=${seed.branchId}',
      <String, Object?>{'hub_device_id': tillUuid},
    );
    expect(asMap(lanView['hub'])['device'], tillUuid);
    expect(asMap(lanView['hub'])['chosen'], isTrue);

    tillStore = await SyncStore.open(MemorySyncStorage());
    till = await Terminal.open(config, 'cashier', offlineStore: tillStore);
    // What the till sends to POST /pos/sync and what the server answers, operation by operation
    till.client.dio.interceptors.add(
      InterceptorsWrapper(
        onResponse: (Response<Object?> r, ResponseInterceptorHandler h) {
          if (r.requestOptions.method == 'POST' &&
              r.requestOptions.path.endsWith('/pos/sync') &&
              r.data is Map) {
            // Only batches the server answered (a batch tried while the link was cut never arrived)
            syncSent.addAll(asList(asMap(r.requestOptions.data)['operations']));
            for (final Map<String, Object?> res in asList(
              asMap(asMap(r.data)['data'])['results'],
            )) {
              syncResults[res['op_id']! as String] = res;
            }
          }
          h.next(r);
        },
      ),
    );
    await till.signIn('cashier');
    final Terminal w1 = await Terminal.open(config, 'waiter1');
    final Terminal w2 = await Terminal.open(config, 'waiter2');
    final Terminal k = await Terminal.open(config, 'kds');
    await w1.signIn('waiter1');
    await w2.signIn('waiter2');
    await k.signIn('cook');
    display = await Terminal.open(config, 'display');
    // The customer display has no staff session: its LAN keys with its own token
    final PosResponse lan = await display.pos.http.raw(
      'GET',
      '/v1/pos/lan',
      scope: AuthScope.terminal,
      idempotent: false,
    );
    final LanCredentials displayLan = LanCredentials.fromJson(asMap(lan.data))!;

    waiter1 = HallDevice(
      'waiter1',
      w1,
      LanCredentials.fromBootstrap(w1.bootstrap.raw)!,
      LanApp.waiter,
    );
    waiter2 = HallDevice(
      'waiter2',
      w2,
      LanCredentials.fromBootstrap(w2.bootstrap.raw)!,
      LanApp.waiter,
    );
    kds = HallDevice(
      'kds',
      k,
      LanCredentials.fromBootstrap(k.bootstrap.raw)!,
      LanApp.kds,
    );
    screen = HallDevice('display', display, displayLan, LanApp.display);

    await till.listen();
    menu = Menu(
      await till.pos.catalog.load(
        PosChannel.hall,
        menuVersion: till.bootstrap.menuVersion,
      ),
    );
    await till.pos.catalog.load(
      PosChannel.takeaway,
      menuVersion: till.bootstrap.menuVersion,
    );
    floor = await till.pos.floor.load();
    kdsStations = <int>{
      for (final PosStation s in till.bootstrap.stations)
        if (s.showOnKds) s.id,
    };
    beaconPort = await freeUdpPort();

    latencyOfReady = (String order, Object lineId, DateTime bumpedAt) {
      final HallTable? t = tables.values
          .where((HallTable t) => t.uuid == order)
          .firstOrNull;
      if (t == null) return;
      readyWaits.add(
        t.waiter.inbox
            .wait(
              LanMsg.kitchenItemStatus,
              since: bumpedAt,
              where: (LanEvent e) =>
                  e.payload['order'] == order &&
                  e.payload['line'] == lineId &&
                  e.payload['status'] == 'ready',
              what: '${t.name}: line $lineId ready on ${t.waiter.label}',
            )
            .then((HeardLan h) => readyLatency.add(h.at.difference(bumpedAt))),
      );
    };
  });

  tearDownAll(() async {
    Future<void> quietly(Future<void> Function() step) async {
      try {
        await step();
      } on Object catch (e) {
        printOnFailure('teardown: $e');
      }
    }

    for (final StreamSubscription<Object?> s in subs) {
      await s.cancel();
    }
    for (final HallDevice Function() d in <HallDevice Function()>[
      () => waiter1,
      () => waiter2,
      () => kds,
      () => screen,
    ]) {
      await quietly(() async {
        await d().inbox.dispose();
        await d().client.dispose();
      });
    }
    await quietly(() => hub.dispose());
    await quietly(() => applier.dispose());
    await quietly(() async => lanBills.dispose());
    for (final Terminal Function() t in <Terminal Function()>[
      () => till,
      () => waiter1.terminal,
      () => waiter2.terminal,
      () => kds.terminal,
      () => display,
    ]) {
      await quietly(() => t().close());
    }
    await quietly(() => printer.stop());
    await quietly(() => api.close());
    await quietly(() => reverb.close());
    owner.close();
    stdout.writeln(
      '\n[pos-e2e] internet uzildi: ${tables.length} hall orders + 1 takeaway over the LAN, ${prints.length} bills printed, '
      '${syncSent.length} operations synced (${syncSent.where((Map<String, Object?> o) => o['origin_device'] != null).length} relayed); '
      'waiter send → KDS ticket ${latencyStat(ticketLatency)}; bump → waiter "tayyor" ${latencyStat(readyLatency)}; '
      'bill request → pre-check printed ${latencyStat(printLatency)}',
    );
  });

  group('Internet uzildi', () {
    test('the owner\'s hub is the till: every device has its LAN keys, finds the hub and joins it; service starts online', () async {
      final LanCredentials hubLan = LanCredentials.fromBootstrap(
        till.bootstrap.raw,
      )!;
      expect(hubLan.isHub, isTrue);
      expect(hubLan.device, tillUuid);
      expect(hubLan.devices.map((LanDeviceInfo d) => d.uuid).toSet(), <String>{
        // The branch's terminals, the hub's own row included (it never lets itself in: LanCredentials.member)
        for (final String key in <String>[
          'cashier',
          'waiter1',
          'waiter2',
          'kds',
          'display',
        ])
          (asMap(seed.terminal(key)['device'])['uuid']! as String)
              .toLowerCase(),
      }, reason: 'the hub lets in the branch\'s terminals');
      for (final HallDevice d in <HallDevice>[waiter1, waiter2, kds, screen]) {
        expect(d.credentials.isHub, isFalse, reason: d.label);
        expect(d.credentials.hubSecret, isNull, reason: d.label);
        expect(d.credentials.hubDevice, tillUuid, reason: d.label);
      }
      expect(waiter1.credentials.grant, isNotNull);
      expect(kds.credentials.grant, isNotNull);
      expect(
        screen.credentials.grant,
        isNull,
        reason: 'the display has no staff session',
      );

      lanBills = LanBillPrints(
        offline: till.pos.offline!,
        print: printPreCheck,
        canPrint: (int? id) => receiptPrinter().address != null,
        autoPrint: () => till.bootstrap.branch.settings.autoPrintBill,
        printerId: () => receiptPrinter().id,
      );
      applier = PosOfflineLanApplier(
        offline: till.pos.offline!,
        printsBill: lanBills.decide,
      );
      hub = LanHub(
        credentials: hubLan,
        applier: applier,
        address: InternetAddress.loopbackIPv4,
        port: 0,
        advertisedHost: '127.0.0.1',
        beaconEvery: const Duration(milliseconds: 300),
        beaconTarget: InternetAddress.loopbackIPv4,
        beaconPort: beaconPort,
      );
      await hub.start();
      watchTillJournal();

      // Waiter 1's phone hears the UDP beacon; the others are given the address (the IP/QR fallback)
      final LanBeacon? heard = await LanDiscovery.listen(
        waiter1.credentials,
        port: beaconPort,
      );
      expect(heard, isNotNull, reason: 'the beacon of this branch\'s hub');
      expect(heard!.hubDevice, tillUuid);
      final LanEndpoint at = LanEndpoint(heard.host, hub.port);
      final LanEndpoint typed = LanEndpoint.parse(
        'rizqim-lan://127.0.0.1:${hub.port}?b=${seed.branchId}&h=$tillUuid',
      )!;
      for (final HallDevice d in <HallDevice>[waiter2, kds, screen]) {
        final LanBeacon? b = await LanDiscovery.fetch(typed, d.credentials);
        expect(b?.hubDevice, tillUuid, reason: d.label);
      }
      final Map<String, LanWelcome> welcomes = <String, LanWelcome>{
        'waiter1': await waiter1.client.connect(at),
        'waiter2': await waiter2.client.connect(typed),
        'kds': await kds.client.connect(typed),
        'display': await screen.client.connect(typed),
      };
      expect(welcomes['waiter1']!.staff!['id'], seed.staff('waiter1').id);
      expect(welcomes['waiter2']!.staff!['id'], seed.staff('waiter2').id);
      expect(welcomes['kds']!.staff!['id'], seed.staff('cook').id);
      expect(welcomes['display']!.staff, isNull);
      for (final LanWelcome w in welcomes.values) {
        expect(w.grantError, isNull);
        expect(w.hubName, 'Kassa');
      }
      expect(hub.peers, hasLength(4));
      expect(waiter1.client.lanState['cloud'], isTrue);

      // The shift opens online; table 1 is opened on waiter 1's phone online and goes to the kitchen
      shift = await till.pos.shifts.open(
        openingCash: openingCash,
        note: 'Internet uzildi mashqi',
      );
      till.pos.session.updateShift(shift.toSummary());
      final HallTable first = tables['Stol 1'] = HallTable(
        'Stol 1',
        <Dish>[Dish.of("Lag'mon", 2), Dish.of('Choy', 1)],
        waiter: waiter1,
        guests: 2,
      );
      first.tableId = floor.tables
          .firstWhere((FloorTable t) => t.name == 'Stol 1')
          .id;
      final PosOrder a = await waiter1.terminal.pos.orders.open(
        channel: PosChannel.hall,
        tableId: first.tableId,
        guestsCount: first.guests,
        items: menu.inputs(first.dishes),
        send: true,
      );
      first.uuid = a.uuid;
      // The till has it (the hub serves it over the LAN after the cut)
      await till.pos.orders.openOrders();
      await till.pos.orders.get(a.uuid);
      await till.pos.floor.load();
      expect(till.pos.offline!.book.order(a.uuid), isNotNull);
      expect(waiter1.evaluate(cloudReachable: true), LanMode.cloud);
    });

    test('the internet goes: the till and the phones lose the cloud and the hall switches to the LAN', () async {
      api.cut = true;
      reverb.cut = true;
      // The till finds out with its next request (it keeps working from its journal)
      final PosFloor offlineFloor = await till.pos.floor.load();
      expect(till.pos.offline!.active, isTrue);
      expect(offlineFloor.table(tables['Stol 1']!.tableId)?.orders, isNotEmpty);
      await waiter1.inbox.wait(
        LanMsg.lanState,
        where: (LanEvent e) => e.payload['cloud'] == false,
        what: 'lan.state cloud: false',
      );
      for (final HallDevice d in <HallDevice>[waiter1, waiter2, kds, screen]) {
        expect(
          await d.cloudAnswers(),
          isFalse,
          reason: '${d.label}: the cloud is out',
        );
        expect(d.evaluate(cloudReachable: false), LanMode.lan, reason: d.label);
      }
      expect(api.refused, greaterThan(0));

      // HallTable 1 goes on over the LAN: the kitchen sees what it has, the waiter adds skewers
      final HallTable first = tables['Stol 1']!;
      final Map<String, Object?> state = await waiter1.client.state();
      expect(
        asList(state['open_orders']).map((Map<String, Object?> o) => o['uuid']),
        contains(first.uuid),
      );
      final DateTime at = DateTime.now();
      final LanOpResult round = await waiter1.ok(
        LanOpType.orderRound,
        <String, Object?>{
          'order': first.uuid,
          'items': <Object?>[line("Qo'y kabob", 4)],
          'send': true,
        },
      );
      first.view = round.order!;
      final HeardLan ticket = await kds.inbox.wait(
        LanMsg.kitchenTicket,
        since: at,
        where: (LanEvent e) => e.payload['order'] == first.uuid,
      );
      ticketLatency.add(ticket.at.difference(at));
      expect(asList(ticket.payload['lines']).single['name'], "Qo'y kabob");
      expect(asList(ticket.payload['lines']).single['quantity'], '4.000');
    });

    test('the hall keeps working: six more tables on two waiters, the kitchen bumps, the waiters serve', () async {
      final Map<String, (HallDevice, List<Dish>, int)> plan =
          <String, (HallDevice, List<Dish>, int)>{
            'Stol 2': (
              waiter1,
              <Dish>[Dish.of('Manti', 2), Dish.of('Limonad', 1)],
              2,
            ),
            'Stol 3': (
              waiter1,
              <Dish>[
                Dish.of("Lag'mon", 1),
                Dish.of('Tovuq kabob', 3),
                Dish.of('Choy', 1),
              ],
              3,
            ),
            'Stol 4': (
              waiter1,
              <Dish>[Dish.of("Qo'y kabob", 6), Dish.of('Choy', 2)],
              4,
            ),
            'Stol 5': (
              waiter2,
              <Dish>[Dish.of("Lag'mon", 3), Dish.of('Limonad', 2)],
              3,
            ),
            'Stol 6': (
              waiter2,
              <Dish>[Dish.of('Manti', 1), Dish.of('Tovuq kabob', 2)],
              1,
            ),
            'Stol 7': (
              waiter2,
              <Dish>[
                Dish.of("Qo'y kabob", 2),
                Dish.of('Manti', 2),
                Dish.of('Choy', 1),
              ],
              2,
            ),
          };
      // The two waiters take their tables at the same time
      await Future.wait(<Future<void>>[
        for (final HallDevice w in <HallDevice>[waiter1, waiter2])
          () async {
            for (final MapEntry<String, (HallDevice, List<Dish>, int)> e
                in plan.entries) {
              if (e.value.$1 != w) continue;
              final HallTable t = tables[e.key] = HallTable(
                e.key,
                e.value.$2,
                waiter: w,
                guests: e.value.$3,
              );
              t.tableId = floor.tables
                  .firstWhere((FloorTable x) => x.name == e.key)
                  .id;
              t.uuid = uuid4();
              final DateTime at = DateTime.now();
              final Future<HeardLan> ticket = kds.inbox.wait(
                LanMsg.kitchenTicket,
                where: (LanEvent ev) => ev.payload['order'] == t.uuid,
                what: '${t.name}: kitchen ticket',
              );
              final LanOpResult opened = await w.ok(
                LanOpType.orderOpen,
                <String, Object?>{
                  'order': t.uuid,
                  'channel': 'pos_hall',
                  'table_id': t.tableId,
                  'guests_count': t.guests,
                  'items': <Object?>[
                    for (final Dish d in t.dishes)
                      line(d.name, d.quantity.toApiString()),
                  ],
                  'send': true,
                },
              );
              t.view = opened.order!;
              expect(
                asMap(t.view['waiter'])['id'],
                w.staff!.id,
                reason: '${t.name} is ${w.label}\'s',
              );
              final HeardLan heard = await ticket;
              ticketLatency.add(heard.at.difference(at));
              expect(heard.payload['table_ref'], isNotNull);
            }
          }(),
      ]);
      expect(tables, hasLength(7));
      // Every table is on the till's floor (its own journal)
      final PosFloor f = till.pos.offline!.floor();
      for (final HallTable t in tables.values) {
        expect(f.table(t.tableId)?.orders, isNotEmpty, reason: t.name);
      }
      // The other waiter sees the change too
      await waiter2.inbox.wait(
        LanMsg.orderChanged,
        where: (LanEvent e) =>
            asMap(e.payload['order'])['uuid'] == tables['Stol 4']!.uuid,
        what: 'waiter2 hears table 4',
      );

      // The waiter rule holds offline: waiter 2 may not add to waiter 1's table (no manager can approve)
      final LanOpResult other = await waiter2.op(
        LanOpType.orderRound,
        <String, Object?>{
          'order': tables['Stol 2']!.uuid,
          'items': <Object?>[line('Choy', 1)],
        },
      );
      expect(other.reason, 'approval_required');
      // The cook opens no orders; the money stays at the till
      expect(
        (await kds.op(LanOpType.orderOpen, <String, Object?>{
          'order': uuid4(),
          'channel': 'pos_takeaway',
          'items': <Object?>[line('Choy', 1)],
        })).reason,
        'permission_denied',
      );
      for (final String type in <String>[
        'bill.pay',
        'bill.create',
        'order.close',
        'order.discount',
      ]) {
        expect(
          (await waiter1.op(type, <String, Object?>{
            'order': tables['Stol 2']!.uuid,
          })).reason,
          'not_allowed_on_lan',
          reason: type,
        );
      }

      // The till's own takeaway: the kitchen gets it over the LAN, the customer display shows the till's cart
      final PosOrder ta = await till.pos.orders.open(
        channel: PosChannel.takeaway,
        items: menu.inputs(<Dish>[Dish.of("Lag'mon", 1), Dish.of('Choy', 1)]),
        send: true,
      );
      takeaway = ta.uuid;
      takeawayTotal = ta.totalPrice;
      expect(
        takeawayTotal,
        menu.total(<Dish>[Dish.of("Lag'mon", 1), Dish.of('Choy', 1)]),
      );
      await kds.inbox.wait(
        LanMsg.kitchenTicket,
        where: (LanEvent e) => e.payload['order'] == takeaway,
        what: 'the takeaway ticket',
      );
      hub.publish(
        LanMsg.cartDisplay,
        PosDisplayCart(
          state: PosDisplayScreen.payment,
          lines: <PosDisplayLine>[
            for (final PosOrderItem i in ta.items)
              PosDisplayLine(
                name: i.productName,
                quantity: i.quantity,
                price: i.unitPrice,
                total: i.totalPrice,
              ),
          ],
          subtotal: ta.totalPrice,
          total: ta.totalPrice,
          seq: 1,
          terminal: tillUuid,
        ).toJson(),
      );
      final HeardLan cart = await screen.inbox.wait(
        LanMsg.cartDisplay,
        what: 'the cart on the display',
      );
      final PosDisplayCart shown = PosDisplayCart.fromJson(
        PosJson(cart.payload),
      );
      expect(shown.total, takeawayTotal);
      expect(shown.lines.map((PosDisplayLine l) => l.name), <String>[
        "Lag'mon",
        'Choy',
      ]);

      // The cook bumps everything at the hot and grill stations; each waiter hears their lines ready
      for (final HallTable t in tables.values) {
        await cookAll(t.uuid);
      }
      await cookAll(takeaway);
      await Future.wait(readyWaits);
      expect(readyLatency.length, greaterThanOrEqualTo(tables.length));
      // The display sees the takeaway "tayyor" (the till's queue.ready); review P6 S7: without a staff grant it gets
      // the cart and the queue only, not the kitchen's lines
      final HeardLan ready = await screen.inbox.wait(
        LanMsg.queueReady,
        where: (LanEvent e) => e.payload['order'] == takeaway,
      );
      expect(ready.payload['short_code'], ta.shortCode);
      expect(screen.inbox.of(LanMsg.kitchenItemStatus), isEmpty);
      expect(screen.inbox.of(LanMsg.kitchenTicket), isEmpty);
      // Bumped already: nothing more is queued
      final Map<String, Object?> bumped = asList(
        asMap(till.pos.offline!.book.order(tables['Stol 2']!.uuid))['items'],
      ).firstWhere((Map<String, Object?> l) => l['kitchen_status'] == 'ready');
      expect(
        (await kds.op(LanOpType.kitchenBump, <String, Object?>{
          'order': tables['Stol 2']!.uuid,
          'line': bumped['id'],
        })).reason,
        'kitchen_status',
      );

      // The waiters serve what is ready and the bar's drinks (no display at the bar)
      for (final HallTable t in tables.values) {
        final Map<String, Object?> now = asMap(
          till.pos.offline!.book.order(t.uuid),
        );
        for (final Map<String, Object?> l in asList(now['items'])) {
          final bool atDisplay = kdsStations.contains(l['station_id']);
          if (l['kitchen_status'] == 'ready' ||
              (l['kitchen_status'] == 'sent' && !atDisplay)) {
            t.view = (await t.waiter.ok(
              LanOpType.orderServed,
              <String, Object?>{'order': t.uuid, 'line': l['id']},
            )).order!;
          }
        }
        for (final Map<String, Object?> l in t.items) {
          expect(
            l['kitchen_status'],
            'served',
            reason: '${t.name}: ${l['product_name']}',
          );
        }
      }
    });

    test('the waiters ask for the bills: each prints on the till\'s printer by itself, once', () async {
      for (final HallTable t in tables.values) {
        billAskedAt[t.uuid] = DateTime.now();
        final LanOpResult asked = await t.waiter.ok(
          LanOpType.billRequest,
          <String, Object?>{'order': t.uuid},
        );
        expect(asked.order!['bill_requested_at'], isNotNull, reason: t.name);
        // Asked again (the waiter tapped twice; the answer was lost and the phone sent the same op again): no new print
        final LanOpResult again = await t.waiter.op(
          LanOpType.billRequest,
          <String, Object?>{'order': t.uuid},
          opId: asked.opId,
        );
        expect(again.duplicate, isTrue);
        expect(
          (await t.waiter.op(LanOpType.billRequest, <String, Object?>{
            'order': t.uuid,
          })).accepted,
          isTrue,
        );
      }
      final DateTime until = DateTime.now().add(const Duration(seconds: 20));
      while (prints.length < tables.length && DateTime.now().isBefore(until)) {
        await Future<void>.delayed(const Duration(milliseconds: 50));
      }
      await Future<void>.delayed(const Duration(milliseconds: 500));
      expect(
        prints,
        hasLength(tables.length),
        reason: 'one pre-check per table, never twice',
      );
      expect(printer.emulator.prints, hasLength(tables.length));
      for (final HallTable t in tables.values) {
        final String text = prints.firstWhere((p) => p.order == t.uuid).text;
        // The request went into the journal marked: the server will not print it again
        final List<SyncOperation> requests = <SyncOperation>[
          for (final SyncOperation o in tillStore.pending)
            if (o.type == SyncOpType.billRequest && o.orderRef == t.uuid) o,
        ];
        expect(requests, hasLength(1), reason: '${t.name}: asked once');
        expect(requests.single.payload['printed_on_lan'], isTrue);
        expect(requests.single.origin, t.waiter.credentials.device);
        // The pre-check is a preview: no bill is queued, the cashier makes it when the guest pays
        expect(
          till.pos.offline!.billing(t.uuid).bills,
          isEmpty,
          reason: t.name,
        );
        t.bill = previews[t.uuid];
        expect(
          t.bill!.total,
          menu.total(<Dish>[
            ...t.dishes,
            if (t.name == 'Stol 1') Dish.of("Qo'y kabob", 4),
          ]),
          reason: t.name,
        );
        expect(text, contains('SCHYOT'));
        expect(text, contains(t.name));
        expect(text, contains('JAMI:'));
        expect(text, contains(t.waiter.staff!.name));
        expect(
          text,
          contains(paperAmount(t.bill!.total)),
          reason: '${t.name}:\n$text',
        );
      }
    });

    test('spoofing fails: a wrong key, someone else\'s device, a stranger, another phone\'s grant, a forged grant', () async {
      final int before = tillStore.pendingCount;
      final LanEndpoint at = LanEndpoint('127.0.0.1', hub.port);
      LanCredentials forged({
        String? device,
        List<int>? key,
        String? grant,
        bool keepGrant = true,
      }) => LanCredentials(
        branchId: waiter1.credentials.branchId,
        keyId: waiter1.credentials.keyId,
        device: device ?? waiter1.credentials.device,
        isHub: false,
        deviceKey: Uint8List.fromList(key ?? waiter1.credentials.deviceKey),
        beaconKey: waiter1.credentials.beaconKey,
        port: hub.port,
        hubDevice: waiter1.credentials.hubDevice,
        hubName: waiter1.credentials.hubName,
        grant: grant ?? (keepGrant ? waiter1.credentials.grant : null),
      );
      Future<String> refused(LanCredentials creds) async {
        final LanClient c = LanClient(
          credentials: creds,
          app: LanApp.waiter,
          timeout: const Duration(seconds: 3),
        );
        try {
          await c.connect(at);
          fail('${creds.device} was let in');
        } on LanUnreachable catch (e) {
          return e.reason;
        } finally {
          await c.dispose();
        }
      }

      // A phone that has the right uuid but not its key (a copied app, an old pairing)
      expect(
        await refused(forged(key: List<int>.generate(32, (int i) => i))),
        'bad_sig',
      );
      // Waiter 1's phone pretending to be waiter 2's (it has only its own key)
      expect(
        await refused(forged(device: waiter2.credentials.device)),
        'bad_sig',
      );
      // A device the branch does not have
      expect(await refused(forged(device: uuid4())), 'unknown_device');

      // Waiter 2's grant on waiter 1's phone: let in as a device, but nobody's operations
      final LanClient stolen = LanClient(
        credentials: forged(grant: waiter2.credentials.grant),
        app: LanApp.waiter,
      );
      try {
        final LanWelcome w = await stolen.connect(at);
        expect(w.grantError, 'grant_device');
        expect(w.staff, isNull);
        final LanOpResult r = await stolen.send(
          LanOpType.orderRound,
          <String, Object?>{
            'order': tables['Stol 5']!.uuid,
            'items': <Object?>[line('Choy', 1)],
          },
        );
        expect(r.reason, 'grant_required');
      } finally {
        await stolen.dispose();
      }
      // A forged grant: waiter 1 made a manager of himself (the signature no longer fits)
      final String grant = waiter1.credentials.grant!;
      final String payload = grant.split('.').first;
      final Map<String, Object?> claims = asMap(
        jsonDecode(utf8.decode(base64Url.decode(base64Url.normalize(payload)))),
      );
      claims['role'] = 'manager';
      claims['permissions'] = <String>[
        ...(claims['permissions']! as List<Object?>).cast<String>(),
        'pos.take_payment',
        'orders.cancel',
      ];
      final String fake =
          '${base64Url.encode(utf8.encode(jsonEncode(claims))).replaceAll('=', '')}.${grant.split('.').last}';
      // The real phone reconnects later with its own grant: drop it now, the hub keeps one connection per device
      final LanClient promoted = LanClient(
        credentials: forged(grant: fake),
        app: LanApp.waiter,
      );
      try {
        final LanWelcome w = await promoted.connect(at);
        expect(w.grantError, 'grant_invalid');
        expect(
          (await promoted.send(LanOpType.billRequest, <String, Object?>{
            'order': tables['Stol 5']!.uuid,
          })).reason,
          'grant_required',
        );
      } finally {
        await promoted.dispose();
      }
      // The display has no staff: it only listens
      expect(
        (await screen.op(LanOpType.kitchenBump, <String, Object?>{
          'order': takeaway,
          'line': 1,
        })).reason,
        'grant_required',
      );
      expect(
        tillStore.pendingCount,
        before,
        reason: 'nothing reached the till\'s journal',
      );

      // Waiter 1's real phone comes back (its connection gave way to the impostors on the same uuid)
      final DateTime gone = DateTime.now().add(const Duration(seconds: 3));
      while (waiter1.client.connected && DateTime.now().isBefore(gone)) {
        await Future<void>.delayed(const Duration(milliseconds: 20));
      }
      if (!waiter1.client.connected) await waiter1.client.connect(at);
      expect(waiter1.client.welcome!.staff!['id'], seed.staff('waiter1').id);
      expect(waiter2.client.connected, isTrue);
      expect(kds.client.connected, isTrue);
    });

    test('the money is taken at the till, offline: cash and card; the tables are free', () async {
      var n = 0;
      for (final HallTable t in tables.values) {
        // The guest pays what the pre-check said: the cashier makes the bill now
        final PosBilling made = await till.pos.billing.createBills(
          t.uuid,
          version: till.pos.offline!.order(t.uuid).version,
        );
        final PosBill bill = made.bills.single;
        expect(bill.total, t.bill!.total, reason: t.name);
        t.bill = bill;
        n++;
        t.tenders = n.isEven
            ? <PosTenderInput>[
                PosTenderInput.card(
                  bill.total,
                  cardType: 'uzcard',
                  transactionId: '6299000${n.toString().padLeft(5, '0')}',
                ),
              ]
            : <PosTenderInput>[
                PosTenderInput.cash(
                  bill.total,
                  tendered: bill.total + const PosMoney.som(2000),
                ),
              ];
        final PosBillResult paid = await till.pos.billing.pay(
          bill.uuid,
          tenders: t.tenders,
          shiftUuid: shift.uuid,
        );
        // Offline the money is taken and the table freed; the bill turns `paid` once its sale receipt is issued
        expect(paid.bill.isTaken, isTrue, reason: t.name);
        expect(paid.bill.dueAmount, PosMoney.zero, reason: t.name);
        expect(paid.order.isClosed, isTrue, reason: t.name);
        (n.isEven ? (PosMoney m) => cardIn += m : (PosMoney m) => cashIn += m)(
          bill.total,
        );
        bills++;
      }
      // The takeaway: a bill and cash at the counter
      final PosBilling tb = await till.pos.billing.createBills(
        takeaway,
        version: till.pos.offline!.order(takeaway).version,
      );
      final PosBill tBill = tb.bills.single;
      expect(tBill.total, takeawayTotal);
      await till.pos.billing.pay(
        tBill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(tBill.total)],
        shiftUuid: shift.uuid,
      );
      cashIn += tBill.total;
      bills++;
      final PosFloor f = till.pos.offline!.floor();
      for (final HallTable t in tables.values) {
        expect(
          f.table(t.tableId)?.orders ?? const <Object?>[],
          isEmpty,
          reason: '${t.name} is free',
        );
      }
      // The waiters' phones see the tables close over the LAN
      await waiter2.inbox.wait(
        LanMsg.orderChanged,
        where: (LanEvent e) =>
            asMap(e.payload['order'])['uuid'] == tables['Stol 7']!.uuid &&
            asMap(e.payload['order'])['status'] == 'delivered',
        what: 'table 7 closed on waiter 2\'s phone',
      );
      expect(waiter1.client.hubDrained, isFalse);
      expect(waiter1.evaluate(cloudReachable: false), LanMode.lan);
    });

    test('the internet returns: the journal drains in order, the server has every operation with its device and staff', () async {
      final int queued = tillStore.pendingCount;
      expect(queued, greaterThan(30));
      final List<String> queue = <String>[
        for (final SyncOperation o in tillStore.pending) o.opId,
      ];
      final DateTime back = DateTime.now();
      api.cut = false;
      reverb.cut = false;
      // A phone listens on the branch channel again: a bill printed on the LAN must not print a second time now
      await waiter1.terminal.realtime?.dispose();
      await waiter1.terminal.log?.dispose();
      final EventLog heard = await waiter1.terminal.listen();
      // The phones see the cloud again but stay on the LAN until the hub's queue is on the server
      expect(await waiter1.cloudAnswers(), isTrue);
      expect(
        waiter1.evaluate(cloudReachable: true),
        LanMode.lan,
        reason: 'the hub has not drained yet',
      );

      // The till's next request reaches the server: its journal goes by itself
      await till.pos.offline!.serverErrors();
      final DateTime until = DateTime.now().add(const Duration(seconds: 60));
      while (tillStore.pendingCount > 0 && DateTime.now().isBefore(until)) {
        await Future<void>.delayed(const Duration(milliseconds: 100));
      }
      expect(tillStore.pendingCount, 0, reason: 'the journal drained');
      for (final HallDevice d in <HallDevice>[waiter1, waiter2, kds, screen]) {
        await d.inbox.wait(
          LanMsg.lanState,
          where: (LanEvent e) => LanModeRule.drained(e.payload),
          // Not the drained state of the online start: the one after this outage
          since: back,
          timeout: const Duration(seconds: 20),
          what: '${d.label}: lan.state drained',
        );
        expect(d.client.hubDrained, isTrue, reason: d.label);
        expect(
          d.evaluate(cloudReachable: await d.cloudAnswers()),
          LanMode.cloud,
          reason: d.label,
        );
      }

      // In order, every one once, each applied
      expect(
        syncSent.map((Map<String, Object?> o) => o['op_id']).toList(),
        queue,
      );
      final List<int> seqs = <int>[
        for (final Map<String, Object?> o in syncSent)
          (o['seq']! as num).toInt(),
      ];
      expect(seqs, List<int>.of(seqs)..sort());
      expect(seqs.toSet(), hasLength(seqs.length));
      for (final Map<String, Object?> o in syncSent) {
        final Map<String, Object?> r = syncResults[o['op_id']]!;
        expect(
          r['status'],
          'applied',
          reason: '${o['type']} ${r['reason']} ${r['message']}',
        );
      }
      // The LAN operations, with the phone or the KDS they came from and the staff member signed in there
      final Map<String, (String, int)> by = <String, (String, int)>{
        for (final HallDevice d in <HallDevice>[waiter1, waiter2, kds])
          d.credentials.device: (d.label, d.staff!.id),
      };
      final List<String> relayed = <String>[
        for (final Map<String, Object?> o in syncSent)
          if (o['origin_device'] != null) o['op_id']! as String,
      ];
      expect(
        relayed,
        lanOps,
        reason: 'the LAN operations reach the server in the order the hub took them',
      );
      final Map<String, int> perDevice = <String, int>{};
      for (final Map<String, Object?> o in syncSent) {
        final String? origin = o['origin_device'] as String?;
        if (origin == null) {
          expect(
            o['staff_id'],
            seed.staff('cashier').id,
            reason: 'the till\'s own ${o['type']}',
          );
          expect(<String>[
            SyncOpType.billCreate,
            SyncOpType.billPay,
            SyncOpType.orderClose,
            SyncOpType.orderOpen,
          ], contains(o['type']));
          continue;
        }
        final (String label, int staffId) = by[origin]!;
        expect(o['staff_id'], staffId, reason: '$label ${o['type']}');
        perDevice[label] = (perDevice[label] ?? 0) + 1;
        if (label == 'kds') expect(o['type'], SyncOpType.kitchenBump);
        expect(
          o['type'],
          isNot(
            anyOf(
              SyncOpType.billPay,
              SyncOpType.billCreate,
              SyncOpType.orderClose,
            ),
          ),
        );
      }
      expect(perDevice.keys.toSet(), <String>{'waiter1', 'waiter2', 'kds'});

      // The server's orders: on the phone that took them, the waiter's, every line added by them, served, paid
      final Map<String, Object?> deviceIds = asMap(seed.json['device_ids']);
      final FiscalReceiptRepository receipts = FiscalReceiptRepository(
        till.pos.http,
      );
      for (final HallTable t in tables.values) {
        final String server = tillStore.resolve(t.uuid);
        final PosOrder o = await till.pos.orders.get(server);
        expect(o.status, 'delivered', reason: t.name);
        expect(o.waiter?.id, t.waiter.staff!.id, reason: t.name);
        expect(
          o.deviceId,
          (deviceIds[t.waiter.label]! as num).toInt(),
          reason: '${t.name}: the order is the phone\'s',
        );
        expect(o.billRequestedAt, isNotNull, reason: t.name);
        for (final PosOrderItem i in o.items) {
          expect(
            i.addedBy?.id,
            t.waiter.staff!.id,
            reason: '${t.name}: ${i.productName}',
          );
          expect(
            i.kitchenStatus,
            PosKitchenStatus.served,
            reason: '${t.name}: ${i.productName}',
          );
        }
        final PosBilling b = await till.pos.billing.bills(server);
        final List<PosBill> live = b.bills
            .where((PosBill x) => x.status != 'void')
            .toList();
        expect(live, hasLength(1), reason: '${t.name}: one bill');
        expect(live.single.isPaid, isTrue);
        expect(live.single.total, t.bill!.total, reason: t.name);
        final PosFiscalReceiptDetail receipt = await receipts.get(
          live.single.fiscal.receiptUuid!,
        );
        expect(
          receipt.total,
          live.single.total,
          reason: '${t.name}: the sale receipt',
        );
      }
      final PosOrder ta = await till.pos.orders.get(
        tillStore.resolve(takeaway),
      );
      expect(ta.status, 'delivered');
      expect(ta.deviceId, (deviceIds['cashier']! as num).toInt());

      // Nothing for the manager in "Sinxronlash xatolari"
      final PosSyncErrorsPage errors = await till.pos.offline!.serverErrors(
        all: true,
      );
      expect(
        errors.errors,
        isEmpty,
        reason: errors.errors
            .map((PosSyncError e) => '${e.type} ${e.reason}')
            .join(', '),
      );

      // The same operation sent again (a lost answer): applied once
      final Map<String, Object?> again = syncSent.firstWhere(
        (Map<String, Object?> o) => o['origin_device'] != null,
      );
      final PosResponse resend = await till.pos.http.raw(
        'POST',
        '/v1/pos/sync',
        body: <String, Object?>{
          'sent_at': DateTime.now().toUtc().toIso8601String(),
          'operations': <Object?>[again],
        },
        scope: AuthScope.terminal,
        idempotent: false,
      );
      final Map<String, Object?> dup = asList(
        asMap(resend.data)['results'], // PosHttp unwrapped the envelope
      ).single;
      expect(dup['duplicate'], isTrue);
      expect(dup['status'], 'applied');

      // No bill printed on the LAN comes out again from the server's automatic print
      await Future<void>.delayed(const Duration(seconds: 2));
      final Set<String> servers = <String>{
        for (final HallTable t in tables.values) tillStore.resolve(t.uuid),
      };
      expect(
        heard.where(
          (PosEvent e) =>
              e is PosBillPrintRequested &&
              servers.contains(e.request.orderUuid),
        ),
        isEmpty,
        reason: 'the hub printed these bills already',
      );
      expect(printer.emulator.prints, hasLength(tables.length));
    });

    test(
      'X and Z add up: every bill taken offline, the receipts, the drawer',
      () async {
        final PosShiftWithReport x = await till.pos.shifts.xReport(shift.uuid);
        final PosShiftReport report = x.report!;
        final Map<String, Object?> sales = asMap(report.raw['sales']);
        final PosMoney total = cashIn + cardIn;
        expect(sales['bills'], bills);
        expect(PosMoney.parse(sales['bills_total']), total);
        expect(PosMoney.parse(sales['received']), total);
        PosMoney byTender(String tender) => PosMoney.parse(
          asList(report.raw['by_tender']).firstWhere(
            (Map<String, Object?> r) => r['tender'] == tender,
            orElse: () => <String, Object?>{'amount': '0'},
          )['amount'],
        );
        expect(byTender('cash'), cashIn);
        expect(byTender('card'), cardIn);
        final PosMoney expected = openingCash + cashIn;
        expect(report.expectedCash, expected);
        final PosShift z = await till.pos.shifts.close(
          shift.uuid,
          countedBreakdown: banknotes(expected),
        );
        expect(z.status, 'closed');
        expect(z.difference, PosMoney.zero);
        till.pos.session.updateShift(null);
      },
    );
  });
}
