import 'dart:convert';
import 'dart:io';
import 'dart:math';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_cashier/src/lan/cashier_lan_hub.dart';
import 'package:rizqim_cashier/src/lan/lan_indicator.dart';
import 'package:flutter/material.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// P6 local bridge on the till: the hub starts from the bootstrap's `lan` section, keeps its keys in secure storage
/// (not in the offline copy), runs again from them after an offline restart, and stops when it is no longer the hub.
/// Real sockets on localhost (plain tests: no widget binding overriding HttpClient).
void main() {
  const String hubUuid = 'aaaaaaaa-0000-4000-8000-000000000001';
  const String phone = 'bbbbbbbb-0000-4000-8000-000000000002';
  final Uint8List secret = Uint8List.fromList(
    List<int>.generate(32, (int i) => i * 3),
  );
  final DateTime now = DateTime.utc(2026, 10, 22, 13);

  Map<String, Object?> hubSection() => <String, Object?>{
    'v': 1,
    'branch_id': FakePosState.branchId,
    'key_id': 1,
    'role': 'hub',
    'device': hubUuid,
    'device_key': base64.encode(LanKeys.device(secret, hubUuid)),
    'beacon_key': base64.encode(LanKeys.beacon(secret)),
    'hub': <String, Object?>{
      'device': hubUuid,
      'name': 'Kassa 1',
      'port': 18480,
    },
    'hub_secret': base64.encode(secret),
    'devices': <Object?>[
      <String, Object?>{'uuid': phone, 'type': 'pos', 'name': 'Ofitsiant'},
    ],
    'grant': null,
    'clock_window_seconds': 120,
  };

  late FakePosAdapter adapter;
  late PosData pos;
  late RizqimClient client;
  late MemoryKeyValueStore secure;
  final List<CashierLanHub> hubs = <CashierLanHub>[];

  LanHub loopback(LanCredentials c, LanOpApplier a) => LanHub(
    credentials: c,
    applier: a,
    address: InternetAddress.loopbackIPv4,
    port: 0,
    beaconEvery: null,
    advertisedHost: '127.0.0.1',
  );

  CashierLanHub hubOf(PosData data) {
    final CashierLanHub h = CashierLanHub(
      session: data.session,
      auth: client.auth,
      offline: data.offline,
      store: secure,
      hubFactory: loopback,
      fetchLan: data.display.lan,
    );
    hubs.add(h);
    return h;
  }

  setUp(() async {
    adapter = FakePosAdapter(clock: () => now);
    adapter.state.bootstrapExtras['lan'] = hubSection();
    FakeCashierBackend(adapter.state);
    FakeSyncBackend(adapter.state);
    secure = MemoryKeyValueStore();
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
      offlineStore: await SyncStore.open(MemorySyncStorage(), clock: () => now),
    );
    await client.auth.pairTerminal('ABCD-EFGH');
    await client.auth.signInStaff(userId: 3, pin: adapter.state.pins[3]!);
  });

  tearDown(() async {
    for (final CashierLanHub h in hubs) {
      h.dispose();
    }
    hubs.clear();
    await Future<void>.delayed(const Duration(milliseconds: 50));
    await pos.dispose();
  });

  Future<void> until(bool Function() ok) async {
    for (var i = 0; i < 200 && !ok(); i++) {
      await Future<void>.delayed(const Duration(milliseconds: 10));
    }
    expect(ok(), isTrue);
  }

  test('the hub till starts the bridge from its bootstrap and counts the devices; the keys stay out of the offline copy', () async {
    final CashierLanHub hub = hubOf(pos);
    await hub.start();
    expect(hub.running, isFalse);
    await pos.session.loadBootstrap();
    await until(() => hub.running);
    expect(hub.isHub, isTrue);
    expect(hub.link, startsWith('rizqim-lan://127.0.0.1:'));
    expect(await secure.read(CashierLanHub.storageKey), contains('hub_secret'));
    // The plain offline copy of the bootstrap has no LAN keys
    expect(
      pos.offline!.store.doc('bootstrap')?.map?.containsKey('lan'),
      isFalse,
    );

    final LanCredentials phoneCreds = LanCredentials.fromJson(<String, Object?>{
      ...hubSection(),
      'role': 'client',
      'device': phone,
      'device_key': base64.encode(LanKeys.device(secret, phone)),
      'hub_secret': null,
      'devices': null,
    })!;
    final int port = int.parse(hub.link!.split(':')[2].split('?').first);
    final LanClient waiter = LanClient(
      credentials: phoneCreds,
      app: LanApp.waiter,
    );
    await waiter.connect(LanEndpoint('127.0.0.1', port));
    await until(() => hub.peers.length == 1);
    expect(hub.peers.single.app, LanApp.waiter);
    await waiter.dispose();
    await until(() => hub.peers.isEmpty);
  });

  test('review S5: the owner revokes a phone: on refresh (pos.lan_changed, reconnect, every 5 min) the hub drops it at '
      'once, without waiting for the next bootstrap', () async {
    final CashierLanHub hub = hubOf(pos);
    await hub.start();
    await pos.session.loadBootstrap();
    await until(() => hub.running);
    final LanClient waiter = LanClient(
      credentials: LanCredentials.fromJson(<String, Object?>{
        ...hubSection(),
        'role': 'client',
        'device': phone,
        'device_key': base64.encode(LanKeys.device(secret, phone)),
        'hub_secret': null,
        'devices': null,
      })!,
      app: LanApp.waiter,
    );
    addTearDown(waiter.dispose);
    await waiter.connect(LanEndpoint('127.0.0.1', hub.port!));
    await until(() => hub.peers.length == 1);

    // The server no longer lists the phone (revoked); the till hears it through GET /pos/lan
    adapter.state.terminalLan = <String, Object?>{
      ...hubSection(),
      'devices': <Object?>[],
    };
    await hub.refresh();
    await until(() => hub.peers.isEmpty);
    expect(hub.running, isTrue);
    expect(waiter.connected, isFalse);
    // Kept for an offline restart too
    expect(await secure.read(CashierLanHub.storageKey), isNot(contains(phone)));

    // The server does not answer: the hub carries on as it is
    adapter.state.failPaths['/v1/pos/lan'] = 503;
    await hub.refresh();
    expect(hub.running, isTrue);
  });

  test('reopened without the internet the till runs the bridge from its stored keys; no longer the hub, it stops and forgets them', () async {
    final CashierLanHub first = hubOf(pos);
    await first.start();
    await pos.session.loadBootstrap();
    await until(() => first.running);
    first.dispose();
    hubs.remove(first);

    // A new start: nobody signed in yet, no bootstrap
    final CashierLanHub again = hubOf(pos);
    await again.start();
    expect(again.running, isTrue);

    // The owner made another till the hub: this one's bootstrap says so
    adapter.state.bootstrapExtras['lan'] = <String, Object?>{
      ...hubSection(),
      'role': 'client',
      'hub_secret': null,
      'devices': null,
      'hub': <String, Object?>{
        'device': 'cccccccc-0000-4000-8000-000000000009',
        'name': 'Kassa 2',
        'port': 18480,
      },
    };
    await pos.session.loadBootstrap();
    await until(() => !again.running);
    expect(again.isHub, isFalse);

    // Unpaired: the keys are gone
    adapter.state.bootstrapExtras['lan'] = null;
    await pos.session.loadBootstrap();
    await until(() => true);
    await Future<void>.delayed(const Duration(milliseconds: 50));
    expect(await secure.read(CashierLanHub.storageKey), isNull);
  });

  testWidgets(
    'the top bar shows "Lokal tarmoq: N qurilma" on the hub, and the address for the manual fallback',
    (WidgetTester tester) async {
      final CashierLanHub hub = hubOf(pos);
      await tester.runAsync(() async {
        await hub.start();
        await pos.session.loadBootstrap();
        await until(() => hub.running);
      });
      await tester.pumpWidget(
        MaterialApp(
          theme: RzTheme.light(),
          home: Scaffold(
            appBar: AppBar(actions: <Widget>[LanIndicator(hub: hub)]),
          ),
        ),
      );
      expect(find.text('Lokal tarmoq: 0 qurilma'), findsOneWidget);
      await tester.tap(find.text('Lokal tarmoq: 0 qurilma'));
      await tester.pumpAndSettle();
      expect(
        find.text("Hozircha hech qanday qurilma ulanmagan."),
        findsOneWidget,
      );
      expect(find.textContaining('rizqim-lan://127.0.0.1:'), findsOneWidget);
      await tester.runAsync(() async {
        hub.dispose();
        hubs.remove(hub);
        await Future<void>.delayed(const Duration(milliseconds: 50));
      });
    },
  );
}
