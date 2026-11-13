import 'dart:async';
import 'dart:io';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support.dart';

/// Stage B: the shared client controller ([LanLink]) and the waiter's route ([LanPosRoute]) against a real hub on
/// localhost over the till's real [PosOffline] (fake backend): mode switching both ways, the "drained" rule, the
/// hub going away, a wrong key, discovery with the multicast lock, and the waiter's repositories through the hub.
void main() {
  late FakePosAdapter adapter;
  late PosData till;
  late PosOfflineLanApplier applier;
  late LanHub hub;
  late TestBranch branch;
  late int port;
  final List<LanLink> links = <LanLink>[];
  DateTime now = DateTime.utc(2026, 10, 22, 13);

  setUp(() async {
    now = DateTime.utc(2026, 10, 22, 13);
    adapter = FakePosAdapter(clock: () => now);
    final FakePosState state = adapter.state;
    state.staff.add(<String, Object?>{
      'id': 5,
      'name': 'Sardor Usmonov',
      'role': 'waiter',
      'staff_code': '1005',
    });
    FakeCashierBackend(state);
    FakeSyncBackend(state);
    state.openShift();
    final RizqimClient client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: MemoryKeyValueStore(),
      dio: Dio()..httpClientAdapter = adapter,
      clock: () => now,
    );
    till = PosData(
      client,
      clock: () => now,
      random: Random(7),
      offlineStore: await SyncStore.open(
        MemorySyncStorage(),
        clock: () => now,
        random: Random(11),
      ),
    );
    await client.auth.pairTerminal('ABCD-EFGH');
    await client.auth.signInStaff(userId: 3, pin: state.pins[3]!);
    final PosBootstrap boot = await till.session.loadBootstrap();
    await till.catalog.load(PosChannel.hall, menuVersion: boot.menuVersion);
    await till.floor.load();
    await till.orders.openOrders();
    adapter.offline = true;
    await till.floor.load();

    branch = TestBranch();
    applier = PosOfflineLanApplier(offline: till.offline!);
    hub = LanHub(
      credentials: branch.hubCredentials(),
      applier: applier,
      address: InternetAddress.loopbackIPv4,
      port: 0,
      beaconEvery: null,
      advertisedHost: '127.0.0.1',
    );
    await hub.start();
    port = hub.port;
  });

  tearDown(() async {
    for (final LanLink l in links) {
      await l.dispose();
    }
    links.clear();
    await hub.dispose();
    await applier.dispose();
    await till.dispose();
  });

  const List<String> waiterPerms = <String>[
    'orders.view',
    'orders.manage',
    'tables.manage',
    'menu.view',
  ];

  LanLink link({
    required bool Function() cloud,
    String device = TestBranch.phone1,
    int staff = 5,
    LanCredentials? credentials,
    LanBeaconListener? listen,
    LanMulticastLock? lock,
    bool manual = true,
  }) {
    final LanLink l = LanLink(
      app: LanApp.waiter,
      probeCloud: () async => cloud(),
      listen:
          listen ??
          (LanCredentials c, {Duration timeout = Duration.zero}) async => null,
      multicast: lock,
      retry: const <Duration>[Duration(milliseconds: 50)],
      probeEvery: const Duration(milliseconds: 50),
      tickEvery: const Duration(milliseconds: 20),
      tryLoopback: false,
    );
    links.add(l);
    unawaited(
      l.setCredentials(
        credentials ??
            branch.client(
              device,
              port: port,
              grant: branch.grant(
                device,
                staff,
                name: 'Sardor Usmonov',
                permissions: waiterPerms,
              ),
            ),
      ),
    );
    if (manual) l.setManual('127.0.0.1:$port');
    return l..start();
  }

  Future<void> until(
    bool Function() ok, {
    Duration timeout = const Duration(seconds: 5),
  }) async {
    final DateTime end = DateTime.now().add(timeout);
    while (!ok()) {
      if (DateTime.now().isAfter(end)) throw TimeoutException('condition');
      await Future<void>.delayed(const Duration(milliseconds: 10));
    }
  }

  Map<String, Object?> tea(String qty) => <String, Object?>{
    'variant_id': FakePosState.variantUuid(1),
    'quantity': qty,
  };

  test('cloud: the LAN stays idle; the server goes → lan through the hub; the server is back but the hub\'s queue '
      'is not → still lan; the queue reaches the server → cloud and the connection is closed', () async {
    var cloud = true;
    final LanLink l = link(cloud: () => cloud);
    await Future<void>.delayed(const Duration(milliseconds: 150));
    expect(l.mode, LanMode.cloud);
    expect(l.connected, isFalse);
    expect(hub.peers, isEmpty);

    cloud = false;
    l.reportCloud(false);
    await until(() => l.mode == LanMode.lan);
    expect(l.viaHub, isTrue);
    expect(l.endpoint, LanEndpoint('127.0.0.1', port));
    expect(l.staff?['id'], 5);
    expect(hub.peers.single.app, LanApp.waiter);

    final LanOpResult r = await l.send(LanOpType.orderOpen, <String, Object?>{
      'order': '9b000000-0000-4000-8000-000000000001',
      'channel': 'pos_hall',
      'table_id': 2,
      'items': <Object?>[tea('1')],
      'send': true,
    });
    expect(r.accepted, isTrue, reason: '${r.reason} ${r.message}');
    await until(() => l.hubPending > 0);

    // The phone sees the server again, the till has not sent its queue yet: the server does not know the order
    cloud = true;
    l.reportCloud(true);
    await Future<void>.delayed(const Duration(milliseconds: 150));
    expect(l.mode, LanMode.lan);

    adapter.offline = false;
    expect((await till.offline!.syncNow()).drained, isTrue);
    await until(() => l.mode == LanMode.cloud);
    expect(l.connected, isFalse);
    await until(() => hub.peers.isEmpty);
  });

  test(
    'the hub goes away → alone (keeps trying); it comes back → lan again',
    () async {
      final LanLink l = link(cloud: () => false);
      l.reportCloud(false);
      await until(() => l.mode == LanMode.lan);
      await hub.stop();
      await until(() => l.mode == LanMode.alone);
      expect(l.lastError, isNotNull);
      hub = LanHub(
        credentials: branch.hubCredentials(port: port),
        applier: applier,
        address: InternetAddress.loopbackIPv4,
        beaconEvery: null,
        advertisedHost: '127.0.0.1',
      );
      await hub.start();
      await until(() => l.mode == LanMode.lan);
      expect(l.lastError, isNull);
    },
  );

  test(
    'a device with the wrong key never gets in: alone, with a plain reason',
    () async {
      final LanLink l = link(
        cloud: () => false,
        credentials: branch.client(
          TestBranch.phone1,
          port: port,
          key: LanKeys.device(branch.secret, TestBranch.phone2),
        ),
      );
      l.reportCloud(false);
      await until(() => l.lastError != null);
      expect(l.mode, LanMode.alone);
      expect(l.connected, isFalse);
    },
  );

  test('no address typed in: the beacon finds the hub, the multicast lock is held only while listening', () async {
    final _Lock lock = _Lock();
    final LanLink l = link(
      cloud: () => false,
      manual: false,
      lock: lock,
      listen: (LanCredentials c, {Duration timeout = Duration.zero}) async {
        expect(lock.held, isTrue);
        return LanBeacon.verify(
          hub.beacon().sign(c.beaconKey),
          c,
          now: DateTime.now(),
        );
      },
    );
    l.reportCloud(false);
    await until(() => l.mode == LanMode.lan);
    expect(lock.acquired, 1);
    expect(lock.held, isFalse);
    expect(l.setManual('not an address:::'), isNotNull);
  });

  test('review P6 S3: every valid beacon heard in the window is tried (an old address of the hub, or one an attacker '
      'replays, does not leave the phone alone)', () async {
    final List<LanEndpoint> heard = <LanEndpoint>[];
    LanBeacon at(String host, int p, LanCredentials c) => LanBeacon.verify(
      LanBeacon(
        branchId: branch.branchId,
        keyId: branch.keyId,
        hubDevice: TestBranch.hub,
        host: host,
        port: p,
        ts: DateTime.now().millisecondsSinceEpoch,
      ).sign(c.beaconKey),
      c,
      now: DateTime.now(),
    )!;
    // A port nothing listens on (the hub's address before a DHCP change, or a forged one)
    final ServerSocket dead = await ServerSocket.bind(
      InternetAddress.loopbackIPv4,
      0,
    );
    final int deadPort = dead.port;
    await dead.close();
    final LanLink l = LanLink(
      app: LanApp.waiter,
      probeCloud: () async => false,
      collect: (LanCredentials c, {Duration timeout = Duration.zero}) async {
        final List<LanBeacon> all = <LanBeacon>[
          at('127.0.0.1', deadPort, c),
          at('127.0.0.1', port, c),
        ];
        heard.addAll(all.map((LanBeacon b) => b.endpoint));
        return all;
      },
      retry: const <Duration>[Duration(milliseconds: 50)],
      probeEvery: const Duration(milliseconds: 50),
      tickEvery: const Duration(milliseconds: 20),
      tryLoopback: false,
    );
    links.add(l);
    await l.setCredentials(
      branch.client(
        TestBranch.phone1,
        port: port,
        grant: branch.grant(TestBranch.phone1, 5, permissions: waiterPerms),
      ),
    );
    l.start();
    l.reportCloud(false);
    await until(() => l.mode == LanMode.lan);
    expect(heard.first, LanEndpoint('127.0.0.1', deadPort));
    expect(l.endpoint, LanEndpoint('127.0.0.1', port));
  });

  test('the waiter\'s repositories through the hub: open, round, send, get, floor, the bill asked; the money and '
      'the bill copy stay at the till', () async {
    final LanLink l = link(cloud: () => false);
    l.reportCloud(false);
    await until(() => l.mode == LanMode.lan);

    final RizqimClient phone = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: MemoryKeyValueStore(),
      dio: Dio()..httpClientAdapter = adapter,
      clock: () => now,
    );
    final PosData waiter = PosData(phone, clock: () => now);
    final LanPosRoute route = LanPosRoute(l);
    waiter.lan = route;
    final List<PosEvent> seen = <PosEvent>[];
    final StreamSubscription<PosEvent> sub = waiter.lanEvents.listen(seen.add);
    addTearDown(() async {
      await sub.cancel();
      await route.dispose();
      await waiter.dispose();
    });

    final PosOrder opened = await waiter.orders.open(
      channel: PosChannel.hall,
      tableId: 2,
      guestsCount: 2,
      items: <PosLineInput>[
        PosLineInput(
          variantUuid: FakePosState.variantUuid(1),
          quantity: PosQuantity.parse('2'),
        ),
      ],
      send: true,
      idempotencyKey: '9c000000-0000-4000-8000-000000000001',
    );
    // The order is known on the hub by the operation's id
    expect(opened.uuid, '9c000000-0000-4000-8000-000000000001');
    expect(opened.items.single.kitchenStatus, PosKitchenStatus.sent);
    // The same key again: once
    final PosOrder again = await waiter.orders.open(
      channel: PosChannel.hall,
      tableId: 2,
      items: <PosLineInput>[
        PosLineInput(
          variantUuid: FakePosState.variantUuid(1),
          quantity: PosQuantity.parse('2'),
        ),
      ],
      idempotencyKey: '9c000000-0000-4000-8000-000000000001',
    );
    expect(again.uuid, opened.uuid);
    expect(
      till.offline!.store.pending
          .where((SyncOperation o) => o.type == SyncOpType.orderOpen)
          .length,
      1,
    );

    final PosOrder round = await waiter.orders.addRound(
      opened.uuid,
      version: opened.version,
      items: <PosLineInput>[
        PosLineInput(
          variantUuid: FakePosState.variantUuid(1),
          quantity: PosQuantity.parse('1'),
        ),
      ],
      fireMode: PosFireMode.hold,
    );
    expect(round.items, hasLength(2));
    final PosOrder sent = await waiter.orders.send(
      opened.uuid,
      version: round.version,
      itemIds: <int>[round.items.last.id],
    );
    // The held line goes when sent by its id
    expect(
      sent.items.every(
        (PosOrderItem i) => i.kitchenStatus == PosKitchenStatus.sent,
      ),
      isTrue,
    );
    expect((await waiter.orders.get(opened.uuid)).uuid, opened.uuid);
    final PosFloor floor = await waiter.floor.load();
    expect(floor.table(2)?.orders, isNotEmpty);
    expect(
      (await waiter.orders.openOrders()).map((PosOpenOrder o) => o.uuid),
      contains(opened.uuid),
    );

    final PosOrder asked = await waiter.orders.requestBill(opened.uuid);
    expect(asked.billRequestedAt, isNotNull);

    await expectLater(
      waiter.billing.bills(opened.uuid),
      throwsA(
        isA<PosOfflineUnavailable>().having(
          (PosOfflineUnavailable e) => e.reason,
          'reason',
          'not_allowed_on_lan',
        ),
      ),
    );
    await expectLater(
      waiter.billing.requestPrint('9c000000-0000-4000-8000-0000000000ff'),
      throwsA(isA<PosOfflineUnavailable>()),
    );
    // Only on the server: moving the order
    await expectLater(
      waiter.orders.move(opened.uuid, version: 1, tableId: 3),
      throwsA(isA<PosOfflineUnavailable>()),
    );
    // The hub's refusal keeps its reason: void a line sent to the kitchen
    await expectLater(
      waiter.orders.voidItem(
        opened.uuid,
        opened.items.single.id,
        version: 1,
        reason: 'xato',
      ),
      throwsA(isA<PosOfflineUnavailable>()),
    );
    await until(
      () => seen.any(
        (PosEvent e) => e is PosOrderItemsChanged && e.orderUuid == opened.uuid,
      ),
    );

    // The hub goes: an operation with no answer is a network error carrying its id (sent again with it, once)
    await hub.stop();
    await until(() => !l.connected);
    await expectLater(
      waiter.orders.markServed(opened.uuid, 1, version: 1),
      throwsA(isA<PosNetworkException>()),
      reason: 'alone: back to the server path, which does not answer',
    );
  });
}

class _Lock implements LanMulticastLock {
  int acquired = 0;
  bool held = false;

  @override
  Future<void> acquire() async {
    acquired++;
    held = true;
  }

  @override
  Future<void> release() async => held = false;
}
