import 'dart:async';
import 'dart:io';
import 'dart:math';

import 'beacon.dart';
import 'credentials.dart';
import 'crypto.dart';
import 'frame.dart';
import 'grant.dart';
import 'messages.dart';

/// A device connected to the hub (after its `hello`).
class LanPeerInfo {
  const LanPeerInfo({
    required this.device,
    required this.type,
    required this.since,
    this.name,
    this.app,
    this.staff,
  });

  final String device;
  final String type;
  final String? name;
  final LanApp? app;

  /// Who is signed in there (from its grant), `null` without one.
  final LanGrant? staff;
  final DateTime since;

  Map<String, Object?> toJson() => <String, Object?>{
    'uuid': device,
    'type': type,
    'name': name,
    'app': app?.wire,
    'staff': staff?.summary,
  };
}

/// The main till's side of the bridge (README): an HTTP + WebSocket server on the LAN (`/lan/v1/ws`, the beacon at
/// `/lan/v1/beacon`) and the UDP beacon. Every frame is checked (device of the branch, signature, key version, time
/// window, `(device, seq)` once); operations go through [applier] one at a time in arrival order; what changes goes to
/// every connected device.
class LanHub {
  LanHub({
    required LanCredentials credentials,
    required this.applier,
    DateTime Function()? clock,
    this._random,
    InternetAddress? address,
    int? port,
    this.beaconEvery = const Duration(seconds: 2),
    this._beaconTarget,
    this._beaconPort,
    this.advertisedHost,
    this.helloWindow = const Duration(minutes: 10),
    this.helloTimeout = const Duration(seconds: 10),
    this.maxPeers = 64,
  }) : _credentials = credentials,
       _clock = clock ?? DateTime.now,
       _address = address ?? InternetAddress.anyIPv4,
       _port = port ?? credentials.port {
    if (!credentials.isHub) {
      throw ArgumentError('Bu terminal filialning markazi emas.');
    }
    _seq = LanSequence(_clock());
    _replay = LanReplayGuard(window: credentials.clockWindow);
  }

  final LanOpApplier applier;
  final DateTime Function() _clock;
  final Random? _random;
  final InternetAddress _address;
  final int _port;
  final InternetAddress? _beaconTarget;
  final int? _beaconPort;

  /// `null`: no UDP beacon (tests, or a network that blocks broadcast: the IP/QR fallback).
  final Duration? beaconEvery;

  /// The address put in the beacon (`null`: the first IPv4 address of this machine).
  final String? advertisedHost;
  final Duration helloWindow;
  final Duration helloTimeout;
  final int maxPeers;

  LanCredentials _credentials;
  late final LanSequence _seq;
  late LanReplayGuard _replay;
  HttpServer? _server;
  LanBeaconSender? _beacon;
  String? _host;
  final Set<_Peer> _peers = <_Peer>{};
  final List<StreamSubscription<Object?>> _subs =
      <StreamSubscription<Object?>>[];
  final StreamController<void> _changes = StreamController<void>.broadcast();
  Future<void> _ops = Future<void>.value();

  /// Frames refused after the handshake (replays, stale ones), for the logs.
  int refusedFrames = 0;

  LanCredentials get credentials => _credentials;

  bool get running => _server != null;

  /// The port it listens on (the configured one, or the one the system gave for port 0).
  int get port => _server?.port ?? _port;

  /// The address devices reach it on (the beacon's).
  String get host => _host ?? advertisedHost ?? '127.0.0.1';

  /// Devices connected now ("Lokal tarmoq: 3 qurilma").
  List<LanPeerInfo> get peers => <LanPeerInfo>[
    for (final _Peer p in _peers)
      if (p.info != null) p.info!,
  ];

  Stream<void> get changes => _changes.stream;

  /// The beacon as it is sent now.
  LanBeacon beacon() => LanBeacon(
    branchId: _credentials.branchId,
    keyId: _credentials.keyId,
    hubDevice: _credentials.device,
    host: host,
    port: port,
    ts: _clock().millisecondsSinceEpoch,
  );

  Future<void> start() async {
    if (_server != null) return;
    final HttpServer server = await HttpServer.bind(
      _address,
      _port,
      shared: true,
    );
    _server = server;
    _host = advertisedHost ?? await _localAddress();
    server.listen(_onRequest, onError: (Object _) {});
    _subs
      ..add(applier.events.listen(_broadcastEvent))
      ..add(applier.statusChanges.listen((_) => _broadcastState()));
    if (beaconEvery != null) {
      _beacon = LanBeaconSender(
        beacon: beacon,
        beaconKey: _credentials.beaconKey,
        every: beaconEvery!,
        target: _beaconTarget,
        port: _beaconPort ?? port,
      );
      await _beacon!.start();
    }
    _emit();
  }

  Future<void> stop() async {
    _beacon?.stop();
    _beacon = null;
    for (final StreamSubscription<Object?> s in _subs) {
      await s.cancel();
    }
    _subs.clear();
    for (final _Peer p in _peers.toList()) {
      await p.close(LanClose.goingAway, 'hub_stopped');
    }
    _peers.clear();
    await _server?.close(force: true);
    _server = null;
    _emit();
  }

  /// New credentials from a later bootstrap (the device list or the key changed): devices no longer let in, or on
  /// another key version, are disconnected.
  void updateCredentials(LanCredentials credentials) {
    if (!credentials.isHub) return;
    final bool keyChanged = credentials.keyId != _credentials.keyId;
    _credentials = credentials;
    if (keyChanged) _replay = LanReplayGuard(window: credentials.clockWindow);
    for (final _Peer p in _peers.toList()) {
      if (keyChanged) {
        unawaited(p.close(LanClose.unauthorized, 'key_mismatch'));
      } else if (p.device != null && credentials.member(p.device!) == null) {
        unawaited(p.close(LanClose.unauthorized, 'unknown_device'));
      }
    }
  }

  /// Sends [type] to every connected device (or only [to]): `cart.display`, `queue.*` and others the till publishes.
  void publish(String type, Map<String, Object?> payload, {String? to}) {
    for (final _Peer p in _peers) {
      if (p.info == null) continue;
      if (to != null && p.device != to.toLowerCase()) continue;
      _send(p, type, payload);
    }
  }

  // --- HTTP ---------------------------------------------------------------------------------------------------------

  Future<void> _onRequest(HttpRequest request) async {
    try {
      final String path = request.uri.path;
      if (path == '/lan/v1/ws' &&
          WebSocketTransformer.isUpgradeRequest(request)) {
        if (_peers.length >= maxPeers) {
          request.response.statusCode = HttpStatus.serviceUnavailable;
          await request.response.close();
          return;
        }
        final WebSocket ws = await WebSocketTransformer.upgrade(request);
        ws.pingInterval = const Duration(seconds: 10);
        _accept(ws);
        return;
      }
      if (path == '/lan/v1/beacon' && request.method == 'GET') {
        final String host = request.headers.host ?? this.host;
        final LanBeacon b = beacon();
        request.response
          ..statusCode = HttpStatus.ok
          ..headers.contentType = ContentType.json
          ..write(
            LanBeacon(
              branchId: b.branchId,
              keyId: b.keyId,
              hubDevice: b.hubDevice,
              host: host,
              port: b.port,
              ts: b.ts,
            ).sign(_credentials.beaconKey),
          );
        await request.response.close();
        return;
      }
      request.response.statusCode = HttpStatus.notFound;
      await request.response.close();
    } on Object {
      // A broken request: nothing to answer
    }
  }

  void _accept(WebSocket ws) {
    final _Peer peer = _Peer(ws, _clock());
    _peers.add(peer);
    peer.helloTimer = Timer(helloTimeout, () {
      if (peer.info == null) {
        unawaited(peer.close(LanClose.badFrame, 'hello_timeout'));
      }
    });
    ws.listen(
      (Object? data) => _onFrame(peer, data),
      onDone: () => _gone(peer),
      onError: (Object _) => _gone(peer),
      cancelOnError: true,
    );
  }

  void _gone(_Peer peer) {
    peer.helloTimer?.cancel();
    if (!_peers.remove(peer)) return;
    if (peer.info != null) {
      _broadcastState();
      _emit();
    }
  }

  // --- frames -------------------------------------------------------------------------------------------------------

  void _onFrame(_Peer peer, Object? data) {
    final LanFrame frame;
    try {
      frame = _open(peer, data);
    } on LanFrameException catch (e) {
      if (peer.info == null ||
          e.closeCode != LanClose.badFrame ||
          e.reason == 'too_big') {
        unawaited(peer.close(e.closeCode, e.reason));
      } else {
        // A replayed, stale or broken frame on a good connection is dropped, the device is told
        refusedFrames++;
        _send(peer, LanMsg.error, <String, Object?>{'reason': e.reason});
      }
      return;
    }
    if (peer.info == null) {
      _hello(peer, frame);
      return;
    }
    switch (frame.type) {
      case LanMsg.op:
        _ops = _ops.then((_) => _op(peer, frame)).catchError((Object _) {});
      case LanMsg.stateGet:
        _send(peer, LanMsg.state, _statePayload());
      case LanMsg.orderGet:
        final String? ref = frame.payload['order'] as String?;
        final Map<String, Object?>? order = ref == null
            ? null
            : applier.order(ref);
        if (order == null) {
          _send(peer, LanMsg.error, <String, Object?>{
            'reason': 'not_found',
            'message': 'Buyurtma kassada topilmadi.',
            'order': ref,
          });
        } else {
          _send(peer, LanMsg.orderChanged, <String, Object?>{'order': order});
        }
      case LanMsg.grant:
        _takeGrant(peer, frame.payload['grant'], frame.device);
        _send(peer, LanMsg.welcome, _welcome(peer, frame.payload['nonce']));
        _broadcastState();
      case LanMsg.ping:
        _send(peer, LanMsg.pong, <String, Object?>{'hub_time': _now()});
      default:
        _send(peer, LanMsg.error, <String, Object?>{
          'reason': 'unknown_type',
          'message': "Noma'lum xabar: ${frame.type}.",
        });
    }
  }

  /// Opens and checks one frame of [peer]: shape, key version, device of the branch (the same one after `hello`),
  /// signature, then time window and `(device, seq)` once.
  LanFrame _open(_Peer peer, Object? data) {
    final Map<String, Object?> envelope = LanFrameCodec.envelope(data);
    if (envelope['kid'] != _credentials.keyId) {
      throw const LanFrameException('key_mismatch', LanClose.unauthorized);
    }
    final String device = (envelope['device']! as String).toLowerCase();
    if (peer.device != null && device != peer.device) {
      throw const LanFrameException('device_changed', LanClose.unauthorized);
    }
    final LanDeviceInfo? member = _credentials.member(device);
    if (member == null) {
      throw const LanFrameException('unknown_device', LanClose.unauthorized);
    }
    final LanChannelKey key =
        peer.key ?? LanChannelKey(_credentials.keyFor(device)!);
    final LanFrame frame = LanFrameCodec.open(envelope, key);
    final String? replay = _replay.check(
      device,
      frame.seq,
      frame.ts,
      _now(),
      window: peer.info == null ? helloWindow : null,
    );
    if (replay != null) throw LanFrameException(replay, LanClose.badFrame);
    if (peer.info == null && frame.type != LanMsg.hello) {
      throw const LanFrameException('hello_required', LanClose.badFrame);
    }
    peer
      ..device = device
      ..key = key
      ..member = member;
    return frame;
  }

  void _hello(_Peer peer, LanFrame frame) {
    peer.helloTimer?.cancel();
    // One connection per device: an older one (a phone that roamed between access points) gives way
    for (final _Peer other in _peers.toList()) {
      if (!identical(other, peer) && other.device == peer.device) {
        unawaited(other.close(LanClose.goingAway, 'replaced'));
        _peers.remove(other);
      }
    }
    peer.app = LanApp.fromWire(frame.payload['app']);
    _takeGrant(peer, frame.payload['grant'], frame.device);
    peer.info = LanPeerInfo(
      device: frame.device,
      type: peer.member!.type,
      name: peer.member!.name,
      app: peer.app,
      staff: peer.grant,
      since: _clock(),
    );
    _send(peer, LanMsg.welcome, _welcome(peer, frame.payload['nonce']));
    _broadcastState();
    _emit();
  }

  void _takeGrant(_Peer peer, Object? token, String device) {
    peer
      ..grant = null
      ..grantError = null;
    if (token is String && token.isNotEmpty) {
      final List<int>? key = _credentials.grantKey;
      final (LanGrant? grant, LanGrantError? error) = key == null
          ? (null, LanGrantError.badSignature)
          : LanGrant.verify(
              token,
              grantKey: key,
              branchId: _credentials.branchId,
              keyId: _credentials.keyId,
              device: device,
              now: _clock(),
            );
      peer
        ..grant = grant
        ..grantError = error?.reason;
    }
    if (peer.info != null) {
      peer.info = LanPeerInfo(
        device: peer.info!.device,
        type: peer.info!.type,
        name: peer.info!.name,
        app: peer.app,
        staff: peer.grant,
        since: peer.info!.since,
      );
    }
  }

  Map<String, Object?> _welcome(_Peer peer, Object? nonce) => <String, Object?>{
    'nonce': nonce,
    'hub_time': _now(),
    'hub': <String, Object?>{
      'device': _credentials.device,
      'name': _credentials.hubName,
    },
    'staff': peer.grant?.summary,
    'grant_error': peer.grantError,
    'state': _lanState(),
  };

  Future<void> _op(_Peer peer, LanFrame frame) async {
    final Map<String, Object?> p = frame.payload;
    final Object? id = p['op_id'];
    final Object? type = p['type'];
    final Object? payload = p['payload'];
    if (id is! String || id.isEmpty || type is! String || payload is! Map) {
      _send(
        peer,
        LanMsg.opResult,
        LanOpResult.refused(
          id is String ? id : '',
          'invalid_payload',
          "Amal ma'lumotlari noto'g'ri.",
        ).toJson(),
      );
      return;
    }
    final LanGrant? grant = peer.grant;
    LanOpResult result;
    if (grant == null) {
      result = LanOpResult.refused(
        id,
        'grant_required',
        'Qurilmada xodim kirmagan (yoki kirish eskirgan).',
      );
    } else if (!_clock().isBefore(grant.expiresAt)) {
      result = LanOpResult.refused(
        id,
        'grant_expired',
        'Xodim kirishi eskirgan: internet bilan qayta kiring.',
      );
    } else if ((p['staff_id'] as num?)?.toInt() != grant.staffId) {
      result = LanOpResult.refused(
        id,
        'staff_mismatch',
        'Amaldagi xodim qurilmada kirgan xodim emas.',
      );
    } else if (!LanOpType.allowed(type)) {
      result = LanOpResult.refused(
        id,
        'not_allowed_on_lan',
        "Bu amal faqat kassada (to'lov, schyot, yopish) yoki internet bilan.",
      );
    } else {
      final String? at = p['occurred_at'] as String?;
      try {
        result = await applier.apply(
          LanRemoteOp(
            opId: id.toLowerCase(),
            type: type,
            device: frame.device,
            staffId: grant.staffId,
            occurredAt: at == null ? null : DateTime.tryParse(at),
            payload: payload.cast<String, Object?>(),
          ),
          grant,
        );
      } on Object {
        result = LanOpResult.refused(
          id,
          'hub_error',
          'Kassada xato: amal qabul qilinmadi, qayta urinib ko\'ring.',
        );
      }
    }
    if (_peers.contains(peer)) _send(peer, LanMsg.opResult, result.toJson());
  }

  // --- out ----------------------------------------------------------------------------------------------------------

  void _send(_Peer peer, String type, Map<String, Object?> payload) {
    final LanChannelKey? key = peer.key;
    if (key == null) return;
    try {
      peer.ws.add(
        LanFrameCodec.seal(
          LanFrame(
            type: type,
            device: _credentials.device,
            seq: _seq.take(),
            ts: _now(),
            keyId: _credentials.keyId,
            payload: payload,
          ),
          key,
          random: _random,
        ),
      );
    } on Object {
      // The socket is closing: its onDone removes it
    }
  }

  void _broadcastEvent(LanEvent e) => publish(e.type, e.payload);

  void _broadcastState() => publish(LanMsg.lanState, _lanState());

  Map<String, Object?> _lanState() => <String, Object?>{
    'cloud': applier.cloud,
    'pending': applier.pending,
    'devices': <Object?>[for (final LanPeerInfo p in peers) p.toJson()],
    'hub_time': _now(),
  };

  Map<String, Object?> _statePayload() => <String, Object?>{
    'lan': _lanState(),
    ...applier.state(),
  };

  int _now() => _clock().millisecondsSinceEpoch;

  void _emit() {
    if (!_changes.isClosed) _changes.add(null);
  }

  static Future<String?> _localAddress() async {
    try {
      for (final NetworkInterface i in await NetworkInterface.list(
        type: InternetAddressType.IPv4,
      )) {
        for (final InternetAddress a in i.addresses) {
          if (!a.isLoopback) return a.address;
        }
      }
    } on Object {
      // No interfaces listed: the IP fallback is typed in
    }
    return null;
  }

  Future<void> dispose() async {
    await stop();
    await _changes.close();
  }
}

class _Peer {
  _Peer(this.ws, this.connectedAt);

  final WebSocket ws;
  final DateTime connectedAt;
  String? device;
  LanChannelKey? key;
  LanDeviceInfo? member;
  LanPeerInfo? info;
  LanApp? app;
  LanGrant? grant;
  String? grantError;
  Timer? helloTimer;

  Future<void> close(int code, String reason) async {
    helloTimer?.cancel();
    try {
      await ws.close(code, reason).timeout(const Duration(seconds: 2));
    } on Object {
      // Already gone
    }
  }
}
