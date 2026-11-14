// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data176.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data176 _$Data176FromJson(Map<String, dynamic> json) => Data176(
  productId: (json['product_id'] as num).toInt(),
  channels: json['channels'] as List<dynamic>,
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data176ToJson(Data176 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'channels': instance.channels,
  'prices': instance.prices,
};
