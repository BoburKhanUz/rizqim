// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'prices3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Prices3 _$Prices3FromJson(Map<String, dynamic> json) => Prices3(
  channel: Channel3.fromJson(json['channel'] as String),
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  price: json['price'] as num?,
  isHidden: json['is_hidden'] as bool?,
);

Map<String, dynamic> _$Prices3ToJson(Prices3 instance) => <String, dynamic>{
  'channel': instance.channel,
  'product_variant_id': instance.productVariantId,
  'price': instance.price,
  'is_hidden': instance.isHidden,
};
