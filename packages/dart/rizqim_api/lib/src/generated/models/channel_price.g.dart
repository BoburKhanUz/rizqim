// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'channel_price.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ChannelPrice _$ChannelPriceFromJson(Map<String, dynamic> json) => ChannelPrice(
  id: (json['id'] as num).toInt(),
  storeId: (json['store_id'] as num).toInt(),
  productId: (json['product_id'] as num).toInt(),
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  channel: OrderChannel.fromJson(json['channel'] as String),
  price: json['price'] as String?,
  isHidden: json['is_hidden'] as bool,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  product: json['product'] == null
      ? null
      : Product.fromJson(json['product'] as Map<String, dynamic>),
  variant: json['variant'] == null
      ? null
      : ProductVariant.fromJson(json['variant'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
);

Map<String, dynamic> _$ChannelPriceToJson(ChannelPrice instance) =>
    <String, dynamic>{
      'id': instance.id,
      'store_id': instance.storeId,
      'product_id': instance.productId,
      'product_variant_id': instance.productVariantId,
      'channel': instance.channel,
      'price': instance.price,
      'is_hidden': instance.isHidden,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'product': instance.product,
      'variant': instance.variant,
      'store': instance.store,
    };
