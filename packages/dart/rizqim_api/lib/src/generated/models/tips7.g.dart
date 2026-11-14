// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tips7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Tips7 _$Tips7FromJson(Map<String, dynamic> json) => Tips7(
  enabled: json['enabled'] as bool,
  mode: json['mode'] as String,
  poolRule: json['pool_rule'] as String,
  poolPeriod: json['pool_period'] as String,
  roles: (json['roles'] as List<dynamic>).map((e) => e as String).toList(),
);

Map<String, dynamic> _$Tips7ToJson(Tips7 instance) => <String, dynamic>{
  'enabled': instance.enabled,
  'mode': instance.mode,
  'pool_rule': instance.poolRule,
  'pool_period': instance.poolPeriod,
  'roles': instance.roles,
};
