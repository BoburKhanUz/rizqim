// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'osh4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Osh4 _$Osh4FromJson(Map<String, dynamic> json) => Osh4(
  persons: (json['persons'] as num).toInt(),
  oshKg: json['osh_kg'] as String,
  ingredients: (json['ingredients'] as List<dynamic>)
      .map((e) => Ingredients5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Osh4ToJson(Osh4 instance) => <String, dynamic>{
  'persons': instance.persons,
  'osh_kg': instance.oshKg,
  'ingredients': instance.ingredients,
};
