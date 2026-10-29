// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data114.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data114 _$Data114FromJson(Map<String, dynamic> json) => Data114(
  productId: (json['product_id'] as num).toInt(),
  channels: json['channels'] as List<dynamic>,
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data114ToJson(Data114 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'channels': instance.channels,
  'prices': instance.prices,
};
