// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refresh_token.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RefreshToken _$RefreshTokenFromJson(Map<String, dynamic> json) => RefreshToken(
  id: (json['id'] as num).toInt(),
  userId: (json['user_id'] as num).toInt(),
  deviceId: (json['device_id'] as num).toInt(),
  familyId: json['family_id'] as String,
  accessTokenId: (json['access_token_id'] as num?)?.toInt(),
  expiresAt: DateTime.parse(json['expires_at'] as String),
  usedAt: json['used_at'] == null
      ? null
      : DateTime.parse(json['used_at'] as String),
  revokedAt: json['revoked_at'] == null
      ? null
      : DateTime.parse(json['revoked_at'] as String),
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  user: json['user'] == null
      ? null
      : User.fromJson(json['user'] as Map<String, dynamic>),
  device: json['device'] == null
      ? null
      : Device.fromJson(json['device'] as Map<String, dynamic>),
);

Map<String, dynamic> _$RefreshTokenToJson(RefreshToken instance) =>
    <String, dynamic>{
      'id': instance.id,
      'user_id': instance.userId,
      'device_id': instance.deviceId,
      'family_id': instance.familyId,
      'access_token_id': instance.accessTokenId,
      'expires_at': instance.expiresAt.toIso8601String(),
      'used_at': instance.usedAt?.toIso8601String(),
      'revoked_at': instance.revokedAt?.toIso8601String(),
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'user': instance.user,
      'device': instance.device,
    };
