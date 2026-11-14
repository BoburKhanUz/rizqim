// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'brand_channel.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BrandChannel _$BrandChannelFromJson(Map<String, dynamic> json) => BrandChannel(
  id: (json['id'] as num).toInt(),
  storeId: (json['store_id'] as num).toInt(),
  brandId: (json['brand_id'] as num).toInt(),
  channel: OrderChannel.fromJson(json['channel'] as String),
  isHidden: json['is_hidden'] as bool,
  pricePercent: json['price_percent'] as String?,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  brand: json['brand'] == null
      ? null
      : Brand.fromJson(json['brand'] as Map<String, dynamic>),
);

Map<String, dynamic> _$BrandChannelToJson(BrandChannel instance) =>
    <String, dynamic>{
      'id': instance.id,
      'store_id': instance.storeId,
      'brand_id': instance.brandId,
      'channel': instance.channel,
      'is_hidden': instance.isHidden,
      'price_percent': instance.pricePercent,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'brand': instance.brand,
    };
