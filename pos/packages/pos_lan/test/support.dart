import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:pos_lan/pos_lan.dart';

/// A branch's LAN as the server would hand it out: one secret, the hub's section and each device's.
class TestBranch {
  TestBranch({this.branchId = 12, this.keyId = 1, Uint8List? secret})
    : secret =
          secret ??
          Uint8List.fromList(List<int>.generate(32, (int i) => 200 - i));

  final int branchId;
  final int keyId;
  final Uint8List secret;

  static const String hub = 'aaaaaaaa-0000-4000-8000-000000000001';
  static const String phone1 = 'bbbbbbbb-0000-4000-8000-000000000002';
  static const String phone2 = 'bbbbbbbb-0000-4000-8000-000000000003';
  static const String kds = 'cccccccc-0000-4000-8000-000000000004';
  static const String display = 'dddddddd-0000-4000-8000-000000000005';
  static const String stranger = 'eeeeeeee-0000-4000-8000-000000000006';

  LanCredentials hubCredentials({int port = 0}) => LanCredentials(
    branchId: branchId,
    keyId: keyId,
    device: hub,
    isHub: true,
    deviceKey: LanKeys.device(secret, hub),
    beaconKey: LanKeys.beacon(secret),
    port: port,
    hubDevice: hub,
    hubName: 'Kassa 1',
    hubSecret: secret,
    devices: const <LanDeviceInfo>[
      LanDeviceInfo(uuid: hub, type: 'pos', name: 'Kassa 1'),
      LanDeviceInfo(uuid: phone1, type: 'pos', name: 'Ofitsiant 1'),
      LanDeviceInfo(uuid: phone2, type: 'pos', name: 'Ofitsiant 2'),
      LanDeviceInfo(uuid: kds, type: 'kds', name: 'Oshxona'),
      LanDeviceInfo(uuid: display, type: 'display', name: 'Ekran'),
    ],
  );

  LanCredentials client(
    String device, {
    int port = 0,
    String? grant,
    int? keyId,
    Uint8List? key,
  }) => LanCredentials(
    branchId: branchId,
    keyId: keyId ?? this.keyId,
    device: device,
    isHub: false,
    deviceKey: key ?? LanKeys.device(secret, device),
    beaconKey: LanKeys.beacon(secret),
    port: port,
    hubDevice: hub,
    hubName: 'Kassa 1',
    grant: grant,
  );

  /// A grant as the server signs it.
  String grant(
    String device,
    int staffId, {
    String role = 'waiter',
    String? name,
    List<String> permissions = const <String>['orders.view', 'orders.manage'],
    DateTime? issuedAt,
    int? branch,
    int? kid,
  }) {
    final DateTime iat = issuedAt ?? DateTime.now();
    return LanGrant.sign(<String, Object?>{
      'v': 1,
      'kid': kid ?? keyId,
      'branch_id': branch ?? branchId,
      'device': device,
      'staff_id': staffId,
      'name': name ?? 'Xodim $staffId',
      'role': role,
      'permissions': permissions,
      'session': 1,
      'iat': iat.millisecondsSinceEpoch ~/ 1000,
      'exp': iat.add(const Duration(hours: 36)).millisecondsSinceEpoch ~/ 1000,
    }, LanKeys.grant(secret));
  }
}

/// A raw WebSocket to the hub that seals frames by hand (to send what a real client never would).
class RawPeer {
  RawPeer._(this.ws, this.device, this.key, this.keyId) {
    ws.listen((Object? d) {
      // The hub's challenge (README §5) comes first, unsigned: kept for the hello, not a frame
      if (!_challenged.isCompleted && d is String) {
        final Object? j = jsonDecode(d);
        if (j is Map && j['type'] == 'challenge') {
          _challenged.complete(j['nonce'] as String?);
          return;
        }
      }
      _incoming.add(d);
    }, onDone: () => _incoming.close());
  }

  static Future<RawPeer> connect(
    int port,
    String device,
    List<int> deviceKey, {
    int keyId = 1,
  }) async {
    final WebSocket ws = await WebSocket.connect(
      'ws://127.0.0.1:$port/lan/v1/ws',
    );
    final RawPeer p = RawPeer._(ws, device, LanChannelKey(deviceKey), keyId);
    p.challenge = await p._challenged.future.timeout(
      const Duration(seconds: 2),
      onTimeout: () => null,
    );
    return p;
  }

  final WebSocket ws;
  final String device;
  final LanChannelKey key;
  final int keyId;
  final Completer<String?> _challenged = Completer<String?>();

  /// The hub's challenge on this connection (a `hello` sealed by [seal] carries it unless it names its own).
  String? challenge;
  final StreamController<Object?> _incoming = StreamController<Object?>();
  late final StreamIterator<Object?> _it = StreamIterator<Object?>(
    _incoming.stream,
  );
  int _seq = DateTime.now().microsecondsSinceEpoch;

  String seal(String type, Map<String, Object?> payload, {int? seq, int? ts}) =>
      LanFrameCodec.seal(
        LanFrame(
          type: type,
          device: device,
          seq: seq ?? _seq++,
          ts: ts ?? DateTime.now().millisecondsSinceEpoch,
          keyId: keyId,
          payload: type == LanMsg.hello && !payload.containsKey('challenge')
              ? <String, Object?>{...payload, 'challenge': challenge}
              : payload,
        ),
        key,
      );

  void sendRaw(String text) => ws.add(text);

  /// The next frame from the hub, opened (`null` when the socket closed).
  Future<LanFrame?> next({
    Duration timeout = const Duration(seconds: 5),
  }) async {
    if (!await _it.moveNext().timeout(timeout)) return null;
    return LanFrameCodec.open(LanFrameCodec.envelope(_it.current), key);
  }

  /// The next frame of [type], skipping others (`lan.state` broadcasts).
  Future<LanFrame> nextOf(String type) async {
    while (true) {
      final LanFrame? f = await next();
      if (f == null) throw StateError('closed waiting for $type');
      if (f.type == type) return f;
    }
  }

  Future<(int?, String?)> closed() async {
    while (await _it.moveNext().timeout(const Duration(seconds: 5))) {}
    return (ws.closeCode, ws.closeReason);
  }

  Future<void> close() => ws.close();
}

/// Collects a client's events.
class Inbox {
  Inbox(Stream<LanEvent> events) {
    _sub = events.listen(seen.add);
  }

  final List<LanEvent> seen = <LanEvent>[];
  late final StreamSubscription<LanEvent> _sub;

  Iterable<LanEvent> of(String type) =>
      seen.where((LanEvent e) => e.type == type);

  Future<LanEvent> wait(
    String type, {
    bool Function(LanEvent e)? where,
    Duration timeout = const Duration(seconds: 5),
  }) async {
    final DateTime until = DateTime.now().add(timeout);
    while (DateTime.now().isBefore(until)) {
      for (final LanEvent e in seen) {
        if (e.type == type && (where == null || where(e))) return e;
      }
      await Future<void>.delayed(const Duration(milliseconds: 10));
    }
    throw TimeoutException('no $type event');
  }

  Future<void> cancel() => _sub.cancel();
}

String pretty(Object? v) => const JsonEncoder.withIndent(' ').convert(v);
