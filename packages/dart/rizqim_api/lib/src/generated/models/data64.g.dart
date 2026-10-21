// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data64.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data64 _$Data64FromJson(Map<String, dynamic> json) => Data64(
  productId: (json['product_id'] as num).toInt(),
  channels: json['channels'] as List<dynamic>,
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data64ToJson(Data64 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'channels': instance.channels,
  'prices': instance.prices,
};
