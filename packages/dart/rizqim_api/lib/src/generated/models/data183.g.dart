// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data183.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data183 _$Data183FromJson(Map<String, dynamic> json) => Data183(
  productId: (json['product_id'] as num).toInt(),
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data183ToJson(Data183 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'prices': instance.prices,
};
