// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data182.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data182 _$Data182FromJson(Map<String, dynamic> json) => Data182(
  productId: (json['product_id'] as num).toInt(),
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data182ToJson(Data182 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'prices': instance.prices,
};
