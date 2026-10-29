// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'products3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Products3 _$Products3FromJson(Map<String, dynamic> json) => Products3(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  categoryId: (json['category_id'] as num).toInt(),
  categoryName: json['category_name'] as String,
  dailyRotation: json['daily_rotation'] as String,
  onMenu: json['on_menu'] as String,
  isSet: json['is_set'] as bool,
);

Map<String, dynamic> _$Products3ToJson(Products3 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'category_id': instance.categoryId,
  'category_name': instance.categoryName,
  'daily_rotation': instance.dailyRotation,
  'on_menu': instance.onMenu,
  'is_set': instance.isSet,
};
