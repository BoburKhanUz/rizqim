// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'portion_weights2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PortionWeights2 _$PortionWeights2FromJson(Map<String, dynamic> json) =>
    PortionWeights2(
      productId: (json['product_id'] as num).toInt(),
      grams: (json['grams'] as num).toInt(),
    );

Map<String, dynamic> _$PortionWeights2ToJson(PortionWeights2 instance) =>
    <String, dynamic>{
      'product_id': instance.productId,
      'grams': instance.grams,
    };
