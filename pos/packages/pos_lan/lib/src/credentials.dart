import 'dart:convert';
import 'dart:typed_data';

import 'crypto.dart';

/// A terminal of the branch the hub lets in (bootstrap `lan.devices`, the hub only).
class LanDeviceInfo {
  const LanDeviceInfo({required this.uuid, required this.type, this.name});

  factory LanDeviceInfo.fromJson(Map<String, Object?> j) => LanDeviceInfo(
    uuid: (j['uuid']! as String).toLowerCase(),
    type: j['type'] as String? ?? 'pos',
    name: j['name'] as String?,
  );

  final String uuid;

  /// `pos`, `kds` or `display`.
  final String type;
  final String? name;

  Map<String, Object?> toJson() => <String, Object?>{
    'uuid': uuid,
    'type': type,
    'name': name,
  };
}

/// What a terminal holds for its branch's LAN: GET /pos/bootstrap `lan` (README §2). Keep it in secure storage
/// ([toJson]); never in the plain offline copy.
class LanCredentials {
  LanCredentials({
    required this.branchId,
    required this.keyId,
    required this.device,
    required this.isHub,
    required this.deviceKey,
    required this.beaconKey,
    required this.port,
    this.hubDevice,
    this.hubName,
    this.hubSecret,
    this.devices = const <LanDeviceInfo>[],
    this.grant,
    this.clockWindow = const Duration(seconds: 120),
    this.version = 1,
  });

  /// The `lan` section of a bootstrap (`null`: no LAN for this sign-in, or a section this build cannot read).
  static LanCredentials? fromBootstrap(Map<String, Object?> bootstrap) {
    final Object? lan = bootstrap['lan'];
    return lan is Map ? fromJson(lan.cast<String, Object?>()) : null;
  }

  /// The section itself (as the server sends it, or as [toJson] kept it).
  static LanCredentials? fromJson(Map<String, Object?> j) {
    final Uint8List? deviceKey = lanFromBase64(j['device_key']);
    final Uint8List? beaconKey = lanFromBase64(j['beacon_key']);
    final Object? device = j['device'];
    final Object? branch = j['branch_id'];
    if (deviceKey == null ||
        beaconKey == null ||
        device is! String ||
        branch is! num ||
        (j['v'] as num?)?.toInt() != 1) {
      return null;
    }
    final Map<String, Object?> hub = j['hub'] is Map
        ? (j['hub']! as Map).cast<String, Object?>()
        : const <String, Object?>{};
    final bool isHub = j['role'] == 'hub';
    final Uint8List? secret = lanFromBase64(j['hub_secret']);
    if (isHub && secret == null) return null;
    return LanCredentials(
      branchId: branch.toInt(),
      keyId: (j['key_id'] as num?)?.toInt() ?? 1,
      device: device.toLowerCase(),
      isHub: isHub,
      deviceKey: deviceKey,
      beaconKey: beaconKey,
      port: (hub['port'] as num?)?.toInt() ?? 18480,
      hubDevice: (hub['device'] as String?)?.toLowerCase(),
      hubName: hub['name'] as String?,
      hubSecret: isHub ? secret : null,
      devices: <LanDeviceInfo>[
        for (final Object? d
            in (j['devices'] as List<Object?>?) ?? const <Object?>[])
          if (d is Map && d['uuid'] is String)
            LanDeviceInfo.fromJson(d.cast<String, Object?>()),
      ],
      grant: j['grant'] as String?,
      clockWindow: Duration(
        seconds: (j['clock_window_seconds'] as num?)?.toInt() ?? 120,
      ),
    );
  }

  final int version;
  final int branchId;
  final int keyId;

  /// This terminal's uuid.
  final String device;
  final bool isHub;
  final Uint8List deviceKey;
  final Uint8List beaconKey;
  final int port;
  final String? hubDevice;
  final String? hubName;

  /// The branch secret `S` (the hub only).
  final Uint8List? hubSecret;

  /// The terminals the hub lets in (the hub only).
  final List<LanDeviceInfo> devices;

  /// This terminal's signed offline grant (its PIN session; `null` without one).
  final String? grant;
  final Duration clockWindow;

  /// The key of the channel between the hub and [peer]: the hub derives any device's from `S`; a client has only its
  /// own (the channel to the hub). `null` when this terminal cannot talk to [peer].
  Uint8List? keyFor(String peer) {
    final String id = peer.toLowerCase();
    if (isHub) {
      return hubSecret == null ? null : LanKeys.device(hubSecret!, id);
    }
    return id == device ? deviceKey : null;
  }

  /// The hub lets [peer] in: an active terminal of the branch, not itself.
  LanDeviceInfo? member(String peer) {
    final String id = peer.toLowerCase();
    if (!isHub || id == device) return null;
    for (final LanDeviceInfo d in devices) {
      if (d.uuid == id) return d;
    }
    return null;
  }

  /// The grant key (the hub only).
  Uint8List? get grantKey =>
      hubSecret == null ? null : LanKeys.grant(hubSecret!);

  /// The same credentials with another grant (the staff member changed on this terminal).
  LanCredentials withGrant(String? grant) => LanCredentials(
    branchId: branchId,
    keyId: keyId,
    device: device,
    isHub: isHub,
    deviceKey: deviceKey,
    beaconKey: beaconKey,
    port: port,
    hubDevice: hubDevice,
    hubName: hubName,
    hubSecret: hubSecret,
    devices: devices,
    grant: grant,
    clockWindow: clockWindow,
    version: version,
  );

  Map<String, Object?> toJson() => <String, Object?>{
    'v': version,
    'branch_id': branchId,
    'key_id': keyId,
    'role': isHub ? 'hub' : 'client',
    'device': device,
    'device_key': _b64(deviceKey),
    'beacon_key': _b64(beaconKey),
    'hub': <String, Object?>{
      'device': hubDevice,
      'name': hubName,
      'port': port,
    },
    'hub_secret': hubSecret == null ? null : _b64(hubSecret!),
    'devices': isHub
        ? <Object?>[for (final LanDeviceInfo d in devices) d.toJson()]
        : null,
    'grant': grant,
    'clock_window_seconds': clockWindow.inSeconds,
  };

  static String _b64(List<int> bytes) => base64.encode(bytes);
}
