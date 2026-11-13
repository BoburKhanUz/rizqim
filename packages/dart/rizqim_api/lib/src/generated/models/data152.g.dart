// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data152.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data152 _$Data152FromJson(Map<String, dynamic> json) => Data152(
  productId: (json['product_id'] as num).toInt(),
  channels: json['channels'] as List<dynamic>,
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data152ToJson(Data152 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'channels': instance.channels,
  'prices': instance.prices,
};
