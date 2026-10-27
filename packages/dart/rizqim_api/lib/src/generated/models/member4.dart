// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'member4.g.dart';

@JsonSerializable()
class Member4 {
  const Member4({
    required this.id,
    required this.name,
    required this.email,
    required this.platformRole,
    required this.platformRoleLabel,
    required this.permissions,
    required this.twoFactorEnabled,
    required this.recoveryCodesLeft,
    required this.lastLoginAt,
  });

  factory Member4.fromJson(Map<String, Object?> json) =>
      _$Member4FromJson(json);

  final int id;
  final String name;
  final String? email;
  @JsonKey(name: 'platform_role')
  final String? platformRole;
  @JsonKey(name: 'platform_role_label')
  final String? platformRoleLabel;
  final List<String> permissions;
  @JsonKey(name: 'two_factor_enabled')
  final bool twoFactorEnabled;
  @JsonKey(name: 'recovery_codes_left')
  final int recoveryCodesLeft;
  @JsonKey(name: 'last_login_at')
  final String? lastLoginAt;

  Map<String, Object?> toJson() => _$Member4ToJson(this);
}
