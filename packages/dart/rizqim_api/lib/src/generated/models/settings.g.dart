// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'settings.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Settings _$SettingsFromJson(Map<String, dynamic> json) => Settings(
  branchId: (json['branch_id'] as num).toInt(),
  enabled: json['enabled'] as bool,
  mode: json['mode'] as String,
  poolRule: json['pool_rule'] as String,
  poolPeriod: json['pool_period'] as String,
  roles: (json['roles'] as List<dynamic>).map((e) => e as String).toList(),
  roleWeights: Map<String, String>.from(json['role_weights'] as Map),
  updatedAt: json['updated_at'] as String?,
);

Map<String, dynamic> _$SettingsToJson(Settings instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'enabled': instance.enabled,
  'mode': instance.mode,
  'pool_rule': instance.poolRule,
  'pool_period': instance.poolPeriod,
  'roles': instance.roles,
  'role_weights': instance.roleWeights,
  'updated_at': instance.updatedAt,
};
