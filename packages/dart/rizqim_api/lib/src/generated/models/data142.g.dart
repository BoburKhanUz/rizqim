// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data142.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data142 _$Data142FromJson(Map<String, dynamic> json) => Data142(
  productId: (json['product_id'] as num).toInt(),
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data142ToJson(Data142 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'prices': instance.prices,
};
