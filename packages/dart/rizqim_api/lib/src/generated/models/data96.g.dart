// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data96.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data96 _$Data96FromJson(Map<String, dynamic> json) => Data96(
  productId: (json['product_id'] as num).toInt(),
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data96ToJson(Data96 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'prices': instance.prices,
};
