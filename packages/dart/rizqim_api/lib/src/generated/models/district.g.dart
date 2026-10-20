// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'district.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

District _$DistrictFromJson(Map<String, dynamic> json) => District(
  id: (json['id'] as num).toInt(),
  regionId: (json['region_id'] as num).toInt(),
  nameUz: json['name_uz'] as String,
  isActive: json['is_active'] as bool,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  region: json['region'] == null
      ? null
      : Region.fromJson(json['region'] as Map<String, dynamic>),
);

Map<String, dynamic> _$DistrictToJson(District instance) => <String, dynamic>{
  'id': instance.id,
  'region_id': instance.regionId,
  'name_uz': instance.nameUz,
  'is_active': instance.isActive,
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
  'region': instance.region,
};
