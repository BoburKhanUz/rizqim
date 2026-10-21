// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data65.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data65 _$Data65FromJson(Map<String, dynamic> json) => Data65(
  productId: (json['product_id'] as num).toInt(),
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data65ToJson(Data65 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'prices': instance.prices,
};
