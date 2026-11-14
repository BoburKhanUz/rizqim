// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'portion_weights.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PortionWeights _$PortionWeightsFromJson(Map<String, dynamic> json) =>
    PortionWeights(
      productId: (json['product_id'] as num).toInt(),
      grams: (json['grams'] as num).toInt(),
    );

Map<String, dynamic> _$PortionWeightsToJson(PortionWeights instance) =>
    <String, dynamic>{
      'product_id': instance.productId,
      'grams': instance.grams,
    };
