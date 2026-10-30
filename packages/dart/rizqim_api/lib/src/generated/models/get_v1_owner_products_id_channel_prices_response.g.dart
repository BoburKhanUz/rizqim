// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_products_id_channel_prices_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerProductsIdChannelPricesResponse
_$GetV1OwnerProductsIdChannelPricesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerProductsIdChannelPricesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data126.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerProductsIdChannelPricesResponseToJson(
  GetV1OwnerProductsIdChannelPricesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
