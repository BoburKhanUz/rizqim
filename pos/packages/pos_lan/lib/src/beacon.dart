import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'credentials.dart';
import 'crypto.dart';

/// Where a hub listens.
class LanEndpoint {
  const LanEndpoint(this.host, this.port);

  /// `rizqim-lan://192.168.1.20:18480?b=12&h=<uuid>` (the QR in the hub's settings) or `192.168.1.20[:18480]`.
  static LanEndpoint? parse(String text, {int defaultPort = 18480}) {
    final String t = text.trim();
    final Uri? uri = Uri.tryParse(t.contains('://') ? t : 'rizqim-lan://$t');
    if (uri == null || uri.host.isEmpty) return null;
    return LanEndpoint(uri.host, uri.hasPort ? uri.port : defaultPort);
  }

  final String host;
  final int port;

  Uri get ws => Uri(scheme: 'ws', host: host, port: port, path: '/lan/v1/ws');

  Uri get beacon =>
      Uri(scheme: 'http', host: host, port: port, path: '/lan/v1/beacon');

  @override
  bool operator ==(Object other) =>
      other is LanEndpoint && other.host == host && other.port == port;

  @override
  int get hashCode => Object.hash(host, port);

  @override
  String toString() => '$host:$port';
}

/// The hub's announcement (README §4): branch, hub device, address, time, signed with the beacon key.
class LanBeacon {
  const LanBeacon({
    required this.branchId,
    required this.keyId,
    required this.hubDevice,
    required this.host,
    required this.port,
    required this.ts,
  });

  final int branchId;
  final int keyId;
  final String hubDevice;
  final String host;
  final int port;

  /// The hub's clock (ms since epoch).
  final int ts;

  LanEndpoint get endpoint => LanEndpoint(host, port);

  /// The QR / manual fallback text.
  String get link => 'rizqim-lan://$host:$port?b=$branchId&h=$hubDevice';

  Map<String, Object?> _unsigned() => <String, Object?>{
    'v': 1,
    'type': 'lan.beacon',
    'branch_id': branchId,
    'key_id': keyId,
    'hub_device': hubDevice,
    'host': host,
    'port': port,
    'ts': ts,
  };

  /// The signed JSON (UDP datagram, GET /lan/v1/beacon).
  String sign(List<int> beaconKey) {
    final Map<String, Object?> j = _unsigned();
    j['sig'] = base64.encode(
      LanKeys.hmac(beaconKey, utf8.encode(lanCanonicalJson(j))),
    );
    return jsonEncode(j);
  }

  /// The beacon in [text] if it is signed with this terminal's beacon key, is its branch's and its hub's, and its
  /// time is within [window] of [now] (the device's own clock: beacons only find the hub; the handshake proves it).
  static LanBeacon? verify(
    String text,
    LanCredentials credentials, {
    required DateTime now,
    Duration window = const Duration(minutes: 10),
  }) {
    Object? json;
    try {
      json = jsonDecode(text);
    } on FormatException {
      return null;
    }
    if (json is! Map) return null;
    final Map<String, Object?> j = json.cast<String, Object?>();
    final Uint8List? sig = lanFromBase64(j['sig']);
    final Map<String, Object?> unsigned = <String, Object?>{
      for (final MapEntry<String, Object?> e in j.entries)
        if (e.key != 'sig') e.key: e.value,
    };
    final Uint8List expected = LanKeys.hmac(
      credentials.beaconKey,
      utf8.encode(lanCanonicalJson(unsigned)),
    );
    if (sig == null || !lanSameBytes(sig, expected)) return null;
    if (j['v'] != 1 ||
        j['type'] != 'lan.beacon' ||
        j['branch_id'] != credentials.branchId ||
        j['hub_device'] is! String ||
        j['host'] is! String ||
        j['port'] is! int ||
        j['ts'] is! int) {
      return null;
    }
    final String hub = (j['hub_device']! as String).toLowerCase();
    if (credentials.hubDevice != null && hub != credentials.hubDevice) {
      return null;
    }
    final int ts = j['ts']! as int;
    if ((now.millisecondsSinceEpoch - ts).abs() > window.inMilliseconds) {
      return null;
    }
    return LanBeacon(
      branchId: credentials.branchId,
      keyId: (j['key_id'] as num?)?.toInt() ?? credentials.keyId,
      hubDevice: hub,
      host: j['host']! as String,
      port: j['port']! as int,
      ts: ts,
    );
  }
}

/// Finding the hub: the UDP beacon, or the address typed in or scanned (README §4).
abstract final class LanDiscovery {
  /// Listens on [port] for a beacon of this branch's hub, at most [timeout]: the first one ([collect] keeps them all,
  /// what a device's [LanLink] uses).
  static Future<LanBeacon?> listen(
    LanCredentials credentials, {
    int? port,
    Duration timeout = const Duration(seconds: 5),
    DateTime Function()? clock,
  }) async {
    RawDatagramSocket? socket;
    try {
      socket = await RawDatagramSocket.bind(
        InternetAddress.anyIPv4,
        port ?? credentials.port,
        reuseAddress: true,
      );
    } on SocketException {
      return null;
    }
    final Completer<LanBeacon?> found = Completer<LanBeacon?>();
    final StreamSubscription<RawSocketEvent> sub = socket.listen((
      RawSocketEvent e,
    ) {
      if (e != RawSocketEvent.read) return;
      final Datagram? d = socket!.receive();
      if (d == null || found.isCompleted) return;
      String text;
      try {
        text = utf8.decode(d.data);
      } on FormatException {
        return;
      }
      final LanBeacon? b = LanBeacon.verify(
        text,
        credentials,
        now: (clock ?? DateTime.now)(),
      );
      if (b != null) found.complete(b);
    });
    final Timer timer = Timer(timeout, () {
      if (!found.isCompleted) found.complete(null);
    });
    try {
      return await found.future;
    } finally {
      timer.cancel();
      await sub.cancel();
      socket.close();
    }
  }

  /// Review P6 S3: every valid beacon of this branch's hub heard on [port] within [timeout] (the whole window: the
  /// hub sends one every 2 s), each address once, in the order heard, at most [max]. A device tries each in turn, so a
  /// beacon replayed from elsewhere (or the hub's old address) does not keep it from the hub.
  static Future<List<LanBeacon>> collect(
    LanCredentials credentials, {
    int? port,
    Duration timeout = const Duration(seconds: 3),
    DateTime Function()? clock,
    int max = 8,
  }) async {
    RawDatagramSocket? socket;
    try {
      socket = await RawDatagramSocket.bind(
        InternetAddress.anyIPv4,
        port ?? credentials.port,
        reuseAddress: true,
      );
    } on SocketException {
      return const <LanBeacon>[];
    }
    final List<LanBeacon> found = <LanBeacon>[];
    final StreamSubscription<RawSocketEvent> sub = socket.listen((
      RawSocketEvent e,
    ) {
      if (e != RawSocketEvent.read) return;
      final Datagram? d = socket!.receive();
      if (d == null || found.length >= max) return;
      String text;
      try {
        text = utf8.decode(d.data);
      } on FormatException {
        return;
      }
      final LanBeacon? b = LanBeacon.verify(
        text,
        credentials,
        now: (clock ?? DateTime.now)(),
      );
      if (b != null && !found.any((LanBeacon f) => f.endpoint == b.endpoint)) {
        found.add(b);
      }
    });
    try {
      await Future<void>.delayed(timeout);
      return List<LanBeacon>.unmodifiable(found);
    } finally {
      await sub.cancel();
      socket.close();
    }
  }

  /// The beacon at [endpoint] (the manual IP or QR fallback): GET /lan/v1/beacon, checked like a broadcast one.
  static Future<LanBeacon?> fetch(
    LanEndpoint endpoint,
    LanCredentials credentials, {
    Duration timeout = const Duration(seconds: 3),
    DateTime Function()? clock,
  }) async {
    final HttpClient http = HttpClient()..connectionTimeout = timeout;
    try {
      final HttpClientRequest req = await http
          .getUrl(endpoint.beacon)
          .timeout(timeout);
      final HttpClientResponse res = await req.close().timeout(timeout);
      if (res.statusCode != 200) return null;
      final String text = await utf8.decodeStream(res).timeout(timeout);
      return LanBeacon.verify(
        text,
        credentials,
        now: (clock ?? DateTime.now)(),
      );
    } on Object {
      return null;
    } finally {
      http.close(force: true);
    }
  }
}

/// The hub's UDP beacon every [every] (README §4).
class LanBeaconSender {
  LanBeaconSender({
    required this.beacon,
    required this.beaconKey,
    this.every = const Duration(seconds: 2),
    InternetAddress? target,
    this.port = 18480,
  }) : target = target ?? InternetAddress('255.255.255.255');

  /// The beacon to send now (its time and address change).
  final LanBeacon Function() beacon;
  final List<int> beaconKey;
  final Duration every;
  final InternetAddress target;
  final int port;

  RawDatagramSocket? _socket;
  Timer? _timer;

  bool get running => _timer != null;

  Future<void> start() async {
    if (_timer != null) return;
    try {
      _socket = await RawDatagramSocket.bind(InternetAddress.anyIPv4, 0)
        ..broadcastEnabled = true;
    } on SocketException {
      return;
    }
    _send();
    _timer = Timer.periodic(every, (_) => _send());
  }

  void _send() {
    try {
      _socket?.send(utf8.encode(beacon().sign(beaconKey)), target, port);
    } on SocketException {
      // No network right now: the next tick tries again
    }
  }

  void stop() {
    _timer?.cancel();
    _timer = null;
    _socket?.close();
    _socket = null;
  }
}
