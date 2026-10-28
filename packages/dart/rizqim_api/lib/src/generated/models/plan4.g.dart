// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'plan4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Plan4 _$Plan4FromJson(Map<String, dynamic> json) => Plan4(
  code: json['code'] as String,
  name: json['name'] as String,
  tier: json['tier'] as String?,
  version: (json['version'] as num).toInt(),
);

Map<String, dynamic> _$Plan4ToJson(Plan4 instance) => <String, dynamic>{
  'code': instance.code,
  'name': instance.name,
  'tier': instance.tier,
  'version': instance.version,
};
