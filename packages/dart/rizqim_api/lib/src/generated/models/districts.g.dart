// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'districts.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Districts _$DistrictsFromJson(Map<String, dynamic> json) => Districts(
  id: (json['id'] as num).toInt(),
  regionId: (json['region_id'] as num).toInt(),
  name: json['name'] as String,
  isActive: json['is_active'] as bool,
);

Map<String, dynamic> _$DistrictsToJson(Districts instance) => <String, dynamic>{
  'id': instance.id,
  'region_id': instance.regionId,
  'name': instance.name,
  'is_active': instance.isActive,
};
