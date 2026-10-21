// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data94.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data94 _$Data94FromJson(Map<String, dynamic> json) => Data94(
  productId: (json['product_id'] as num).toInt(),
  channels: json['channels'] as List<dynamic>,
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data94ToJson(Data94 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'channels': instance.channels,
  'prices': instance.prices,
};
