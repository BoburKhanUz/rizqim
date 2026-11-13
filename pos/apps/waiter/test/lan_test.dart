import 'dart:async';
import 'dart:io';
import 'dart:math';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_ui/rizqim_ui.dart';
import 'package:rizqim_waiter/src/features/order/order_page.dart';
import 'package:rizqim_waiter/src/lan/lan_dialog.dart';
import 'package:rizqim_waiter/src/lan/waiter_lan.dart';
import 'package:rizqim_waiter/src/shell/connection_indicator.dart';

import 'support/harness.dart';

/// P6 lokal ko'prik, ofitsiant: (1) [WaiterLan] haqiqiy markaz bilan (localhost, kassaning haqiqiy `PosOffline`i):
/// internet uziladi — telefon markazga ulanadi, buyurtma kassa navbatiga shu telefon nomidan tushadi; xodim chiqsa
/// ko'prik to'xtaydi. (2) Ekran `lan` rejimida: belgi va lenta, amallar markazga, schyotni qayta chiqarish — oddiy xabar.
void main() {
  group('WaiterLan with a real hub', () {
    late FakePosAdapter tillAdapter;
    late PosData till;
    late PosOfflineLanApplier applier;
    late LanHub hub;
    final Uint8List secret = Uint8List.fromList(
      List<int>.generate(32, (int i) => 100 + i),
    );
    const String hubDevice = 'aaaaaaaa-0000-4000-8000-0000000000aa';
    final DateTime now = DateTime.utc(2026, 10, 22, 13);

    setUp(() async {
      tillAdapter = FakePosAdapter(clock: () => now);
      FakeCashierBackend(tillAdapter.state);
      FakeSyncBackend(tillAdapter.state);
      tillAdapter.state.openShift();
      final RizqimClient client = RizqimClient(
        baseUrl: 'https://api.test/api',
        store: MemoryKeyValueStore(),
        dio: Dio()..httpClientAdapter = tillAdapter,
        clock: () => now,
      );
      till = PosData(
        client,
        clock: () => now,
        random: Random(3),
        offlineStore: await SyncStore.open(
          MemorySyncStorage(),
          clock: () => now,
          random: Random(5),
        ),
      );
      await client.auth.pairTerminal('ABCD-EFGH');
      await client.auth.signInStaff(userId: 3, pin: tillAdapter.state.pins[3]!);
      final PosBootstrap boot = await till.session.loadBootstrap();
      await till.catalog.load(PosChannel.hall, menuVersion: boot.menuVersion);
      await till.floor.load();
      await till.orders.openOrders();
      tillAdapter.offline = true;
      await till.floor.load();
      applier = PosOfflineLanApplier(offline: till.offline!);
    });

    tearDown(() async {
      await hub.dispose();
      await applier.dispose();
      await till.dispose();
    });

    Future<void> until(bool Function() ok) async {
      final DateTime end = DateTime.now().add(const Duration(seconds: 5));
      while (!ok()) {
        if (DateTime.now().isAfter(end)) throw TimeoutException('condition');
        await Future<void>.delayed(const Duration(milliseconds: 10));
      }
    }

    test('the internet goes: the phone works through the till; signing out stops the bridge', () async {
      final WaiterHarness h = WaiterHarness();
      await h.client.auth.pairTerminal('ABCD-EFGH');
      final String phone = h.client.auth.terminal!.device.uuid.toLowerCase();
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
          devices: <LanDeviceInfo>[
            LanDeviceInfo(uuid: phone, type: 'pos', name: 'Ofitsiant'),
          ],
        ),
        applier: applier,
        address: InternetAddress.loopbackIPv4,
        port: 0,
        beaconEvery: null,
        advertisedHost: '127.0.0.1',
      );
      await hub.start();
      final DateTime iat = DateTime.now();
      h.state.bootstrapExtras['lan'] = LanCredentials(
        branchId: 12,
        keyId: 1,
        device: phone,
        isHub: false,
        deviceKey: LanKeys.device(secret, phone),
        beaconKey: LanKeys.beacon(secret),
        port: hub.port,
        hubDevice: hubDevice,
        hubName: 'Kassa 1',
        grant: LanGrant.sign(<String, Object?>{
          'v': 1,
          'kid': 1,
          'branch_id': 12,
          'device': phone,
          'staff_id': 4,
          'name': 'Botir Aliyev',
          'role': 'waiter',
          'permissions': <String>['orders.view', 'orders.manage'],
          'session': 1,
          'iat': iat.millisecondsSinceEpoch ~/ 1000,
          'exp':
              iat.add(const Duration(hours: 36)).millisecondsSinceEpoch ~/ 1000,
        }, LanKeys.grant(secret)),
      ).toJson();

      final WaiterLan lan = WaiterLan(
        data: h.services.data,
        link: LanLink(
          app: LanApp.waiter,
          probeCloud: () => lanProbeServer(h.services.data.http),
          // No beacon on localhost: the phone finds the till on this machine's own address
          listen: (
            LanCredentials c, {
            Duration timeout = Duration.zero,
          }) async => null,
          retry: const <Duration>[Duration(milliseconds: 50)],
          probeEvery: const Duration(milliseconds: 100),
          tickEvery: const Duration(milliseconds: 20),
        ),
      );
      addTearDown(lan.dispose);
      await lan.start();
      await h.client.auth.signInStaff(userId: 4, pin: h.state.pins[4]!);
      await h.services.session.loadBootstrap();
      await until(() => lan.link.available);
      expect(lan.mode, LanMode.cloud);
      expect(hub.peers, isEmpty);

      // The phone's Wi-Fi has no internet: its next request says so
      h.adapter.offline = true;
      await expectLater(
        h.services.data.floor.load(),
        throwsA(isA<PosNetworkException>()),
      );
      await until(() => lan.mode == LanMode.lan);
      expect(h.services.data.connectivity.state.lan, isTrue);
      expect(h.services.data.connectivity.state.usable, isTrue);
      expect(hub.peers.single.staff?.name, 'Botir Aliyev');

      final PosFloor floor = await h.services.data.floor.load();
      expect(floor.halls, isNotEmpty);
      final PosOrder opened = await h.services.data.orders.open(
        channel: PosChannel.hall,
        tableId: 3,
        guestsCount: 2,
        items: <PosLineInput>[
          PosLineInput(
            variantUuid: FakePosState.variantUuid(1),
            quantity: PosQuantity.parse('1'),
          ),
        ],
        send: true,
      );
      final SyncOperation queued = till.offline!.store.pending.firstWhere(
        (SyncOperation o) => o.type == SyncOpType.orderOpen,
      );
      expect(queued.origin, phone);
      expect(queued.staffId, 4);
      expect((await h.services.data.orders.get(opened.uuid)).uuid, opened.uuid);

      // Signed out: no staff, no operations over the LAN
      await h.client.auth.signOutStaff();
      await until(() => !lan.link.available);
      await until(() => hub.peers.isEmpty);
      expect(h.services.data.connectivity.state.lan, isFalse);
    });
  });

  group('the screens in lan mode', () {
    testWidgets(
      'badge, banner, actions through the hub, the reprint is plainly online only',
      (WidgetTester tester) async {
        final WaiterHarness h = WaiterHarness();
        final String uuid = seedWaiterOrder(h, tableId: 1, sent: false);
        await pumpHome(tester, h);
        expect(find.textContaining('Lokal tarmoq'), findsNothing);

        // The internet goes; the branch's hub answers (a stand-in that serves the fake backend as the waiter)
        final _StandInHub route = _StandInHub(h.state, staffId: 4);
        h.services.data.lan = route;
        h.adapter.offline = true;
        h.services.data.connectivity
          ..reportFailure(const SocketException('offline'))
          ..setLan(true);
        await tester.pumpAndSettle();
        expect(find.byType(RzOfflineBanner), findsOneWidget);
        expect(find.textContaining('Lokal tarmoq'), findsWidgets);
        final ConnectionIndicator badge = tester.widget(
          find.byType(ConnectionIndicator),
        );
        expect(badge.connectivity.state.lan, isTrue);

        await tester.tap(find.text('1-xona'));
        await tester.pumpAndSettle();
        expect(find.byType(OrderPage), findsOneWidget);
        await tapButton(tester, 'Yuborish (2)');
        expect(
          route.ops.map((List<Object?> o) => o.first),
          contains('order.send'),
        );
        expect(h.adapter.to('POST', '/v1/pos/orders/$uuid/send'), isEmpty);

        await tapButton(tester, "Schyot so'rash");
        await tapButton(tester, "So'rash");
        expect(
          route.ops.map((List<Object?> o) => o.first),
          contains('bill.request'),
        );
        await tapButton(tester, 'Schyotni qayta chiqarish');
        await tapButton(tester, 'Chiqarish');
        expect(
          find.textContaining('schyotni qayta chiqarish faqat internet bilan'),
          findsWidgets,
        );
        h.services.data.lan = null;
        await route.dispose();
      },
    );

    testWidgets('the LAN dialog takes an address and refuses nonsense', (
      WidgetTester tester,
    ) async {
      final LanLink link = LanLink(
        app: LanApp.waiter,
        probeCloud: () async => true,
      );
      final WaiterHarness h = WaiterHarness();
      final WaiterLan lan = WaiterLan(data: h.services.data, link: link);
      await tester.pumpWidget(
        MaterialApp(
          theme: RzTheme.light(),
          home: Scaffold(body: LanDialog(lan: lan)),
        ),
      );
      expect(find.textContaining('Internet bor'), findsOneWidget);
      expect(find.textContaining('lokal tarmoq sozlanmagan'), findsOneWidget);
      expect(await lan.setManual('::: nonsense'), isNotNull);
      expect(await lan.setManual('192.168.1.20'), isNull);
      expect(link.manualEndpoint, const LanEndpoint('192.168.1.20', 18480));
      lan.dispose();
    });
  });
}

/// The hub as the phone sees it, standing in for a real one: serves the fake backend as [staffId] and records every
/// operation (the protocol itself is tested in pos_lan with real sockets).
class _StandInHub implements PosLanRoute {
  _StandInHub(this.state, {required this.staffId});

  final FakePosState state;
  final int staffId;
  final List<List<Object?>> ops = <List<Object?>>[];
  final StreamController<PosEvent> _events =
      StreamController<PosEvent>.broadcast();

  Map<String, Object?> _get(String path) =>
      ((state.handleAsStaff(staffId, 'GET', path, null).body!
                  as Map<String, Object?>)['data'] ??
              const <String, Object?>{})
          as Map<String, Object?>;

  @override
  bool get active => true;

  @override
  Stream<PosEvent> get events => _events.stream;

  @override
  Future<Map<String, Object?>> floor() async => _get('/v1/pos/floor');

  @override
  Future<List<Map<String, Object?>>> openOrders() async =>
      const <Map<String, Object?>>[];

  @override
  Future<Map<String, Object?>> order(String ref) async =>
      _get('/v1/pos/orders/$ref');

  @override
  Future<Map<String, Object?>> op(
    String type,
    Map<String, Object?> payload, {
    String? opId,
  }) async {
    ops.add(<Object?>[type, payload, opId]);
    final String ref = payload['order']! as String;
    final int version = (await order(ref))['version']! as int;
    final String? path = switch (type) {
      'order.send' => '/v1/pos/orders/$ref/send',
      'bill.request' => '/v1/pos/orders/$ref/request-bill',
      _ => null,
    };
    if (path != null) {
      state.handleAsStaff(staffId, 'POST', path, <String, Object?>{
        'version': version,
      }, idempotencyKey: opId ?? 'k-${ops.length}');
    }
    return order(ref);
  }

  @override
  String? resolve(String ref) => null;

  Future<void> dispose() => _events.close();
}
