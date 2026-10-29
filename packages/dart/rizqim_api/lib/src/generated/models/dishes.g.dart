// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'dishes.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Dishes _$DishesFromJson(Map<String, dynamic> json) => Dishes(
  category: json['category'] as String,
  name: json['name'] as String,
  variants: (json['variants'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  exists: json['exists'] as bool,
);

Map<String, dynamic> _$DishesToJson(Dishes instance) => <String, dynamic>{
  'category': instance.category,
  'name': instance.name,
  'variants': instance.variants,
  'exists': instance.exists,
};
