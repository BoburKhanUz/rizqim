// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'regions.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Regions _$RegionsFromJson(Map<String, dynamic> json) => Regions(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  isActive: json['is_active'] as bool,
);

Map<String, dynamic> _$RegionsToJson(Regions instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'is_active': instance.isActive,
};
