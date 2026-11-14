// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'osh.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Osh _$OshFromJson(Map<String, dynamic> json) => Osh(
  personsPerKg: json['persons_per_kg'] as String,
  productIds: (json['product_ids'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  ingredients: (json['ingredients'] as List<dynamic>)
      .map((e) => Ingredients.fromJson(e as Map<String, dynamic>))
      .toList(),
  kazans: (json['kazans'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
);

Map<String, dynamic> _$OshToJson(Osh instance) => <String, dynamic>{
  'persons_per_kg': instance.personsPerKg,
  'product_ids': instance.productIds,
  'ingredients': instance.ingredients,
  'kazans': instance.kazans,
};
