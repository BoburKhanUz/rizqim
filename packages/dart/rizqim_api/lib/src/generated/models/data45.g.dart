// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data45.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data45 _$Data45FromJson(Map<String, dynamic> json) => Data45(
  productId: (json['product_id'] as num).toInt(),
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data45ToJson(Data45 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'prices': instance.prices,
};
