// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data12.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data12 _$Data12FromJson(Map<String, dynamic> json) => Data12(
  member: Member4.fromJson(json['member'] as Map<String, dynamic>),
  platformRole: json['platform_role'] as String?,
  permissions: (json['permissions'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  twoFactorEnabled: json['two_factor_enabled'] as bool,
  recoveryCodesLeft: (json['recovery_codes_left'] as num).toInt(),
  lastLoginAt: json['last_login_at'] as String?,
  session: json['session'] == null
      ? null
      : Session2.fromJson(json['session'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data12ToJson(Data12 instance) => <String, dynamic>{
  'member': instance.member,
  'platform_role': instance.platformRole,
  'permissions': instance.permissions,
  'two_factor_enabled': instance.twoFactorEnabled,
  'recovery_codes_left': instance.recoveryCodesLeft,
  'last_login_at': instance.lastLoginAt,
  'session': instance.session,
};
