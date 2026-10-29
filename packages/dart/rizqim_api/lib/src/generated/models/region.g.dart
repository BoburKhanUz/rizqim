// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'region.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Region _$RegionFromJson(Map<String, dynamic> json) => Region(
  id: (json['id'] as num).toInt(),
  nameUz: json['name_uz'] as String,
  isActive: json['is_active'] as bool,
  createdAt: json['created_at'] as String?,
  updatedAt: json['updated_at'] as String?,
  sortOrder: (json['sort_order'] as num).toInt(),
  districts: (json['districts'] as List<dynamic>?)
      ?.map((e) => District.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$RegionToJson(Region instance) => <String, dynamic>{
  'id': instance.id,
  'name_uz': instance.nameUz,
  'is_active': instance.isActive,
  'created_at': instance.createdAt,
  'updated_at': instance.updatedAt,
  'sort_order': instance.sortOrder,
  'districts': instance.districts,
};
