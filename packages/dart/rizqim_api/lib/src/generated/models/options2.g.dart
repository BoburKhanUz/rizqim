// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'options2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Options2 _$Options2FromJson(Map<String, dynamic> json) => Options2(
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

Map<String, dynamic> _$Options2ToJson(Options2 instance) => <String, dynamic>{
  'modes': instance.modes,
  'pool_rules': instance.poolRules,
  'pool_periods': instance.poolPeriods,
  'roles': instance.roles,
};
