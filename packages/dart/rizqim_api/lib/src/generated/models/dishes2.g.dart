// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'dishes2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Dishes2 _$Dishes2FromJson(Map<String, dynamic> json) => Dishes2(
  category: json['category'] as String,
  name: json['name'] as String,
  variants: (json['variants'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  exists: json['exists'] as bool,
);

Map<String, dynamic> _$Dishes2ToJson(Dishes2 instance) => <String, dynamic>{
  'category': instance.category,
  'name': instance.name,
  'variants': instance.variants,
  'exists': instance.exists,
};
