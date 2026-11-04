import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:pos_lan/pos_lan.dart';
import 'package:test/test.dart';

import 'support.dart';

/// An applier that takes everything and remembers it (the till's own rules are in bridge_test.dart).
class RecordingApplier implements LanOpApplier {
  final List<(LanRemoteOp, LanGrantView)> applied =
      <(LanRemoteOp, LanGrantView)>[];
  final StreamController<LanEvent> _events =
      StreamController<LanEvent>.broadcast();
  final StreamController<void> _status = StreamController<void>.broadcast();

  @override
  Future<LanOpResult> apply(LanRemoteOp op, LanGrantView staff) async {
    applied.add((op, staff));
    return LanOpResult(
      opId: op.opId,
      accepted: true,
      order: <String, Object?>{'uuid': op.order},
    );
  }

  @override
  Map<String, Object?> state() => <String, Object?>{
    'floor': null,
    'open_orders': <Object?>[],
  };

  @override
  Map<String, Object?>? order(String ref) => null;

  @override
  Stream<LanEvent> get events => _events.stream;

  void emit(LanEvent e) => _events.add(e);

  @override
  bool cloud = false;

  @override
  int pending = 0;

  @override
  Stream<void> get statusChanges => _status.stream;

  void status() => _status.add(null);
}

void main() {
  late TestBranch branch;
  late RecordingApplier applier;
  late LanHub hub;
  final List<LanClient> clients = <LanClient>[];

  setUp(() async {
    branch = TestBranch();
    applier = RecordingApplier();
    hub = LanHub(
      credentials: branch.hubCredentials(),
      applier: applier,
      address: InternetAddress.loopbackIPv4,
      port: 0,
      beaconEvery: null,
      advertisedHost: '127.0.0.1',
    );
    await hub.start();
  });

  tearDown(() async {
    for (final LanClient c in clients) {
      await c.dispose();
    }
    clients.clear();
    await hub.dispose();
  });

  LanClient client(
    String device, {
    String? grant,
    LanApp app = LanApp.waiter,
    int? keyId,
    List<int>? key,
  }) {
    final LanClient c = LanClient(
      credentials: branch.client(
        device,
        port: hub.port,
        grant: grant,
        keyId: keyId,
        key: key == null ? null : Uint8List.fromList(key),
      ),
      app: app,
    );
    clients.add(c);
    return c;
  }

  LanEndpoint at() => LanEndpoint('127.0.0.1', hub.port);

  group('auth', () {
    test('a device of the branch with its grant is welcomed and its staff is known; the hub lists it', () async {
      final LanClient phone = client(
        TestBranch.phone1,
        grant: branch.grant(TestBranch.phone1, 4, name: 'Botir'),
      );
      final LanWelcome w = await phone.connect(at());
      expect(w.hubDevice, TestBranch.hub);
      expect(w.staff, <String, Object?>{
        'id': 4,
        'name': 'Botir',
        'role': 'waiter',
      });
      expect(w.grantError, isNull);
      expect(hub.peers.single.device, TestBranch.phone1);
      expect(hub.peers.single.app, LanApp.waiter);
      expect(hub.peers.single.staff?.staffId, 4);

      final LanOpResult r = await phone.send(
        LanOpType.orderRound,
        <String, Object?>{'order': 'o-1', 'items': <Object?>[]},
      );
      expect(r.accepted, isTrue);
      expect(applier.applied.single.$1.device, TestBranch.phone1);
      expect(applier.applied.single.$1.staffId, 4);
      expect(applier.applied.single.$1.opId, r.opId);
    });

    test('a device not of the branch, a wrong key or another key version is shut out', () async {
      await expectLater(
        client(TestBranch.stranger).connect(at()),
        throwsA(
          isA<LanUnreachable>().having(
            (LanUnreachable e) => e.reason,
            'reason',
            'unknown_device',
          ),
        ),
      );
      // A phone of the branch with a key that is not its own (the beacon key, another branch's secret)
      await expectLater(
        client(
          TestBranch.phone1,
          key: branch.client(TestBranch.phone1).beaconKey,
        ).connect(at()),
        throwsA(
          isA<LanUnreachable>().having(
            (LanUnreachable e) => e.reason,
            'reason',
            'bad_sig',
          ),
        ),
      );
      final TestBranch other = TestBranch(
        branchId: 13,
        secret: Uint8List(32)..fillRange(0, 32, 7),
      );
      await expectLater(
        LanClient(
          credentials: other.client(TestBranch.phone1),
          app: LanApp.waiter,
        ).connect(at()),
        throwsA(
          isA<LanUnreachable>().having(
            (LanUnreachable e) => e.reason,
            'reason',
            'bad_sig',
          ),
        ),
      );
      await expectLater(
        client(TestBranch.phone2, keyId: 2).connect(at()),
        throwsA(
          isA<LanUnreachable>().having(
            (LanUnreachable e) => e.reason,
            'reason',
            'key_mismatch',
          ),
        ),
      );
      expect(hub.peers, isEmpty);
    });

    test('a frame before hello is refused, and so is pretending to be another device on the same socket', () async {
      final RawPeer raw = await RawPeer.connect(
        hub.port,
        TestBranch.phone1,
        branch.client(TestBranch.phone1).deviceKey,
      );
      raw.sendRaw(raw.seal(LanMsg.ping, const <String, Object?>{}));
      expect(await raw.closed(), (LanClose.badFrame, 'hello_required'));

      final RawPeer p = await RawPeer.connect(
        hub.port,
        TestBranch.phone1,
        branch.client(TestBranch.phone1).deviceKey,
      );
      p.sendRaw(
        p.seal(LanMsg.hello, <String, Object?>{'nonce': 'n1', 'app': 'waiter'}),
      );
      expect((await p.nextOf(LanMsg.welcome)).payload['nonce'], 'n1');
      // The KDS's own key, but on the phone's socket
      p.sendRaw(
        LanFrameCodec.seal(
          LanFrame(
            type: LanMsg.ping,
            device: TestBranch.kds,
            seq: 1,
            ts: DateTime.now().millisecondsSinceEpoch,
            keyId: 1,
          ),
          LanChannelKey(branch.client(TestBranch.kds).deviceKey),
        ),
      );
      expect(await p.closed(), (LanClose.unauthorized, 'device_changed'));
    });

    test(
      'a replayed or stale frame is dropped and told, the connection stays',
      () async {
        final RawPeer p = await RawPeer.connect(
          hub.port,
          TestBranch.phone1,
          branch.client(TestBranch.phone1).deviceKey,
        );
        p.sendRaw(
          p.seal(LanMsg.hello, <String, Object?>{
            'nonce': 'n',
            'app': 'waiter',
            'grant': branch.grant(TestBranch.phone1, 4),
          }),
        );
        await p.nextOf(LanMsg.welcome);

        final String op = p.seal(LanMsg.op, <String, Object?>{
          'op_id': 'f0000000-0000-4000-8000-000000000001',
          'type': LanOpType.orderSend,
          'staff_id': 4,
          'payload': <String, Object?>{'order': 'o'},
        });
        p.sendRaw(op);
        expect((await p.nextOf(LanMsg.opResult)).payload['status'], 'accepted');
        // Captured on the Wi-Fi and sent again: refused, not applied twice
        p.sendRaw(op);
        expect((await p.nextOf(LanMsg.error)).payload['reason'], 'replay');
        // An old frame (a fresh seq, the time three minutes back)
        p.sendRaw(
          p.seal(
            LanMsg.ping,
            const <String, Object?>{},
            ts: DateTime.now()
                .subtract(const Duration(minutes: 3))
                .millisecondsSinceEpoch,
          ),
        );
        expect((await p.nextOf(LanMsg.error)).payload['reason'], 'stale');
        expect(applier.applied, hasLength(1));
        expect(hub.refusedFrames, 2);
        // Still connected
        p.sendRaw(p.seal(LanMsg.ping, const <String, Object?>{}));
        expect((await p.nextOf(LanMsg.pong)).payload['hub_time'], isA<int>());
      },
    );

    test(
      'a frame with a broken signature after hello ends the connection',
      () async {
        final RawPeer p = await RawPeer.connect(
          hub.port,
          TestBranch.phone1,
          branch.client(TestBranch.phone1).deviceKey,
        );
        p.sendRaw(
          p.seal(LanMsg.hello, <String, Object?>{
            'nonce': 'n',
            'app': 'waiter',
          }),
        );
        await p.nextOf(LanMsg.welcome);
        final Map<String, Object?> e = (jsonDecode(
          p.seal(LanMsg.ping, const <String, Object?>{}),
        ) as Map).cast<String, Object?>();
        e['seq'] = (e['seq']! as int) + 7;
        p.sendRaw(jsonEncode(e));
        expect(await p.closed(), (LanClose.unauthorized, 'bad_sig'));
      },
    );

    test('a grant of another branch, another device, a forged or an expired one: connected, but no operation goes in', () async {
      Future<void> refused(String device, String grant, String error) async {
        final LanClient c = client(device, grant: grant);
        final LanWelcome w = await c.connect(at());
        expect(w.staff, isNull, reason: error);
        expect(w.grantError, error);
        final LanOpResult r = await c.send(
          LanOpType.orderSend,
          <String, Object?>{'order': 'o'},
        );
        expect(r.accepted, isFalse);
        expect(r.reason, 'grant_required');
        await c.close();
      }

      await refused(
        TestBranch.phone1,
        branch.grant(TestBranch.phone1, 4, branch: 13),
        'wrong_branch',
      );
      await refused(
        TestBranch.phone1,
        branch.grant(TestBranch.phone2, 4),
        'grant_device',
      );
      await refused(
        TestBranch.phone1,
        LanGrant.sign(<String, Object?>{
          'v': 1,
          'kid': 1,
          'branch_id': 12,
          'device': TestBranch.phone1,
          'staff_id': 2,
          'role': 'manager',
          'iat': 1,
          'exp': 9999999999,
        }, branch.client(TestBranch.phone1).deviceKey),
        'grant_invalid',
      );
      await refused(
        TestBranch.phone1,
        branch.grant(
          TestBranch.phone1,
          4,
          issuedAt: DateTime.now().subtract(const Duration(hours: 37)),
        ),
        'grant_expired',
      );
      // A display has no staff: it watches only
      final LanClient display = client(TestBranch.display, app: LanApp.display);
      expect((await display.connect(at())).grantError, isNull);
      expect(
        (await display.send(LanOpType.kitchenBump, <String, Object?>{
          'order': 'o',
          'line': 1,
        })).reason,
        'grant_required',
      );
      expect(applier.applied, isEmpty);
    });

    test('an operation for someone else than the grant, or one that is not for the LAN, is refused', () async {
      final LanClient phone = client(
        TestBranch.phone1,
        grant: branch.grant(TestBranch.phone1, 4),
      );
      await phone.connect(at());
      for (final String type in <String>[
        'bill.pay',
        'bill.create',
        'order.close',
        'order.discount',
        'refund',
      ]) {
        final LanOpResult r = await phone.send(type, <String, Object?>{
          'order': 'o',
        });
        expect(r.reason, 'not_allowed_on_lan', reason: type);
      }
      expect(applier.applied, isEmpty);
    });
  });

  group('events', () {
    test('what the till publishes and what the applier tells reach every connected device; lan.state follows', () async {
      final LanClient a = client(
        TestBranch.phone1,
        grant: branch.grant(TestBranch.phone1, 4),
      );
      final LanClient kds = client(
        TestBranch.kds,
        app: LanApp.kds,
        grant: branch.grant(
          TestBranch.kds,
          9,
          role: 'cook',
          permissions: <String>['kitchen.manage'],
        ),
      );
      final LanClient display = client(TestBranch.display, app: LanApp.display);
      final Inbox inA = Inbox(a.events),
          inKds = Inbox(kds.events),
          inDisplay = Inbox(display.events);
      await a.connect(at());
      await kds.connect(at());
      await display.connect(at());
      await inA.wait(
        LanMsg.lanState,
        where: (LanEvent e) =>
            (e.payload['devices']! as List<Object?>).length == 3,
      );
      expect(
        hub.peers.map((LanPeerInfo p) => p.device),
        unorderedEquals(<String>[
          TestBranch.phone1,
          TestBranch.kds,
          TestBranch.display,
        ]),
      );

      applier.emit(
        const LanEvent(LanMsg.kitchenTicket, <String, Object?>{
          'order': 'o-1',
          'lines': <Object?>[],
        }),
      );
      hub.publish(LanMsg.cartDisplay, <String, Object?>{'total': '42000.00'});
      hub.publish(LanMsg.queueReady, <String, Object?>{
        'number': 17,
      }, to: TestBranch.display);
      for (final Inbox i in <Inbox>[inA, inKds, inDisplay]) {
        await i.wait(LanMsg.kitchenTicket);
        await i.wait(LanMsg.cartDisplay);
      }
      expect((await inDisplay.wait(LanMsg.queueReady)).payload['number'], 17);
      expect(inA.of(LanMsg.queueReady), isEmpty);

      // The till's queue reached the server: the devices may go back to the cloud
      applier
        ..cloud = true
        ..pending = 0
        ..status();
      await inA.wait(
        LanMsg.lanState,
        where: (LanEvent e) => e.payload['cloud'] == true,
      );
      expect(a.hubDrained, isTrue);

      final Map<String, Object?> state = await a.state();
      expect(state['open_orders'], isEmpty);
      expect((state['lan']! as Map<String, Object?>)['cloud'], isTrue);

      await display.close();
      await inA.wait(
        LanMsg.lanState,
        where: (LanEvent e) =>
            (e.payload['devices']! as List<Object?>).length == 2,
      );
    });

    test('a device that connects again replaces its old connection; the hub stopping disconnects everyone', () async {
      final LanClient first = client(
        TestBranch.phone1,
        grant: branch.grant(TestBranch.phone1, 4),
      );
      await first.connect(at());
      final LanClient again = client(
        TestBranch.phone1,
        grant: branch.grant(TestBranch.phone1, 4),
      );
      final Completer<bool> lost = Completer<bool>();
      first.connection.listen((bool c) {
        if (!c && !lost.isCompleted) lost.complete(true);
      });
      await again.connect(at());
      expect(await lost.future.timeout(const Duration(seconds: 5)), isTrue);
      expect(hub.peers, hasLength(1));

      final Completer<bool> stopped = Completer<bool>();
      again.connection.listen((bool c) {
        if (!c && !stopped.isCompleted) stopped.complete(true);
      });
      await hub.stop();
      expect(await stopped.future.timeout(const Duration(seconds: 5)), isTrue);
      expect(again.connected, isFalse);
    });

    test('a device removed from the branch (or a new key) is disconnected when the till takes a new bootstrap', () async {
      final LanClient phone = client(
        TestBranch.phone2,
        grant: branch.grant(TestBranch.phone2, 5),
      );
      await phone.connect(at());
      final Completer<bool> lost = Completer<bool>();
      phone.connection.listen((bool c) {
        if (!c && !lost.isCompleted) lost.complete(true);
      });
      final LanCredentials creds = branch.hubCredentials();
      hub.updateCredentials(
        LanCredentials(
          branchId: creds.branchId,
          keyId: creds.keyId,
          device: creds.device,
          isHub: true,
          deviceKey: creds.deviceKey,
          beaconKey: creds.beaconKey,
          port: creds.port,
          hubDevice: creds.hubDevice,
          hubSecret: creds.hubSecret,
          devices: creds.devices
              .where((LanDeviceInfo d) => d.uuid != TestBranch.phone2)
              .toList(),
        ),
      );
      expect(await lost.future.timeout(const Duration(seconds: 5)), isTrue);
      await expectLater(
        phone.connect(at()),
        throwsA(
          isA<LanUnreachable>().having(
            (LanUnreachable e) => e.reason,
            'reason',
            'unknown_device',
          ),
        ),
      );
    });
  });

  test('the beacon: over HTTP for the IP/QR fallback and by UDP, checked by the device', () async {
    final LanCredentials creds = branch.client(
      TestBranch.phone1,
      port: hub.port,
    );
    final LanBeacon? b = await LanDiscovery.fetch(at(), creds);
    expect(b, isNotNull);
    expect(b!.hubDevice, TestBranch.hub);
    expect(b.port, hub.port);
    // Another branch's device does not take this hub's beacon
    expect(
      await LanDiscovery.fetch(
        at(),
        TestBranch(branchId: 13).client(TestBranch.phone1),
      ),
      isNull,
    );

    // UDP (unicast to loopback here: the till broadcasts on the LAN)
    final RawDatagramSocket probe = await RawDatagramSocket.bind(
      InternetAddress.loopbackIPv4,
      0,
    );
    final int udpPort = probe.port;
    probe.close();
    final Future<LanBeacon?> heard = LanDiscovery.listen(creds, port: udpPort);
    final LanBeaconSender sender = LanBeaconSender(
      beacon: hub.beacon,
      beaconKey: hub.credentials.beaconKey,
      every: const Duration(milliseconds: 100),
      target: InternetAddress.loopbackIPv4,
      port: udpPort,
    );
    await sender.start();
    final LanBeacon? udp = await heard;
    sender.stop();
    expect(udp?.endpoint, LanEndpoint('127.0.0.1', hub.port));
  });
}
