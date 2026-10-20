// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_products_id_channel_prices_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerProductsIdChannelPricesRequestBody
_$V1OwnerProductsIdChannelPricesRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerProductsIdChannelPricesRequestBody(
  prices: (json['prices'] as List<dynamic>)
      .map((e) => Prices3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$V1OwnerProductsIdChannelPricesRequestBodyToJson(
  V1OwnerProductsIdChannelPricesRequestBody instance,
) => <String, dynamic>{'prices': instance.prices};
