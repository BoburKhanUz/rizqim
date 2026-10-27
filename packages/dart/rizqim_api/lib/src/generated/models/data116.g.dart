// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data116.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data116 _$Data116FromJson(Map<String, dynamic> json) => Data116(
  productId: (json['product_id'] as num).toInt(),
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data116ToJson(Data116 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'prices': instance.prices,
};
