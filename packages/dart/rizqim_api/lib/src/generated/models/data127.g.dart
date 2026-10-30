// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data127.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data127 _$Data127FromJson(Map<String, dynamic> json) => Data127(
  productId: (json['product_id'] as num).toInt(),
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data127ToJson(Data127 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'prices': instance.prices,
};
