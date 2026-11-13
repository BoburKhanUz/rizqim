import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart' show PosMoney, ReceiptHeader;
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:pos_hardware/testing.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_cashier/src/lan/cashier_lan_hub.dart';
import 'package:rizqim_cashier/src/lan/lan_bill_prints.dart';
import 'package:rizqim_cashier/src/lan/lan_publisher.dart';
import 'package:rizqim_cashier/src/lan/lan_settings_card.dart';
import 'package:rizqim_cashier/src/lan/windows_firewall.dart';
import 'package:rizqim_cashier/src/printing/bill_print_service.dart';
import 'package:rizqim_cashier/src/printing/printing_service.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// P6 stage B on the till (the LAN hub): what the till does itself reaches the LAN devices (`cart.display`, `queue.*`,
/// the kitchen's events), a bill a waiter asks for over the LAN while the till is offline prints on the till's own
/// printer once (and is marked so the server does not print it again), and the settings show the hub. Real sockets on
/// localhost (plain tests: no widget binding overriding HttpClient).
void main() {
  const String hubUuid = FakePosState.deviceUuid;
  const String phone = 'bbbbbbbb-0000-4000-8000-000000000002';
  const String display = 'dddddddd-0000-4000-8000-000000000005';
  final Uint8List secret = Uint8List.fromList(
    List<int>.generate(32, (int i) => i * 5 + 1),
  );
  final DateTime now = DateTime.utc(2026, 10, 22, 13);
  const List<String> waiterPerms = <String>[
    'orders.view',
    'orders.manage',
    'tables.manage',
    'menu.view',
  ];

  Map<String, Object?> section({
    String role = 'hub',
    String device = hubUuid,
  }) => <String, Object?>{
    'v': 1,
    'branch_id': FakePosState.branchId,
    'key_id': 1,
    'role': role,
    'device': device,
    'device_key': base64.encode(LanKeys.device(secret, device)),
    'beacon_key': base64.encode(LanKeys.beacon(secret)),
    'hub': <String, Object?>{
      'device': hubUuid,
      'name': 'Kassa 1',
      'port': 18480,
    },
    'hub_secret': role == 'hub' ? base64.encode(secret) : null,
    'devices': role == 'hub'
        ? <Object?>[
            <String, Object?>{
              'uuid': phone,
              'type': 'pos',
              'name': 'Botir tel',
            },
            <String, Object?>{
              'uuid': display,
              'type': 'display',
              'name': 'Ekran',
            },
          ]
        : null,
    'grant': null,
    'clock_window_seconds': 120,
  };

  String grant(String device, int staffId, String name) {
    final DateTime iat = DateTime.now();
    return LanGrant.sign(<String, Object?>{
      'v': 1,
      'kid': 1,
      'branch_id': FakePosState.branchId,
      'device': device,
      'staff_id': staffId,
      'name': name,
      'role': 'waiter',
      'permissions': waiterPerms,
      'session': 1,
      'iat': iat.millisecondsSinceEpoch ~/ 1000,
      'exp': iat.add(const Duration(hours: 36)).millisecondsSinceEpoch ~/ 1000,
    }, LanKeys.grant(secret));
  }

  late FakePosAdapter adapter;
  late PosData pos;
  late RizqimClient client;
  late CashierPrinting printing;
  late BillPrintService prints;
  late LanBillPrints lanBills;
  late CashierLanHub hub;
  late LanPublisher publisher;
  final Map<String, MemoryPrinterTransport> printers =
      <String, MemoryPrinterTransport>{};
  final List<LanClient> clients = <LanClient>[];

  List<String> receipts() => <String>[
    for (final EscPosDocument d
        in (printers['server-1'] ?? MemoryPrinterTransport()).documents)
      d.text,
  ];

  Future<void> until(bool Function() ok, {String? reason}) async {
    for (var i = 0; i < 300 && !ok(); i++) {
      await Future<void>.delayed(const Duration(milliseconds: 10));
    }
    expect(ok(), isTrue, reason: reason);
  }

  setUp(() async {
    printers.clear();
    adapter = FakePosAdapter(clock: () => now);
    for (final Map<String, Object?> p in adapter.state.printers) {
      p['print_as_image'] = false;
    }
    adapter.state.bootstrapExtras['lan'] = section();
    FakeCashierBackend(adapter.state);
    FakeSyncBackend(adapter.state);
    adapter.state.openShift();
    client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: MemoryKeyValueStore(),
      dio: Dio()..httpClientAdapter = adapter,
      clock: () => now,
    );
    pos = PosData(
      client,
      clock: () => now,
      random: Random(3),
      offlineStore: await SyncStore.open(
        MemorySyncStorage(),
        clock: () => now,
        random: Random(5),
      ),
    );
    printing = CashierPrinting(
      android: false,
      printers: PosPrinters(
        queue: PrintQueue(
          retryPolicy: RetryPolicy.none,
          transportFor: (PrinterConfig c) =>
              printers[c.id] ??= MemoryPrinterTransport(),
        ),
      ),
    );
    prints = BillPrintService(
      data: pos,
      printing: printing,
      deviceUuid: () => pos.session.bootstrap?.device?.uuid,
      now: () => now,
      header: () => const ReceiptHeader(storeName: 'Choyxona'),
      printedOnLan: (String id) => lanBills.printed(id),
    );
    lanBills = tillLanBillPrints(
      offline: pos.offline!,
      prints: prints,
      now: () => now,
      autoPrint: () =>
          pos.session.bootstrap?.branch.settings.autoPrintBill ?? true,
      printerId: () => pos.session.bootstrap?.branch.settings.billPrinter,
      takeOverAfter: const Duration(milliseconds: 300),
    );
    hub = CashierLanHub(
      session: pos.session,
      auth: client.auth,
      offline: pos.offline,
      hubFactory: (LanCredentials c, LanOpApplier a) => LanHub(
        credentials: c,
        applier: a,
        address: InternetAddress.loopbackIPv4,
        port: 0,
        beaconEvery: null,
        advertisedHost: '127.0.0.1',
      ),
      printsBill: lanBills.decide,
    );
    publisher = LanPublisher(
      hub: hub,
      feed: pos.displayFeed,
      offline: pos.offline,
    );

    await client.auth.pairTerminal('ABCD-EFGH');
    await client.auth.signInStaff(userId: 3, pin: adapter.state.pins[3]!);
    await hub.start();
    final PosBootstrap boot = await pos.session.loadBootstrap();
    printing.configure(boot.printers);
    await pos.catalog.load(PosChannel.hall, menuVersion: boot.menuVersion);
    await pos.catalog.load(PosChannel.takeaway, menuVersion: boot.menuVersion);
    await pos.floor.load();
    await pos.orders.openOrders();
    await until(() => hub.running);
    publisher.start();
  });

  tearDown(() async {
    for (final LanClient c in clients) {
      await c.dispose();
    }
    clients.clear();
    publisher.dispose();
    lanBills.dispose();
    hub.dispose();
    await Future<void>.delayed(const Duration(milliseconds: 50));
    await pos.dispose();
  });

  Future<LanClient> connect(
    String device,
    LanApp app, {
    String? as,
    List<LanEvent>? into,
  }) async {
    final LanClient c = LanClient(
      credentials: LanCredentials.fromJson(<String, Object?>{
        ...section(role: 'client', device: device),
        'grant': as,
      })!,
      app: app,
    );
    clients.add(c);
    if (into != null) c.events.listen(into.add);
    await c.connect(LanEndpoint('127.0.0.1', hub.port!));
    return c;
  }

  Future<void> goOffline() async {
    adapter.offline = true;
    await pos.floor.load();
    expect(pos.offline!.connectivity.state.online, isFalse);
  }

  Map<String, Object?> line(int variant, String qty) => <String, Object?>{
    'variant_id': FakePosState.variantUuid(variant),
    'quantity': qty,
  };

  Future<String> waiterOrder(LanClient waiter, {int table = 1}) async {
    final String order = newUuid();
    final LanOpResult opened = await waiter.send(
      LanOpType.orderOpen,
      <String, Object?>{
        'order': order,
        'channel': 'pos_hall',
        'table_id': table,
        'guests_count': 2,
        'items': <Object?>[line(1, '2')],
        'send': true,
      },
    );
    expect(opened.accepted, isTrue, reason: opened.message);
    return order;
  }

  test('offline, a bill the waiter asks for over the LAN prints on the till once, marked for the server; '
      'nothing is billed in the queue', () async {
    await goOffline();
    final LanClient botir = await connect(
      phone,
      LanApp.waiter,
      as: grant(phone, 4, 'Botir Aliyev'),
    );
    final String order = await waiterOrder(botir);
    final int queued = pos.offline!.store.pendingCount;

    final LanOpResult asked = await botir.send(
      LanOpType.billRequest,
      <String, Object?>{'order': order},
    );
    expect(asked.accepted, isTrue, reason: asked.message);
    await until(() => receipts().isNotEmpty, reason: 'the pre-check prints');
    await lanBills.idle();
    expect(receipts(), hasLength(1));
    expect(receipts().single, contains('SCHYOT'));
    expect(receipts().single, contains('Bu fiskal chek emas'));
    expect(receipts().single, isNot(contains('NUSXA')));

    // The queued request says the hub printed it (the server does not print it again); no bill was queued
    final List<SyncOperation> ops = pos.offline!.store.operationsAfter(0);
    final SyncOperation request = ops.singleWhere(
      (SyncOperation o) => o.type == SyncOpType.billRequest,
    );
    expect(request.payload['printed_on_lan'], isTrue);
    expect(request.origin, phone);
    expect(pos.offline!.store.pendingCount, queued + 1);
    expect(
      ops.where((SyncOperation o) => o.type == SyncOpType.billCreate),
      isEmpty,
    );
    expect(pos.offline!.billing(order).bills, isEmpty);

    // Asked again (the answer was lost on the Wi-Fi, or the waiter tapped twice): no second paper
    expect(
      (await botir.send(LanOpType.billRequest, <String, Object?>{
        'order': order,
      })).accepted,
      isTrue,
    );
    await Future<void>.delayed(const Duration(milliseconds: 100));
    await lanBills.idle();
    expect(receipts(), hasLength(1));
  });

  test('review C6: the hub prints before it queues; a waiter retry after the till restarted prints nothing more', () async {
    await goOffline();
    final LanClient botir = await connect(
      phone,
      LanApp.waiter,
      as: grant(phone, 4, 'Botir Aliyev'),
    );
    final String order = await waiterOrder(botir);
    final String opId = newUuid();
    final LanRemoteOp op = LanRemoteOp(
      opId: opId,
      type: LanOpType.billRequest,
      device: phone,
      staffId: 4,
      payload: <String, Object?>{'order': order},
    );

    // Printed in the same step it is decided; then the till dies before the request reached its queue
    expect(await lanBills.decide(op), isTrue);
    expect(receipts(), hasLength(1));
    expect(pos.offline!.store.find(opId), isNull);

    // The app comes back (a new dedupe in memory): the waiter's phone sends the same request again
    final BillPrintService restarted = BillPrintService(
      data: pos,
      printing: printing,
      deviceUuid: () => pos.session.bootstrap?.device?.uuid,
      now: () => now,
      header: () => const ReceiptHeader(storeName: 'Choyxona'),
    );
    final LanBillPrints again = tillLanBillPrints(
      offline: pos.offline!,
      prints: restarted,
      now: () => now,
      autoPrint: () => true,
    );
    addTearDown(again.dispose);
    expect(again.printed(opId), isTrue);
    expect(await again.decide(op), isTrue, reason: 'on paper already');
    final LanOpResult retried = await botir.send(
      LanOpType.billRequest,
      <String, Object?>{'order': order},
      opId: opId,
    );
    expect(retried.accepted, isTrue, reason: retried.message);
    await lanBills.idle();
    expect(receipts(), hasLength(1));
    expect(pos.offline!.store.find(opId)!.payload['printed_on_lan'], isTrue);
  });

  test('review C6: the outage just started (the till still thinks it is online): the hub prints a request the '
      'server does not answer, and the server\'s print of it later comes out nowhere', () async {
    final LanClient botir = await connect(
      phone,
      LanApp.waiter,
      as: grant(phone, 4, 'Botir Aliyev'),
    );
    final String order = await waiterOrder(botir);
    // The server stops answering: the till has not noticed yet (an error answer keeps it "online")
    adapter.state.failPaths['/v1/pos/sync'] = 503;
    final String opId = newUuid();
    final LanOpResult asked = await botir.send(
      LanOpType.billRequest,
      <String, Object?>{'order': order},
      opId: opId,
    );
    expect(asked.accepted, isTrue, reason: asked.message);
    expect(pos.offline!.connectivity.state.online, isTrue);
    expect(
      receipts(),
      isEmpty,
      reason: 'the server is the one to print it now',
    );
    await until(
      () => receipts().isNotEmpty,
      reason: 'unanswered: the hub prints',
    );
    await lanBills.idle();
    expect(receipts(), hasLength(1));
    expect(lanBills.printed(opId), isTrue);

    // The internet is back: the server applies the request and announces its print with the request's key
    adapter.state.failPaths.clear();
    final BillPrintOutcome outcome = await prints.handle(
      BillPrintRequest(
        billUuid: 'server-bill',
        orderUuid: order,
        requestId: opId,
        targetDevice: pos.session.bootstrap?.device?.uuid,
      ),
    );
    expect(outcome, BillPrintOutcome.duplicate);
    expect(receipts(), hasLength(1));
  });

  test('online, or with the branch setting off, the till leaves the print to the server', () async {
    final LanClient botir = await connect(
      phone,
      LanApp.waiter,
      as: grant(phone, 4, 'Botir Aliyev'),
    );
    // The till reaches the server (the phone's Wi-Fi has no internet): the server prints as usual
    final String online = await waiterOrder(botir);
    expect(
      (await botir.send(LanOpType.billRequest, <String, Object?>{
        'order': online,
      })).accepted,
      isTrue,
    );
    await Future<void>.delayed(const Duration(milliseconds: 100));
    await lanBills.idle();
    expect(receipts(), isEmpty);
    expect(
      pos.offline!.store
          .operationsAfter(0)
          .where((SyncOperation o) => o.payload['printed_on_lan'] == true),
      isEmpty,
    );

    // Offline but the owner turned the automatic bill print off
    adapter.state.autoPrintBill = false;
    await pos.session.loadBootstrap();
    await goOffline();
    final String off = await waiterOrder(botir, table: 2);
    expect(
      (await botir.send(LanOpType.billRequest, <String, Object?>{
        'order': off,
      })).accepted,
      isTrue,
    );
    await Future<void>.delayed(const Duration(milliseconds: 100));
    await lanBills.idle();
    expect(receipts(), isEmpty);
    expect(
      pos.offline!.store
          .operationsAfter(0)
          .lastWhere((SyncOperation o) => o.type == SyncOpType.billRequest)
          .payload
          .containsKey('printed_on_lan'),
      isFalse,
    );
  });

  test(
    'without a receipt printer the hub does not take the print on itself',
    () async {
      printing.configure(const <PosPrinterInfo>[]);
      await goOffline();
      final LanClient botir = await connect(
        phone,
        LanApp.waiter,
        as: grant(phone, 4, 'Botir Aliyev'),
      );
      final String order = await waiterOrder(botir);
      await botir.send(LanOpType.billRequest, <String, Object?>{
        'order': order,
      });
      final SyncOperation request = pos.offline!.store
          .operationsAfter(0)
          .singleWhere((SyncOperation o) => o.type == SyncOpType.billRequest);
      expect(request.payload.containsKey('printed_on_lan'), isFalse);
    },
  );

  test('the cart on the till reaches the display tablets, and a display that joins later gets the last cart', () async {
    final LanClient screen = await connect(display, LanApp.display);
    final List<LanEvent> seen = <LanEvent>[];
    final StreamSubscription<LanEvent> sub = screen.events.listen(seen.add);
    addTearDown(sub.cancel);

    pos.displayFeed.publish(
      PosDisplayCart(
        state: PosDisplayScreen.cart,
        lines: const <PosDisplayLine>[],
        total: PosMoney.parse('36000'),
      ),
    );
    await until(
      () => seen.any((LanEvent e) => e.type == LanMsg.cartDisplay),
      reason: 'cart.display',
    );
    final LanEvent cart = seen.firstWhere(
      (LanEvent e) => e.type == LanMsg.cartDisplay,
    );
    expect(cart.payload['state'], 'cart');
    expect(cart.payload['total'], '36000.00');
    expect(cart.payload['session'], pos.displayFeed.session);
    expect(cart.payload['seq'], 1);

    // A display that (re)connects gets the cart at once
    await screen.dispose();
    clients.remove(screen);
    final List<LanEvent> later = <LanEvent>[];
    await connect(display, LanApp.display, into: later);
    await until(
      () => later.any((LanEvent e) => e.type == LanMsg.cartDisplay),
      reason: 'the last cart is replayed',
    );
    expect(
      later
          .firstWhere((LanEvent e) => e.type == LanMsg.cartDisplay)
          .payload['seq'],
      1,
    );
  });

  test('the till\'s ready, call and hand-over go to the displays; offline, a number whose dishes are all ready is '
      'announced once', () async {
    final LanClient screen = await connect(display, LanApp.display);
    final List<LanEvent> seen = <LanEvent>[];
    final StreamSubscription<LanEvent> sub = screen.events.listen(seen.add);
    addTearDown(sub.cancel);

    const PosQueueEntry entry = PosQueueEntry(
      orderUuid: 'o-1',
      number: 57,
      channel: 'pos_takeaway',
      orderStatus: 'preparing',
      status: PosPickupStatus.ready,
      callCount: 1,
    );
    publisher.queue(LanMsg.queueCalled, entry, shortCode: 'A57');
    await until(() => seen.any((LanEvent e) => e.type == LanMsg.queueCalled));
    final LanEvent called = seen.firstWhere(
      (LanEvent e) => e.type == LanMsg.queueCalled,
    );
    expect(called.payload['number'], 57);
    expect(called.payload['order'], 'o-1');
    expect(called.payload['short_code'], 'A57');
    expect(called.payload['called'], isTrue);

    // Offline: the hub's own view of an order with a queue number whose dishes are all ready
    await goOffline();
    Map<String, Object?> order(String status) => <String, Object?>{
      'uuid': 'o-2',
      'status': 'preparing',
      'short_code': 'B12',
      'queue': <String, Object?>{'number': 12, 'status': 'preparing'},
      'items': <Object?>[
        <String, Object?>{'id': 1, 'kitchen_status': 'ready'},
        <String, Object?>{'id': 2, 'kitchen_status': status},
        <String, Object?>{'id': 3, 'kitchen_status': 'void'},
      ],
    };
    final StreamController<LanEvent> events = StreamController<LanEvent>();
    final LanPublisher derived = _PublisherProbe(hub, pos, events.stream);
    derived.start();
    addTearDown(derived.dispose);
    events
      ..add(
        LanEvent(LanMsg.orderChanged, <String, Object?>{
          'order': order('sent'),
          'ref': 'o-2',
        }),
      )
      ..add(
        LanEvent(LanMsg.orderChanged, <String, Object?>{
          'order': order('ready'),
          'ref': 'o-2',
        }),
      )
      ..add(
        LanEvent(LanMsg.orderChanged, <String, Object?>{
          'order': order('served'),
          'ref': 'o-2',
        }),
      );
    await until(
      () => seen.any(
        (LanEvent e) =>
            e.type == LanMsg.queueReady && e.payload['number'] == 12,
      ),
    );
    await Future<void>.delayed(const Duration(milliseconds: 100));
    expect(
      seen.where(
        (LanEvent e) =>
            e.type == LanMsg.queueReady && e.payload['number'] == 12,
      ),
      hasLength(1),
    );
    expect(LanPublisher.readyForPickup(order('sent')), isFalse);
    expect(LanPublisher.readyForPickup(order('served')), isTrue);
    await events.close();
  });

  testWidgets(
    'the settings show the hub: on, its address and port, the devices and a QR to join',
    (WidgetTester tester) async {
      late LanClient botir;
      await tester.runAsync(() async {
        botir = await connect(
          phone,
          LanApp.waiter,
          as: grant(phone, 4, 'Botir Aliyev'),
        );
        await until(() => hub.peers.length == 1);
      });
      await tester.pumpWidget(
        MaterialApp(
          theme: RzTheme.light(),
          home: Scaffold(
            body: SingleChildScrollView(child: LanSettingsCard(hub: hub)),
          ),
        ),
      );
      expect(find.text('Lokal tarmoq'), findsOneWidget);
      expect(find.text('Yoqilgan: bu kassa markaz'), findsOneWidget);
      expect(find.text('127.0.0.1'), findsOneWidget);
      expect(find.text('${hub.port}'), findsOneWidget);
      expect(find.text('1 ta'), findsOneWidget);
      expect(
        find.byKey(const ValueKey<String>('lan-peer-$phone')),
        findsOneWidget,
      );
      expect(find.textContaining('Botir Aliyev'), findsOneWidget);
      expect(find.byKey(const ValueKey<String>('lan-qr')), findsOneWidget);
      expect(find.textContaining('rizqim-lan://127.0.0.1:'), findsOneWidget);
      await tester.runAsync(() => botir.close());
    },
  );

  testWidgets(
    'with no device joined yet, the settings say how to reach the hub, with the Windows firewall step',
    (WidgetTester tester) async {
      final List<List<String>> ran = <List<String>>[];
      await tester.pumpWidget(
        MaterialApp(
          theme: RzTheme.light(),
          home: Scaffold(
            body: SingleChildScrollView(
              child: LanSettingsCard(
                hub: hub,
                firewall: WindowsFirewall(
                  windows: true,
                  program: r'C:\Rizqim\rizqim_cashier.exe',
                  run: (String exe, List<String> args) async {
                    ran.add(<String>[exe, ...args]);
                    // netsh: "No rules match the specified criteria"
                    return ProcessResult(1, 1, '', '');
                  },
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Yoqilgan: bu kassa markaz'), findsOneWidget);
      expect(find.text("Hozircha yo'q"), findsOneWidget);
      expect(
        find.byKey(const ValueKey<String>('lan-reach-hint')),
        findsOneWidget,
      );
      expect(find.textContaining('AP isolation'), findsOneWidget);
      expect(
        find.byKey(const ValueKey<String>('lan-firewall-missing')),
        findsOneWidget,
      );
      expect(find.textContaining('${hub.port}-portni'), findsOneWidget);
      expect(ran.single.first, 'netsh');
      // The manual address and its QR are right below the hint
      expect(find.byKey(const ValueKey<String>('lan-qr')), findsOneWidget);
    },
  );

  testWidgets('a till that is not the hub says so and names the hub', (
    WidgetTester tester,
  ) async {
    await tester.runAsync(() async {
      adapter.state.bootstrapExtras['lan'] = <String, Object?>{
        ...section(role: 'client'),
        'hub': <String, Object?>{
          'device': 'cccccccc-0000-4000-8000-000000000009',
          'name': 'Kassa 2',
          'port': 18480,
        },
      };
      await pos.session.loadBootstrap();
      await until(() => !hub.running);
    });
    await tester.pumpWidget(
      MaterialApp(
        theme: RzTheme.light(),
        home: Scaffold(body: LanSettingsCard(hub: hub)),
      ),
    );
    expect(find.text("O'chiq: bu kassa markaz emas"), findsOneWidget);
    expect(find.textContaining('Kassa 2'), findsOneWidget);
    expect(find.byKey(const ValueKey<String>('lan-qr')), findsNothing);
  });
}

/// [LanPublisher] fed with hand-made hub events (the same `order.changed` the till's applier gives).
class _PublisherProbe extends LanPublisher {
  _PublisherProbe(CashierLanHub hub, PosData pos, this.source)
    : super(
        hub: _ProbeHub(hub, source),
        feed: pos.displayFeed,
        offline: pos.offline,
      );

  final Stream<LanEvent> source;
}

class _ProbeHub extends CashierLanHub {
  _ProbeHub(this.real, this.source)
    : super(session: real.session, auth: real.auth, offline: real.offline);

  final CashierLanHub real;
  final Stream<LanEvent> source;

  @override
  Stream<LanEvent> get events => source;

  @override
  List<LanPeerInfo> get peers => real.peers;

  @override
  void publish(String type, Map<String, Object?> payload, {String? to}) =>
      real.publish(type, payload, to: to);
}
