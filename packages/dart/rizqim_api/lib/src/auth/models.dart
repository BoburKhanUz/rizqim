/// Terminal (POS/KDS) ulangan do'kon va filial.
class NamedRef {
  const NamedRef({required this.id, required this.name});

  factory NamedRef.fromJson(Map<String, Object?> json) =>
      NamedRef(id: (json['id']! as num).toInt(), name: json['name']! as String);

  final int id;
  final String name;

  Map<String, Object?> toJson() => <String, Object?>{'id': id, 'name': name};
}

/// Ro'yxatdan o'tgan qurilma (`device` obyekti).
class DeviceInfo {
  const DeviceInfo({
    required this.uuid,
    required this.type,
    required this.name,
    this.platform,
    this.appVersion,
    this.storeId,
    this.branchId,
  });

  factory DeviceInfo.fromJson(Map<String, Object?> json) => DeviceInfo(
    uuid: json['uuid']! as String,
    type: json['type']! as String,
    name: (json['name'] as String?) ?? '',
    platform: json['platform'] as String?,
    appVersion: json['app_version'] as String?,
    storeId: (json['store_id'] as num?)?.toInt(),
    branchId: (json['branch_id'] as num?)?.toInt(),
  );

  final String uuid;

  /// `pos`, `kds`, `courier`, `partner_mobile`, `customer_mobile`, `web`.
  final String type;
  final String name;
  final String? platform;
  final String? appVersion;
  final int? storeId;
  final int? branchId;

  Map<String, Object?> toJson() => <String, Object?>{
    'uuid': uuid,
    'type': type,
    'name': name,
    'platform': platform,
    'app_version': appVersion,
    'store_id': storeId,
    'branch_id': branchId,
  };
}

/// `POST /devices/pair` natijasi. [token] terminalning o'z tokeni (365 kun yoki o'chirilguncha).
class PairedTerminal {
  const PairedTerminal({
    required this.token,
    required this.device,
    this.store,
    this.branch,
  });

  factory PairedTerminal.fromJson(Map<String, Object?> json) => PairedTerminal(
    token: json['token']! as String,
    device: DeviceInfo.fromJson(json['device']! as Map<String, Object?>),
    store: _ref(json['store']),
    branch: _ref(json['branch']),
  );

  final String token;
  final DeviceInfo device;
  final NamedRef? store;
  final NamedRef? branch;

  Map<String, Object?> toJson() => <String, Object?>{
    'token': token,
    'device': device.toJson(),
    'store': store?.toJson(),
    'branch': branch?.toJson(),
  };
}

NamedRef? _ref(Object? json) =>
    json is Map ? NamedRef.fromJson(json.cast<String, Object?>()) : null;

/// PIN klaviaturasidagi xodim (`GET /terminal/staff`).
class StaffMember {
  const StaffMember({
    required this.id,
    required this.name,
    required this.role,
    this.staffCode,
    this.photoUrl,
  });

  factory StaffMember.fromJson(Map<String, Object?> json) => StaffMember(
    id: (json['id']! as num).toInt(),
    name: json['name']! as String,
    role: json['role']! as String,
    staffCode: json['staff_code'] as String?,
    photoUrl: json['photo_url'] as String?,
  );

  final int id;
  final String name;
  final String role;
  final String? staffCode;
  final String? photoUrl;
}

/// Terminalga PIN bilan kirgan xodim sessiyasi (12 soat). Faqat xotirada saqlanadi.
class StaffSession {
  const StaffSession({
    required this.token,
    required this.expiresAt,
    required this.userId,
    required this.userName,
    required this.role,
    required this.permissions,
    this.branchId,
  });

  factory StaffSession.fromJson(Map<String, Object?> json) {
    final Map<String, Object?> user = (json['user']! as Map<Object?, Object?>)
        .cast<String, Object?>();
    return StaffSession(
      token: json['token']! as String,
      expiresAt: DateTime.parse(json['expires_at']! as String),
      userId: (user['id']! as num).toInt(),
      userName: user['name']! as String,
      role: user['role']! as String,
      branchId: (user['branch_id'] as num?)?.toInt(),
      permissions:
          ((json['permissions'] as List<Object?>?) ?? const <Object?>[])
              .cast<String>()
              .toSet(),
    );
  }

  final String token;
  final DateTime expiresAt;
  final int userId;
  final String userName;
  final String role;
  final int? branchId;
  final Set<String> permissions;

  bool can(String permission) => permissions.contains(permission);

  bool isExpired(DateTime now) => !expiresAt.isAfter(now);
}

/// Shaxsiy ilovadagi (kuryer, hamkor, mijoz) qisqa access token + aylanuvchi refresh token.
class UserSession {
  const UserSession({
    required this.accessToken,
    required this.accessExpiresAt,
    required this.refreshToken,
    required this.refreshExpiresAt,
    required this.deviceUuid,
  });

  /// Kirish yoki `POST /auth/refresh` javobining `data` qismidan (`token`, `token_expires_at`, `refresh_token`,
  /// `refresh_token_expires_at`, `device.uuid`).
  factory UserSession.fromJson(Map<String, Object?> json) {
    final Object? device = json['device'];
    final Object? uuid =
        json['device_uuid'] ?? (device is Map ? device['uuid'] : null);
    return UserSession(
      accessToken: (json['token'] ?? json['access_token'])! as String,
      accessExpiresAt: DateTime.parse(
        (json['token_expires_at'] ?? json['access_expires_at'])! as String,
      ),
      refreshToken: json['refresh_token']! as String,
      refreshExpiresAt: DateTime.parse(
        json['refresh_token_expires_at']! as String,
      ),
      deviceUuid: uuid! as String,
    );
  }

  final String accessToken;
  final DateTime accessExpiresAt;
  final String refreshToken;
  final DateTime refreshExpiresAt;
  final String deviceUuid;

  Map<String, Object?> toJson() => <String, Object?>{
    'access_token': accessToken,
    'access_expires_at': accessExpiresAt.toUtc().toIso8601String(),
    'refresh_token': refreshToken,
    'refresh_token_expires_at': refreshExpiresAt.toUtc().toIso8601String(),
    'device_uuid': deviceUuid,
  };
}
