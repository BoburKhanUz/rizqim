// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'options.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Options _$OptionsFromJson(Map<String, dynamic> json) => Options(
  modes: (json['modes'] as List<dynamic>)
      .map((e) => Modes.fromJson(e as Map<String, dynamic>))
      .toList(),
  poolRules: (json['pool_rules'] as List<dynamic>)
      .map((e) => PoolRules.fromJson(e as Map<String, dynamic>))
      .toList(),
  poolPeriods: (json['pool_periods'] as List<dynamic>)
      .map((e) => PoolPeriods.fromJson(e as Map<String, dynamic>))
      .toList(),
  roles: (json['roles'] as List<dynamic>)
      .map((e) => Roles2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$OptionsToJson(Options instance) => <String, dynamic>{
  'modes': instance.modes,
  'pool_rules': instance.poolRules,
  'pool_periods': instance.poolPeriods,
  'roles': instance.roles,
};
