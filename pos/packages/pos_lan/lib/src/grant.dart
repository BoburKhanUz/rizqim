import 'dart:convert';
import 'dart:typed_data';

import 'crypto.dart';
import 'messages.dart';

/// Why a grant was not taken (README §3).
enum LanGrantError {
  malformed('grant_invalid'),
  badSignature('grant_invalid'),
  otherKey('key_mismatch'),
  otherBranch('wrong_branch'),
  otherDevice('grant_device'),
  expired('grant_expired');

  const LanGrantError(this.reason);

  /// The reason sent to the device.
  final String reason;
}

/// The server-signed offline grant of a PIN session (README §3): who is signed in on which terminal, their role and
/// permissions, until when. The hub checks it with the grant key; a client only carries it.
class LanGrant implements LanGrantView {
  const LanGrant({
    required this.staffId,
    required this.device,
    required this.branchId,
    required this.keyId,
    required this.permissions,
    required this.issuedAt,
    required this.expiresAt,
    this.name,
    this.role,
    this.session,
  });

  @override
  final int staffId;
  @override
  final String? name;

  /// The staff member's role (`waiter`, `cashier`, `cook`, `manager`, `owner`...).
  @override
  final String? role;
  @override
  final String device;
  final int branchId;
  final int keyId;
  @override
  final Set<String> permissions;
  final DateTime issuedAt;
  final DateTime expiresAt;
  final int? session;

  @override
  bool can(String permission) => permissions.contains(permission);

  @override
  bool get isWaiter => role == 'waiter';

  Map<String, Object?> get summary => <String, Object?>{
    'id': staffId,
    'name': name,
    'role': role,
  };

  /// Checks [token] (`base64url(json).base64url(hmac)`) with [grantKey] for [device] of [branchId] at [now]. Returns
  /// the grant or the reason it is refused.
  static (LanGrant?, LanGrantError?) verify(
    String token, {
    required List<int> grantKey,
    required int branchId,
    required int keyId,
    required String device,
    required DateTime now,
  }) {
    final List<String> parts = token.split('.');
    if (parts.length != 2) return (null, LanGrantError.malformed);
    final Uint8List? sig = lanFromBase64Url(parts[1]);
    final Uint8List expected = LanKeys.hmac(grantKey, utf8.encode(parts[0]));
    if (sig == null || !lanSameBytes(sig, expected)) {
      return (null, LanGrantError.badSignature);
    }
    final Uint8List? body = lanFromBase64Url(parts[0]);
    Object? json;
    try {
      json = body == null ? null : jsonDecode(utf8.decode(body));
    } on FormatException {
      json = null;
    }
    if (json is! Map) return (null, LanGrantError.malformed);
    final Map<String, Object?> j = json.cast<String, Object?>();
    final Object? staff = j['staff_id'];
    final Object? iat = j['iat'];
    final Object? exp = j['exp'];
    if (staff is! num || iat is! num || exp is! num || j['device'] is! String) {
      return (null, LanGrantError.malformed);
    }
    final LanGrant grant = LanGrant(
      staffId: staff.toInt(),
      name: j['name'] as String?,
      role: j['role'] as String?,
      device: (j['device']! as String).toLowerCase(),
      branchId: (j['branch_id'] as num?)?.toInt() ?? -1,
      keyId: (j['kid'] as num?)?.toInt() ?? -1,
      permissions: <String>{
        for (final Object? p
            in (j['permissions'] as List<Object?>?) ?? const <Object?>[])
          if (p is String) p,
      },
      issuedAt: DateTime.fromMillisecondsSinceEpoch(
        iat.toInt() * 1000,
        isUtc: true,
      ),
      expiresAt: DateTime.fromMillisecondsSinceEpoch(
        exp.toInt() * 1000,
        isUtc: true,
      ),
      session: (j['session'] as num?)?.toInt(),
    );
    if (grant.keyId != keyId) return (null, LanGrantError.otherKey);
    if (grant.branchId != branchId) return (null, LanGrantError.otherBranch);
    if (grant.device != device.toLowerCase()) {
      return (null, LanGrantError.otherDevice);
    }
    if (!now.isBefore(grant.expiresAt)) return (null, LanGrantError.expired);
    return (grant, null);
  }

  /// A grant made the way the server makes one (tests and tools; the server is the only real issuer).
  static String sign(Map<String, Object?> payload, List<int> grantKey) {
    final String body = lanBase64Url(utf8.encode(jsonEncode(payload)));
    return '$body.${lanBase64Url(LanKeys.hmac(grantKey, utf8.encode(body)))}';
  }
}
