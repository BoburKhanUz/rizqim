// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'member3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Member3 _$Member3FromJson(Map<String, dynamic> json) => Member3(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  email: json['email'] as String?,
  platformRole: json['platform_role'] as String?,
  platformRoleLabel: json['platform_role_label'] as String?,
  permissions: (json['permissions'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  twoFactorEnabled: json['two_factor_enabled'] as bool,
  recoveryCodesLeft: (json['recovery_codes_left'] as num).toInt(),
  lastLoginAt: json['last_login_at'] as String?,
);

Map<String, dynamic> _$Member3ToJson(Member3 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'email': instance.email,
  'platform_role': instance.platformRole,
  'platform_role_label': instance.platformRoleLabel,
  'permissions': instance.permissions,
  'two_factor_enabled': instance.twoFactorEnabled,
  'recovery_codes_left': instance.recoveryCodesLeft,
  'last_login_at': instance.lastLoginAt,
};
