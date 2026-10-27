// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'member5.g.dart';

@JsonSerializable()
class Member5 {
  const Member5({
    required this.id,
    required this.name,
    required this.email,
    required this.platformRole,
    required this.platformRoleLabel,
    required this.status,
    required this.disabled,
    required this.twoFactorEnabled,
    required this.lastLoginAt,
    required this.activeSessions,
    required this.inviteExpiresAt,
    required this.createdAt,
  });

  factory Member5.fromJson(Map<String, Object?> json) =>
      _$Member5FromJson(json);

  final int id;
  final String name;
  final String? email;
  @JsonKey(name: 'platform_role')
  final String? platformRole;
  @JsonKey(name: 'platform_role_label')
  final String? platformRoleLabel;
  final String status;
  final bool disabled;
  @JsonKey(name: 'two_factor_enabled')
  final bool twoFactorEnabled;
  @JsonKey(name: 'last_login_at')
  final String? lastLoginAt;
  @JsonKey(name: 'active_sessions')
  final int activeSessions;
  @JsonKey(name: 'invite_expires_at')
  final String? inviteExpiresAt;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Member5ToJson(this);
}
