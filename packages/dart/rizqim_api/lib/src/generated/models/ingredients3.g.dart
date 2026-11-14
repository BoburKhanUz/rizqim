// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'ingredients3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Ingredients3 _$Ingredients3FromJson(Map<String, dynamic> json) => Ingredients3(
  name: json['name'] as String,
  unit: Unit.fromJson(json['unit'] as String),
  perKg: json['per_kg'] as num,
  key: json['key'] as String?,
);

Map<String, dynamic> _$Ingredients3ToJson(Ingredients3 instance) =>
    <String, dynamic>{
      'key': instance.key,
      'name': instance.name,
      'unit': instance.unit,
      'per_kg': instance.perKg,
    };
