// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'osh2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Osh2 _$Osh2FromJson(Map<String, dynamic> json) => Osh2(
  personsPerKg: json['persons_per_kg'] as String,
  productIds: (json['product_ids'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  ingredients: (json['ingredients'] as List<dynamic>)
      .map((e) => Ingredients2.fromJson(e as Map<String, dynamic>))
      .toList(),
  kazans: (json['kazans'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
);

Map<String, dynamic> _$Osh2ToJson(Osh2 instance) => <String, dynamic>{
  'persons_per_kg': instance.personsPerKg,
  'product_ids': instance.productIds,
  'ingredients': instance.ingredients,
  'kazans': instance.kazans,
};
