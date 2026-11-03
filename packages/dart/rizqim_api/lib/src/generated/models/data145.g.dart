// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data145.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data145 _$Data145FromJson(Map<String, dynamic> json) => Data145(
  productId: (json['product_id'] as num).toInt(),
  quickMultipliers: (json['quick_multipliers'] as List<dynamic>?)
      ?.map((e) => (e as num).toInt())
      .toList(),
  waitMinutes: (json['wait_minutes'] as num?)?.toInt(),
  weightMode: json['weight_mode'] == null
      ? null
      : WeightBasis.fromJson(json['weight_mode'] as String),
);

Map<String, dynamic> _$Data145ToJson(Data145 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'quick_multipliers': instance.quickMultipliers,
  'wait_minutes': instance.waitMinutes,
  'weight_mode': instance.weightMode,
};
