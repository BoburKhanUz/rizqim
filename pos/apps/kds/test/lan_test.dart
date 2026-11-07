import 'dart:async';
import 'dart:io';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_kds/src/lan/kds_lan.dart';
import 'package:rizqim_kds/src/lan/lan_badge.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// P6 lokal ko'prik, oshxona ekrani: haqiqiy markaz (localhost) va uning soxta kassa tomoni. Internet uziladi — doska
/// markazdan (oxirgi server ro'yxati + `order.changed` / `kitchen.item_status`), taymer markaz soati bo'yicha (kassa
/// soati 5 daqiqa oldinda bo'lsa ham), "Tayyor" markazga; "Buyurtma tayyor" — oddiy xabar.
void main() {
  final Uint8List secret = Uint8List.fromList(
    List<int>.generate(32, (int i) => 7 + i),
  );
  const String hubDevice = 'aaaaaaaa-0000-4000-8000-0000000000aa';
  const String kdsDevice = 'cccccccc-0000-4000-8000-0000000000cc';
  // The till's clock runs 5 minutes ahead of the tablet's
  DateTime hubClock() => DateTime.now().add(const Duration(minutes: 5));

  late _Till till;
  late LanHub hub;

  setUp(() async {
    till = _Till(hubClock);
    hub = LanHub(
      credentials: LanCredentials(
        branchId: 12,
        keyId: 1,
        device: hubDevice,
        isHub: true,
        deviceKey: LanKeys.device(secret, hubDevice),
        beaconKey: LanKeys.beacon(secret),
        port: 0,
        hubDevice: hubDevice,
        hubName: 'Kassa 1',
        hubSecret: secret,
        devices: const <LanDeviceInfo>[
          LanDeviceInfo(uuid: kdsDevice, type: 'kds', name: 'Oshxona'),
        ],
      ),
      applier: till,
      clock: hubClock,
      address: InternetAddress.loopbackIPv4,
      port: 0,
      beaconEvery: null,
      advertisedHost: '127.0.0.1',
    );
    await hub.start();
  });

  tearDown(() async {
    await hub.dispose();
    await till.dispose();
  });

  Map<String, Object?> section() {
    final DateTime iat = DateTime.now();
    return LanCredentials(
      branchId: 12,
      keyId: 1,
      device: kdsDevice,
      isHub: false,
      deviceKey: LanKeys.device(secret, kdsDevice),
      beaconKey: LanKeys.beacon(secret),
      port: hub.port,
      hubDevice: hubDevice,
      hubName: 'Kassa 1',
      grant: LanGrant.sign(<String, Object?>{
        'v': 1,
        'kid': 1,
        'branch_id': 12,
        'device': kdsDevice,
        'staff_id': 6,
        'name': 'Oshpaz Ali',
        'role': 'cook',
        'permissions': <String>['orders.view', 'kitchen.manage'],
        'session': 1,
        'iat': iat.millisecondsSinceEpoch ~/ 1000,
        'exp':
            iat.add(const Duration(hours: 36)).millisecondsSinceEpoch ~/ 1000,
      }, LanKeys.grant(secret)),
    ).toJson();
  }

  KdsLan kdsLan() => KdsLan(
    setup: KitchenSetupService(Dio()),
    link: LanLink(
      app: LanApp.kds,
      probeCloud: () async => false,
      listen: (LanCredentials c, {Duration timeout = Duration.zero}) async =>
          null,
      retry: const <Duration>[Duration(milliseconds: 50)],
      probeEvery: const Duration(milliseconds: 100),
      tickEvery: const Duration(milliseconds: 20),
    ),
  )..start();

  Future<void> until(bool Function() ok) async {
    final DateTime end = DateTime.now().add(const Duration(seconds: 5));
    while (!ok()) {
      if (DateTime.now().isAfter(end)) throw TimeoutException('condition');
      await Future<void>.delayed(const Duration(milliseconds: 10));
    }
  }

  test('the internet goes: the board comes from the hub, bumps go to it, the wait is on the hub\'s clock', () async {
    final KdsLan lan = kdsLan();
    addTearDown(lan.dispose);
    await lan.setSection(section());
    final _Cloud cloud = _Cloud();
    final LanKitchenService service = LanKitchenService(cloud: cloud, lan: lan);

    // Online: the server's list (kept for an outage)
    expect((await service.tickets()).tickets.single.uuid, 'server-1');
    expect(lan.mode, LanMode.cloud);

    // The internet goes: the first load fails, the tablet finds the till
    cloud.offline = true;
    await expectLater(service.tickets(), throwsA(isA<ApiException>()));
    await until(() => lan.viaHub);

    // A waiter's new order on the till, sent to the kitchen 90 seconds ago (till's clock)
    till.add(
      _order('lan-1', <Map<String, Object?>>[
        _item(21, 'Lag\'mon'),
        _item(22, 'Choy'),
      ], openedAt: hubClock().subtract(const Duration(seconds: 90))),
    );
    await until(() => lan.board.ticket('lan-1') != null);
    final KitchenTicketList list = await service.tickets();
    expect(list.tickets.map((KitchenTicket t) => t.uuid), <String>[
      'server-1',
      'lan-1',
    ]);
    final KitchenTicket fresh = list.tickets.last;
    expect(fresh.isCooking, isTrue);
    expect(fresh.lines.map((KitchenLine l) => l.quantity), <num>[2, 2]);
    expect(fresh.waitingAt(DateTime.now()).inSeconds, inInclusiveRange(88, 95));
    expect(fresh.tableRef, '2-xona');

    // "Tayyor" goes to the till's queue
    final KitchenTicket? bumped = await service.bumpLine('lan-1', 21);
    expect(till.ops.last, <Object?>['kitchen.bump', 'lan-1', 21]);
    expect(bumped!.lines.first.status, KitchenLineStatus.ready);
    // A whole order "ready" has no LAN operation: plainly said
    await expectLater(
      service.markOrderReady('lan-1'),
      throwsA(
        isA<ApiException>().having(
          (ApiException e) => e.reason,
          'reason',
          'not_allowed_on_lan',
        ),
      ),
    );
    // The hub refuses: the reason comes back as the board's message
    till.refuse = true;
    await expectLater(
      service.bumpLine('lan-1', 22),
      throwsA(
        isA<ApiException>().having(
          (ApiException e) => e.message,
          'message',
          'Yuborilmagan.',
        ),
      ),
    );
    till.refuse = false;
    await service.bumpLine('lan-1', 22);
    // Everything ready: off the board
    expect(
      (await service.tickets()).tickets.map((KitchenTicket t) => t.uuid),
      <String>['server-1'],
    );

    // Another screen bumps the server's order on the till
    hub.publish(LanMsg.kitchenItemStatus, <String, Object?>{
      'order': 'server-1',
      'line': 11,
      'status': 'ready',
    });
    await until(() => lan.board.ticket('server-1') == null);
  });

  testWidgets(
    'the badge says "Lokal tarmoq" through the hub and nothing online',
    (WidgetTester tester) async {
      final KdsLan lan = await tester.runAsync(() async {
        final KdsLan l = kdsLan();
        await l.setSection(section());
        return l;
      }) as KdsLan;
      await tester.pumpWidget(
        MaterialApp(
          theme: RzTheme.dark(),
          home: Scaffold(body: KdsLanBadge(lan: lan)),
        ),
      );
      expect(find.text('Lokal tarmoq'), findsNothing);
      expect(find.text("Internet yo'q"), findsNothing);
      await tester.runAsync(() async {
        lan.link.reportCloud(false);
        await until(() => lan.viaHub);
      });
      await tester.pump();
      expect(find.text('Lokal tarmoq'), findsOneWidget);
      await tester.runAsync(() async {
        await hub.stop();
        await until(() => lan.mode == LanMode.alone);
      });
      await tester.pump();
      expect(find.text("Internet yo'q"), findsOneWidget);
      await tester.runAsync(() async => lan.dispose());
    },
  );
}

Map<String, Object?> _item(int id, String name) => <String, Object?>{
  'id': id,
  'product_name': name,
  'quantity': '2.000',
  'quantity_label': '2 ta',
  'unit': 'pcs',
  'kitchen_status': 'sent',
  'sent_at': DateTime.now()
      .add(const Duration(minutes: 5))
      .subtract(const Duration(seconds: 90))
      .toUtc()
      .toIso8601String(),
  'modifiers': <Object?>[],
};

Map<String, Object?> _order(
  String uuid,
  List<Map<String, Object?>> items, {
  required DateTime openedAt,
}) => <String, Object?>{
  'uuid': uuid,
  'status': 'open',
  'channel': 'pos_hall',
  'short_code': 'K1-7',
  'table_ref': '2-xona',
  'hall_ref': 'Zal',
  'opened_at': openedAt.toUtc().toIso8601String(),
  'items': items,
};

/// The till's side of the hub (its queue and orders), in memory.
class _Till implements LanOpApplier {
  _Till(this.clock);

  final DateTime Function() clock;
  final Map<String, Map<String, Object?>> orders =
      <String, Map<String, Object?>>{};
  final List<List<Object?>> ops = <List<Object?>>[];
  bool refuse = false;
  final StreamController<LanEvent> _events =
      StreamController<LanEvent>.broadcast();
  final StreamController<void> _status = StreamController<void>.broadcast();

  void add(Map<String, Object?> order) {
    orders['${order['uuid']}'] = order;
    _events
      ..add(
        LanEvent(LanMsg.kitchenTicket, <String, Object?>{
          'order': order['uuid'],
          'lines': order['items'],
        }),
      )
      ..add(
        LanEvent(LanMsg.orderChanged, <String, Object?>{
          'order': order,
          'ref': order['uuid'],
        }),
      );
  }

  @override
  Future<LanOpResult> apply(LanRemoteOp op, LanGrantView staff) async {
    if (refuse) return LanOpResult.refused(op.opId, 'nope', 'Yuborilmagan.');
    final Object? line = op.payload['line'];
    ops.add(<Object?>[op.type, op.order, line]);
    final Map<String, Object?>? o = orders[op.order];
    if (o != null && op.type == LanOpType.kitchenBump) {
      for (final Object? raw in o['items']! as List<Object?>) {
        final Map<String, Object?> i = raw! as Map<String, Object?>;
        if (i['id'] == line) i['kitchen_status'] = 'ready';
      }
    }
    return LanOpResult(opId: op.opId, accepted: true, order: o);
  }

  @override
  Map<String, Object?> state() => <String, Object?>{
    'floor': null,
    'open_orders': <Object?>[],
  };

  @override
  Map<String, Object?>? order(String ref) => orders[ref];

  @override
  Stream<LanEvent> get events => _events.stream;

  @override
  bool get cloud => false;

  @override
  int get pending => ops.length;

  @override
  Stream<void> get statusChanges => _status.stream;

  Future<void> dispose() async {
    await _events.close();
    await _status.close();
  }
}

/// The server's kitchen API: one cooking ticket, then no answer.
class _Cloud implements KitchenService {
  bool offline = false;

  @override
  Future<KitchenTicketList> tickets({
    int? branchId,
    bool includeReady = false,
    int? canceledWithin,
  }) async {
    if (offline) throw const ApiException(message: "Aloqa yo'q");
    final DateTime now = DateTime.now();
    return KitchenTicketList(
      tickets: <KitchenTicket>[
        KitchenTicket(
          uuid: 'server-1',
          status: 'preparing',
          type: 'dine_in',
          waitingSeconds: 300,
          receivedAt: now,
          acceptedAt: now.subtract(const Duration(minutes: 5)),
          lines: const <KitchenLine>[
            KitchenLine(
              id: 11,
              productName: 'Osh',
              quantityLabel: '1',
              status: KitchenLineStatus.sent,
            ),
          ],
        ),
      ],
      receivedAt: now,
    );
  }

  @override
  Future<KitchenTicket?> bumpLine(String orderUuid, int lineId) async => null;

  @override
  Future<KitchenTicket?> recallLine(String orderUuid, int lineId) async => null;

  @override
  Future<KitchenTicket?> assembled(
    String orderUuid, {
    String? idempotencyKey,
  }) async => null;

  @override
  Future<void> markOrderReady(String orderUuid) async {}

  @override
  Future<void> markQueueReady(
    String orderUuid, {
    String? idempotencyKey,
  }) async {}
}
