// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data115.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data115 _$Data115FromJson(Map<String, dynamic> json) => Data115(
  productId: (json['product_id'] as num).toInt(),
  channels: json['channels'] as List<dynamic>,
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data115ToJson(Data115 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'channels': instance.channels,
  'prices': instance.prices,
};
