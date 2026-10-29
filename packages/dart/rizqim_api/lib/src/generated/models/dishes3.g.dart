// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'dishes3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Dishes3 _$Dishes3FromJson(Map<String, dynamic> json) => Dishes3(
  category: json['category'] as String,
  name: json['name'] as String,
  variants: (json['variants'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  exists: json['exists'] as bool,
);

Map<String, dynamic> _$Dishes3ToJson(Dishes3 instance) => <String, dynamic>{
  'category': instance.category,
  'name': instance.name,
  'variants': instance.variants,
  'exists': instance.exists,
};
