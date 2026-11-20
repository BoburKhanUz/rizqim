// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data224.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data224 _$Data224FromJson(Map<String, dynamic> json) => Data224(
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

Map<String, dynamic> _$Data224ToJson(Data224 instance) => <String, dynamic>{
  'persons': instance.persons,
  'persons_per_kg': instance.personsPerKg,
  'osh_kg': instance.oshKg,
  'ingredients': instance.ingredients,
  'kazans': instance.kazans,
  'kazan_capacity_kg': instance.kazanCapacityKg,
};
