// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data225.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data225 _$Data225FromJson(Map<String, dynamic> json) => Data225(
  persons: (json['persons'] as num).toInt(),
  personsPerKg: json['persons_per_kg'] as String,
  oshKg: json['osh_kg'] as String,
  ingredients: (json['ingredients'] as List<dynamic>)
      .map((e) => Ingredients4.fromJson(e as Map<String, dynamic>))
      .toList(),
  kazans: (json['kazans'] as List<dynamic>)
      .map((e) => Kazans.fromJson(e as Map<String, dynamic>))
      .toList(),
  kazanCapacityKg: json['kazan_capacity_kg'] as String,
);

Map<String, dynamic> _$Data225ToJson(Data225 instance) => <String, dynamic>{
  'persons': instance.persons,
  'persons_per_kg': instance.personsPerKg,
  'osh_kg': instance.oshKg,
  'ingredients': instance.ingredients,
  'kazans': instance.kazans,
  'kazan_capacity_kg': instance.kazanCapacityKg,
};
