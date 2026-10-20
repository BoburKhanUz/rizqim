// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device.dart';
import 'user.dart';

part 'refresh_token.g.dart';

@JsonSerializable()
class RefreshToken {
  const RefreshToken({
    required this.id,
    required this.userId,
    required this.deviceId,
    required this.familyId,
    required this.accessTokenId,
    required this.expiresAt,
    required this.usedAt,
    required this.revokedAt,
    required this.createdAt,
    required this.updatedAt,
    required this.replacedById,
    required this.graceUsedAt,
    this.user,
    this.device,
  });

  factory RefreshToken.fromJson(Map<String, Object?> json) =>
      _$RefreshTokenFromJson(json);

  final int id;
  @JsonKey(name: 'user_id')
  final int userId;
  @JsonKey(name: 'device_id')
  final int deviceId;
  @JsonKey(name: 'family_id')
  final String familyId;
  @JsonKey(name: 'access_token_id')
  final int? accessTokenId;
  @JsonKey(name: 'expires_at')
  final DateTime expiresAt;
  @JsonKey(name: 'used_at')
  final DateTime? usedAt;
  @JsonKey(name: 'revoked_at')
  final DateTime? revokedAt;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  @JsonKey(name: 'replaced_by_id')
  final int? replacedById;
  @JsonKey(name: 'grace_used_at')
  final DateTime? graceUsedAt;
  final User? user;
  final Device? device;

  Map<String, Object?> toJson() => _$RefreshTokenToJson(this);
}
