// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data146.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data146 _$Data146FromJson(Map<String, dynamic> json) => Data146(
  productId: (json['product_id'] as num).toInt(),
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data146ToJson(Data146 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'prices': instance.prices,
};
