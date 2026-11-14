// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'osh3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Osh3 _$Osh3FromJson(Map<String, dynamic> json) => Osh3(
  personsPerKg: json['persons_per_kg'] as num?,
  ingredients: (json['ingredients'] as List<dynamic>?)
      ?.map((e) => Ingredients3.fromJson(e as Map<String, dynamic>))
      .toList(),
  kazans: (json['kazans'] as List<dynamic>?)
      ?.map((e) => (e as num).toInt())
      .toList(),
  productIds: (json['product_ids'] as List<dynamic>?)
      ?.map((e) => (e as num).toInt())
      .toList(),
);

Map<String, dynamic> _$Osh3ToJson(Osh3 instance) => <String, dynamic>{
  'persons_per_kg': instance.personsPerKg,
  'ingredients': instance.ingredients,
  'kazans': instance.kazans,
  'product_ids': instance.productIds,
};
