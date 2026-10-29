// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'products2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Products2 _$Products2FromJson(Map<String, dynamic> json) => Products2(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  categoryId: (json['category_id'] as num).toInt(),
  categoryName: json['category_name'] as String,
  dailyRotation: json['daily_rotation'] as String,
  onMenu: json['on_menu'] as String,
  isSet: json['is_set'] as bool,
);

Map<String, dynamic> _$Products2ToJson(Products2 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'category_id': instance.categoryId,
  'category_name': instance.categoryName,
  'daily_rotation': instance.dailyRotation,
  'on_menu': instance.onMenu,
  'is_set': instance.isSet,
};
