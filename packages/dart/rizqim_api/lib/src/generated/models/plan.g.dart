// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'plan.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Plan _$PlanFromJson(Map<String, dynamic> json) => Plan(
  code: json['code'] as String,
  name: json['name'] as String,
  tier: json['tier'] as String?,
  version: (json['version'] as num).toInt(),
);

Map<String, dynamic> _$PlanToJson(Plan instance) => <String, dynamic>{
  'code': instance.code,
  'name': instance.name,
  'tier': instance.tier,
  'version': instance.version,
};
