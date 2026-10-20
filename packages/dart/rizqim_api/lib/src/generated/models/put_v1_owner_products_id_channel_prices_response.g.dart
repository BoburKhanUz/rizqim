// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_products_id_channel_prices_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerProductsIdChannelPricesResponse
_$PutV1OwnerProductsIdChannelPricesResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerProductsIdChannelPricesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data44.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerProductsIdChannelPricesResponseToJson(
  PutV1OwnerProductsIdChannelPricesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
