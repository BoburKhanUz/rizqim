// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data143.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data143 _$Data143FromJson(Map<String, dynamic> json) => Data143(
  productId: (json['product_id'] as num).toInt(),
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data143ToJson(Data143 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'prices': instance.prices,
};
