// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data95.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data95 _$Data95FromJson(Map<String, dynamic> json) => Data95(
  productId: (json['product_id'] as num).toInt(),
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data95ToJson(Data95 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'prices': instance.prices,
};
