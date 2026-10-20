// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data44.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data44 _$Data44FromJson(Map<String, dynamic> json) => Data44(
  productId: (json['product_id'] as num).toInt(),
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data44ToJson(Data44 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'prices': instance.prices,
};
