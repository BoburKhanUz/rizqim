// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'member3.dart';
import 'session.dart';

part 'data10.g.dart';

@JsonSerializable()
class Data10 {
  const Data10({
    required this.member,
    required this.platformRole,
    required this.permissions,
    required this.twoFactorEnabled,
    required this.recoveryCodesLeft,
    required this.lastLoginAt,
    required this.session,
  });

  factory Data10.fromJson(Map<String, Object?> json) => _$Data10FromJson(json);

  final Member3 member;
  @JsonKey(name: 'platform_role')
  final String? platformRole;
  final List<String> permissions;
  @JsonKey(name: 'two_factor_enabled')
  final bool twoFactorEnabled;
  @JsonKey(name: 'recovery_codes_left')
  final int recoveryCodesLeft;
  @JsonKey(name: 'last_login_at')
  final String? lastLoginAt;
  final Session? session;

  Map<String, Object?> toJson() => _$Data10ToJson(this);
}
