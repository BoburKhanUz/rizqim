import 'dart:async';
import 'dart:io';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_customer_display/src/controller/display_controller.dart';
import 'package:rizqim_customer_display/src/lan/display_lan.dart';
import 'package:rizqim_customer_display/src/screens/display_screen.dart';
import 'package:rizqim_customer_display/src/screens/settings_dialog.dart';
import 'package:rizqim_customer_display/src/settings/display_settings.dart';
import 'package:rizqim_customer_display/src/sound/ready_sound.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/harness.dart';

/// P6 lokal ko'prik, mijoz ekrani: haqiqiy markaz (localhost) va uning soxta kassa tomoni. Kalitlar `GET /pos/lan`
/// dan (qurilmada saqlanadi); internet uziladi — chek (`cart.display`) va raqamlar (`queue.*`, `order.changed`)
/// markazdan, burchakda "Lokal tarmoq"; markaz navbati serverga yetgach odatdagi rejim va server holati.
void main() {
  final Uint8List secret = Uint8List.fromList(
    List<int>.generate(32, (int i) => 40 + i),
  );
  const String hubDevice = 'aaaaaaaa-0000-4000-8000-0000000000aa';

  late _Till till;
  late LanHub hub;
  late FakeDisplayBackend backend;
  late FakePosAdapter adapter;
  late RizqimClient client;
  late PosData pos;
  late String display;

  setUp(() async {
    backend = FakeDisplayBackend(DateTime.now, FakePosRealtimeHub())
      ..tillUuid = hubDevice.toUpperCase();
    adapter = FakePosAdapter()..respond = backend.handle;
    client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: MemoryKeyValueStore(),
      dio: Dio()..httpClientAdapter = adapter,
    );
    pos = PosData(client);
    await client.auth.pairTerminal('ABCD-EFGH');
    display = client.auth.terminal!.device.uuid.toLowerCase();
    till = _Till();
    hub = LanHub(
      credentials: LanCredentials(
        branchId: 1,
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
          LanDeviceInfo(uuid: display, type: 'display', name: 'Mijoz ekrani'),
        ],
      ),
      applier: till,
      address: InternetAddress.loopbackIPv4,
      port: 0,
      beaconEvery: null,
      advertisedHost: '127.0.0.1',
    );
    await hub.start();
    // The terminal's own section (no grant: a display only listens)
    adapter.state.terminalLan = LanCredentials(
      branchId: 1,
      keyId: 1,
      device: display,
      isHub: false,
      deviceKey: LanKeys.device(secret, display),
      beaconKey: LanKeys.beacon(secret),
      port: hub.port,
      hubDevice: hubDevice,
      hubName: 'Kassa 1',
    ).toJson();
  });

  tearDown(() async {
    await hub.dispose();
    await till.dispose();
    await pos.dispose();
  });

  LanLink fastLink() => LanLink(
    app: LanApp.display,
    probeCloud: () => lanProbeServer(pos.http),
    // No beacon on localhost: the display finds the till on this machine's own address (Windows, the till's PC)
    listen: (LanCredentials c, {Duration timeout = Duration.zero}) async =>
        null,
    retry: const <Duration>[Duration(milliseconds: 50)],
    probeEvery: const Duration(milliseconds: 100),
    tickEvery: const Duration(milliseconds: 20),
  );

  Future<void> until(bool Function() ok) async {
    final DateTime end = DateTime.now().add(const Duration(seconds: 5));
    while (!ok()) {
      if (DateTime.now().isAfter(end)) throw TimeoutException('condition');
      await Future<void>.delayed(const Duration(milliseconds: 10));
    }
  }

  DisplayController controllerFor(DisplayLan lan, SilentReadySound sound) =>
      DisplayController(
        repository: pos.display,
        settings: DisplaySettingsController(MemorySettingsStore()),
        sound: sound,
        lan: lan,
        pollInterval: const Duration(milliseconds: 100),
      );

  Map<String, Object?> cart(int seq, {String state = 'cart'}) =>
      <String, Object?>{
        'state': state,
        'seq': seq,
        'session': 'run-9',
        'lines': <Object?>[
          FakeDisplayBackend.line("Lag'mon", qty: '2', price: '30000.00'),
        ],
        'subtotal': '60000.00',
        'discount': '0.00',
        'total': '${60000 + seq}.00',
        'queue_number': 12,
        'payment': null,
      };

  test('the internet goes: the cart and the numbers come from the till over Wi-Fi; back on the server once the '
      'till\'s queue reached it', () async {
    backend.preparing.add(FakeDisplayBackend.entry(5, 'preparing'));
    final MemoryKeyValueStore store = MemoryKeyValueStore();
    final DisplayLan lan = DisplayLan(pos: pos, link: fastLink(), store: store);
    final SilentReadySound sound = SilentReadySound();
    final DisplayController c = controllerFor(lan, sound);
    addTearDown(() async {
      c.dispose();
      lan.dispose();
    });
    await lan.start();
    c.start();
    await until(() => c.state != null && lan.link.available);
    expect(c.link, isNot(DisplayLink.lan));
    expect(lan.mode, LanMode.cloud);
    expect(hub.peers, isEmpty, reason: 'online the LAN stays idle');
    // The keys are kept on the device (a restart without the internet still joins the till)
    expect(await store.read(DisplayLan.sectionKey), contains(display));

    // The internet goes: the next poll fails, the display joins the till
    adapter.offline = true;
    await until(() => lan.viaHub);
    expect(c.link, DisplayLink.lan);
    expect(describeLink(c.link), 'Lokal tarmoq');
    expect(hub.peers.single.app, LanApp.display);

    // The till's cart
    hub.publish(LanMsg.cartDisplay, cart(3));
    await until(() => c.view == DisplayView.cart);
    expect(c.cart.total.toApiString(), '60003.00');
    expect(c.cart.lines.single.name, "Lag'mon");
    // An older one arriving late is dropped
    hub.publish(LanMsg.cartDisplay, cart(2));
    hub.publish(LanMsg.cartDisplay, cart(4));
    await until(() => c.cart.total.toApiString() == '60004.00');

    // A new number on the till (order.changed with its queue), then the kitchen finished it: announced once
    till.emit(
      const LanEvent(LanMsg.orderChanged, <String, Object?>{
        'ref': 'lan-8',
        'order': <String, Object?>{
          'uuid': 'lan-8',
          'status': 'open',
          'channel': 'pos_takeaway',
          'queue': <String, Object?>{'number': 8, 'status': 'preparing'},
        },
      }),
    );
    await until(
      () => c.board!.preparing.any((PosQueueEntry e) => e.number == 8),
    );
    expect(c.board!.preparing.map((PosQueueEntry e) => e.number), <int>[5, 8]);
    hub.publish(LanMsg.queueReady, <String, Object?>{
      'order': 'lan-8',
      'number': 8,
      'short_code': 'T-8',
      'call_count': 0,
      'called': false,
    });
    await until(() => c.board!.ready.any((PosQueueEntry e) => e.number == 8));
    expect(c.board!.preparing.map((PosQueueEntry e) => e.number), <int>[5]);
    expect(c.latestAnnouncement?.number, 8);
    expect(sound.plays, 1);
    // Called again at the till: announced again
    hub.publish(LanMsg.queueCalled, <String, Object?>{
      'order': 'lan-8',
      'number': 8,
      'call_count': 1,
      'called': true,
    });
    await until(() => sound.plays == 2);
    expect(c.latestAnnouncement!.again, isTrue);
    // Handed over: off the board
    hub.publish(LanMsg.queueHanded, <String, Object?>{
      'order': 'lan-8',
      'number': 8,
    });
    await until(() => c.board!.ready.isEmpty);

    // The server is back, but the till's queue is not on it yet: the till's picture stays
    adapter.offline = false;
    till
      ..cloudUp = true
      ..queued = 2
      ..changed();
    await Future<void>.delayed(const Duration(milliseconds: 400));
    expect(lan.mode, LanMode.lan);
    expect(c.cart.total.toApiString(), '60004.00');
    // The queue reached the server: back to normal, the server's state at once
    till
      ..queued = 0
      ..changed();
    await until(() => lan.mode == LanMode.cloud);
    await until(() => c.link != DisplayLink.lan && c.cart.isIdle);
    expect(c.board!.preparing.map((PosQueueEntry e) => e.number), <int>[5]);
    await until(() => hub.peers.isEmpty);
  });

  test('another till\'s screen keeps its own cart; a display restarted without the internet uses the kept keys', () async {
    final MemoryKeyValueStore store = MemoryKeyValueStore();
    final DisplayLan first = DisplayLan(
      pos: pos,
      link: fastLink(),
      store: store,
    );
    await first.start();
    expect(first.link.available, isTrue);
    first.dispose();

    // Restarted with no internet at all
    backend.tillUuid = 'bbbbbbbb-0000-4000-8000-0000000000bb';
    adapter.offline = true;
    final DisplayLan lan = DisplayLan(pos: pos, link: fastLink(), store: store);
    final SilentReadySound sound = SilentReadySound();
    final DisplayController c = controllerFor(lan, sound);
    addTearDown(() async {
      c.dispose();
      lan.dispose();
    });
    await lan.start();
    expect(lan.link.available, isTrue);
    c.start();
    await until(() => lan.viaHub);
    // Nothing from the server yet: the till's cart still shows
    hub.publish(LanMsg.cartDisplay, cart(1));
    await until(() => c.view == DisplayView.cart);

    // Back online the server says this screen follows another till
    adapter.offline = false;
    till
      ..cloudUp = true
      ..changed();
    await until(() => lan.mode == LanMode.cloud);
    await until(() => c.state?.tillUuid != null);
    // Its keys were fetched again (the server answered)
    expect(await store.read(DisplayLan.sectionKey), contains(display));
  });

  testWidgets(
    'the corner says "Lokal tarmoq"; the settings take the hub\'s address and refuse nonsense',
    (WidgetTester tester) async {
      late DisplayLan lan;
      late DisplayController c;
      await tester.runAsync(() async {
        lan = DisplayLan(pos: pos, link: fastLink());
        c = controllerFor(lan, SilentReadySound());
        await lan.start();
        c.start();
        await until(() => c.state != null);
        adapter.offline = true;
        await until(() => lan.viaHub);
      });
      tester.view
        ..physicalSize = const Size(1280, 800)
        ..devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      await tester.pumpWidget(
        MaterialApp(
          theme: RzTheme.dark(),
          home: DisplayScreen(controller: c),
        ),
      );
      await tester.pump();
      expect(find.byKey(const ValueKey<String>('link-lan')), findsOneWidget);
      expect(find.text('Lokal tarmoq'), findsOneWidget);
      expect(find.text("Aloqa yo'q"), findsNothing);

      await tester.pumpWidget(
        MaterialApp(
          theme: RzTheme.dark(),
          home: Scaffold(
            body: DisplaySettingsDialog(settings: c.settings, lan: lan),
          ),
        ),
      );
      await tester.pump();
      expect(find.text('Lokal tarmoq'), findsOneWidget);
      expect(
        find.textContaining('Markaz: Kassa 1 (127.0.0.1:'),
        findsOneWidget,
      );
      await tester.enterText(
        find.byKey(const ValueKey<String>('lan-address')),
        '::: nonsense',
      );
      await tester.tap(find.text('Ulanish'));
      await tester.runAsync(
        () => Future<void>.delayed(const Duration(milliseconds: 20)),
      );
      await tester.pump();
      expect(find.textContaining("Manzil noto'g'ri"), findsOneWidget);
      // The port defaults to the branch's (from the keys)
      await tester.runAsync(() async {
        expect(await lan.setManual('127.0.0.1'), isNull);
      });
      expect(lan.link.manualEndpoint, LanEndpoint('127.0.0.1', hub.port));

      await tester.pumpWidget(const SizedBox.shrink());
      await tester.runAsync(() async {
        c.dispose();
        lan.dispose();
        await Future<void>.delayed(const Duration(milliseconds: 50));
      });
    },
  );
}

/// The till's side of the hub (its queue, in memory): only the status and the events matter to a display.
class _Till implements LanOpApplier {
  bool cloudUp = false;
  int queued = 0;
  final StreamController<LanEvent> _events =
      StreamController<LanEvent>.broadcast();
  final StreamController<void> _status = StreamController<void>.broadcast();

  void emit(LanEvent e) => _events.add(e);

  void changed() => _status.add(null);

  @override
  Future<LanOpResult> apply(LanRemoteOp op, LanGrantView staff) async =>
      LanOpResult.refused(op.opId, 'not_allowed_on_lan', "Yo'q.");

  @override
  Map<String, Object?> state() => <String, Object?>{
    'floor': null,
    'open_orders': <Object?>[],
  };

  @override
  Map<String, Object?>? order(String ref) => null;

  @override
  Stream<LanEvent> get events => _events.stream;

  @override
  bool get cloud => cloudUp;

  @override
  int get pending => queued;

  @override
  Stream<void> get statusChanges => _status.stream;

  Future<void> dispose() async {
    await _events.close();
    await _status.close();
  }
}
