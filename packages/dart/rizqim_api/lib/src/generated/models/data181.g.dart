// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data181.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data181 _$Data181FromJson(Map<String, dynamic> json) => Data181(
  productId: (json['product_id'] as num).toInt(),
  quickMultipliers: (json['quick_multipliers'] as List<dynamic>?)
      ?.map((e) => (e as num).toInt())
      .toList(),
  waitMinutes: (json['wait_minutes'] as num?)?.toInt(),
  weightMode: json['weight_mode'] == null
      ? null
      : WeightBasis.fromJson(json['weight_mode'] as String),
);

Map<String, dynamic> _$Data181ToJson(Data181 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'quick_multipliers': instance.quickMultipliers,
  'wait_minutes': instance.waitMinutes,
  'weight_mode': instance.weightMode,
};
