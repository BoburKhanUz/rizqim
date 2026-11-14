// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'ingredients.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Ingredients _$IngredientsFromJson(Map<String, dynamic> json) => Ingredients(
  key: json['key'] as String,
  name: json['name'] as String,
  unit: json['unit'] as String,
  perKg: json['per_kg'] as String,
);

Map<String, dynamic> _$IngredientsToJson(Ingredients instance) =>
    <String, dynamic>{
      'key': instance.key,
      'name': instance.name,
      'unit': instance.unit,
      'per_kg': instance.perKg,
    };
