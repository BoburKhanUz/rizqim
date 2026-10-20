// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data43.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data43 _$Data43FromJson(Map<String, dynamic> json) => Data43(
  productId: (json['product_id'] as num).toInt(),
  channels: json['channels'] as List<dynamic>,
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data43ToJson(Data43 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'channels': instance.channels,
  'prices': instance.prices,
};
