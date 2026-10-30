// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tips5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Tips5 _$Tips5FromJson(Map<String, dynamic> json) => Tips5(
  enabled: json['enabled'] as bool,
  mode: json['mode'] as String,
  poolRule: json['pool_rule'] as String,
  poolPeriod: json['pool_period'] as String,
  roles: (json['roles'] as List<dynamic>).map((e) => e as String).toList(),
);

Map<String, dynamic> _$Tips5ToJson(Tips5 instance) => <String, dynamic>{
  'enabled': instance.enabled,
  'mode': instance.mode,
  'pool_rule': instance.poolRule,
  'pool_period': instance.poolPeriod,
  'roles': instance.roles,
};
