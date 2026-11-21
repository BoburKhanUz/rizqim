// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data180.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data180 _$Data180FromJson(Map<String, dynamic> json) => Data180(
  productId: (json['product_id'] as num).toInt(),
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data180ToJson(Data180 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'prices': instance.prices,
};
