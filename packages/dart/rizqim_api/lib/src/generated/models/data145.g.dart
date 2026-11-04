// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data145.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data145 _$Data145FromJson(Map<String, dynamic> json) => Data145(
  productId: (json['product_id'] as num).toInt(),
  channels: json['channels'] as List<dynamic>,
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data145ToJson(Data145 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'channels': instance.channels,
  'prices': instance.prices,
};
