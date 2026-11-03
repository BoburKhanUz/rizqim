// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'products4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Products4 _$Products4FromJson(Map<String, dynamic> json) => Products4(
  productId: (json['product_id'] as num).toInt(),
  name: json['name'] as String,
  categoryId: (json['category_id'] as num).toInt(),
  targetSeconds: (json['target_seconds'] as num).toInt(),
);

Map<String, dynamic> _$Products4ToJson(Products4 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'name': instance.name,
  'category_id': instance.categoryId,
  'target_seconds': instance.targetSeconds,
};
