// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data153.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data153 _$Data153FromJson(Map<String, dynamic> json) => Data153(
  productId: (json['product_id'] as num).toInt(),
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data153ToJson(Data153 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'prices': instance.prices,
};
