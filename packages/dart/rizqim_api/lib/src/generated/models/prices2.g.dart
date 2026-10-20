// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'prices2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Prices2 _$Prices2FromJson(Map<String, dynamic> json) => Prices2(
  channel: json['channel'] as String,
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  price: json['price'] as String?,
  isHidden: json['is_hidden'] as bool,
);

Map<String, dynamic> _$Prices2ToJson(Prices2 instance) => <String, dynamic>{
  'channel': instance.channel,
  'product_variant_id': instance.productVariantId,
  'price': instance.price,
  'is_hidden': instance.isHidden,
};
