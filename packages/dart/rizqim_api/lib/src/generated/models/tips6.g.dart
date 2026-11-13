// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tips6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Tips6 _$Tips6FromJson(Map<String, dynamic> json) => Tips6(
  enabled: json['enabled'] as bool,
  mode: json['mode'] as String,
  poolRule: json['pool_rule'] as String,
  poolPeriod: json['pool_period'] as String,
  roles: (json['roles'] as List<dynamic>).map((e) => e as String).toList(),
);

Map<String, dynamic> _$Tips6ToJson(Tips6 instance) => <String, dynamic>{
  'enabled': instance.enabled,
  'mode': instance.mode,
  'pool_rule': instance.poolRule,
  'pool_period': instance.poolPeriod,
  'roles': instance.roles,
};
