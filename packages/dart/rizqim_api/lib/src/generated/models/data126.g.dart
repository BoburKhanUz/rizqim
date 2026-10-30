// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data126.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data126 _$Data126FromJson(Map<String, dynamic> json) => Data126(
  productId: (json['product_id'] as num).toInt(),
  channels: json['channels'] as List<dynamic>,
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data126ToJson(Data126 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'channels': instance.channels,
  'prices': instance.prices,
};
