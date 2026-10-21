// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'districts2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Districts2 _$Districts2FromJson(Map<String, dynamic> json) => Districts2(
  id: (json['id'] as num).toInt(),
  regionId: (json['region_id'] as num).toInt(),
  name: json['name'] as String,
);

Map<String, dynamic> _$Districts2ToJson(Districts2 instance) =>
    <String, dynamic>{
      'id': instance.id,
      'region_id': instance.regionId,
      'name': instance.name,
    };
