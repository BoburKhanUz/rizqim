// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data177.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data177 _$Data177FromJson(Map<String, dynamic> json) => Data177(
  productId: (json['product_id'] as num).toInt(),
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data177ToJson(Data177 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'prices': instance.prices,
};
