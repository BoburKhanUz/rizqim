// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'member5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Member5 _$Member5FromJson(Map<String, dynamic> json) => Member5(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  email: json['email'] as String?,
  platformRole: json['platform_role'] as String?,
  platformRoleLabel: json['platform_role_label'] as String?,
  status: json['status'] as String,
  disabled: json['disabled'] as bool,
  twoFactorEnabled: json['two_factor_enabled'] as bool,
  lastLoginAt: json['last_login_at'] as String?,
  activeSessions: (json['active_sessions'] as num).toInt(),
  inviteExpiresAt: json['invite_expires_at'] as String?,
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$Member5ToJson(Member5 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'email': instance.email,
  'platform_role': instance.platformRole,
  'platform_role_label': instance.platformRoleLabel,
  'status': instance.status,
  'disabled': instance.disabled,
  'two_factor_enabled': instance.twoFactorEnabled,
  'last_login_at': instance.lastLoginAt,
  'active_sessions': instance.activeSessions,
  'invite_expires_at': instance.inviteExpiresAt,
  'created_at': instance.createdAt,
};
