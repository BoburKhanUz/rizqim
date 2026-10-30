// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'options5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Options5 _$Options5FromJson(Map<String, dynamic> json) => Options5(
  modes: (json['modes'] as List<dynamic>)
      .map((e) => Modes2.fromJson(e as Map<String, dynamic>))
      .toList(),
  poolRules: (json['pool_rules'] as List<dynamic>)
      .map((e) => PoolRules2.fromJson(e as Map<String, dynamic>))
      .toList(),
  poolPeriods: (json['pool_periods'] as List<dynamic>)
      .map((e) => PoolPeriods2.fromJson(e as Map<String, dynamic>))
      .toList(),
  roles: (json['roles'] as List<dynamic>)
      .map((e) => Roles3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Options5ToJson(Options5 instance) => <String, dynamic>{
  'modes': instance.modes,
  'pool_rules': instance.poolRules,
  'pool_periods': instance.poolPeriods,
  'roles': instance.roles,
};
