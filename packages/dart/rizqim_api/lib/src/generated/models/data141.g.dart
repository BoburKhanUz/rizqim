// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data141.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data141 _$Data141FromJson(Map<String, dynamic> json) => Data141(
  productId: (json['product_id'] as num).toInt(),
  channels: json['channels'] as List<dynamic>,
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data141ToJson(Data141 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'channels': instance.channels,
  'prices': instance.prices,
};
