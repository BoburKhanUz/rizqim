import 'dart:async';
import 'dart:io';

import 'package:pos_lan/pos_lan.dart';
import 'package:test/test.dart';

import 'support.dart';

/// P6 Windows: the hub as the till runs it (every IPv4 interface, the UDP beacon with broadcast on) and a device on
/// the same PC (the customer display on the till's second monitor) reaching it at 127.0.0.1. Runs on Linux here and
/// on Windows in the CI `windows-build` job, where socket options differ (SO_REUSEADDR, broadcast, loopback).
void main() {
  late TestBranch branch;

  setUp(() => branch = TestBranch());

  test('the hub binds every interface; a device on the same PC joins it at 127.0.0.1', () async {
    final LanHub hub = LanHub(
      credentials: branch.hubCredentials(),
      applier: _IdleApplier(),
      port: 0,
      beaconEvery: null,
      advertisedHost: '192.168.1.20',
    );
    await hub.start();
    addTearDown(hub.dispose);
    expect(hub.port, greaterThan(0));
    // the beacon names the LAN address; the display on the same PC uses loopback (the firewall never applies there)
    expect(hub.beacon().endpoint, LanEndpoint('192.168.1.20', hub.port));

    final LanEndpoint local = LanEndpoint('127.0.0.1', hub.port);
    final LanCredentials creds = branch.client(
      TestBranch.display,
      port: hub.port,
    );
    final LanBeacon? fetched = await LanDiscovery.fetch(local, creds);
    expect(fetched?.hubDevice, TestBranch.hub);

    final LanClient display = LanClient(
      credentials: creds,
      app: LanApp.display,
    );
    addTearDown(display.dispose);
    final LanWelcome welcome = await display.connect(local);
    expect(welcome.hubDevice, TestBranch.hub);
    await _until(() => hub.peers.length == 1);
    expect(hub.peers.single.device, TestBranch.display);
  });

  test('the hub\'s own beacon (broadcast enabled) is heard by a listener on the same machine', () async {
    final RawDatagramSocket probe = await RawDatagramSocket.bind(
      InternetAddress.loopbackIPv4,
      0,
    );
    final int udpPort = probe.port;
    probe.close();
    final LanCredentials creds = branch.client(TestBranch.kds);
    final Future<LanBeacon?> heard = LanDiscovery.listen(
      creds,
      port: udpPort,
      timeout: const Duration(seconds: 5),
    );
    final LanHub hub = LanHub(
      credentials: branch.hubCredentials(),
      applier: _IdleApplier(),
      port: 0,
      beaconEvery: const Duration(milliseconds: 100),
      beaconTarget: InternetAddress.loopbackIPv4,
      beaconPort: udpPort,
      advertisedHost: '127.0.0.1',
    );
    await hub.start();
    addTearDown(hub.dispose);
    final LanBeacon? b = await heard;
    expect(b?.endpoint, LanEndpoint('127.0.0.1', hub.port));
  });

  test('two devices on one PC (KDS and display) can both listen for the beacon port', () async {
    final RawDatagramSocket probe = await RawDatagramSocket.bind(
      InternetAddress.loopbackIPv4,
      0,
    );
    final int udpPort = probe.port;
    probe.close();
    // Both bind the same port (reuseAddress): neither gives up at once with "no beacon"
    final Stopwatch sw = Stopwatch()..start();
    final List<LanBeacon?> both = await Future.wait(<Future<LanBeacon?>>[
      LanDiscovery.listen(
        branch.client(TestBranch.kds),
        port: udpPort,
        timeout: const Duration(milliseconds: 600),
      ),
      LanDiscovery.listen(
        branch.client(TestBranch.display),
        port: udpPort,
        timeout: const Duration(milliseconds: 600),
      ),
    ]);
    expect(both, <LanBeacon?>[null, null]);
    expect(sw.elapsedMilliseconds, greaterThanOrEqualTo(500));
  });
}

Future<void> _until(bool Function() ok) async {
  final DateTime until = DateTime.now().add(const Duration(seconds: 5));
  while (!ok()) {
    if (DateTime.now().isAfter(until)) throw TimeoutException('condition');
    await Future<void>.delayed(const Duration(milliseconds: 10));
  }
}

/// A hub applier with nothing to apply (this file checks the sockets only).
class _IdleApplier implements LanOpApplier {
  final StreamController<LanEvent> _events =
      StreamController<LanEvent>.broadcast();
  final StreamController<void> _status = StreamController<void>.broadcast();

  @override
  Future<LanOpResult> apply(LanRemoteOp op, LanGrantView staff) async =>
      LanOpResult.refused(op.opId, 'not_here', 'Sinov markazi.');

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
  bool cloud = true;

  @override
  int pending = 0;

  @override
  Stream<void> get statusChanges => _status.stream;
}
