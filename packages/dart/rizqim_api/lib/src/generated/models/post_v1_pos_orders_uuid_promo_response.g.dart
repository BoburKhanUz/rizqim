// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_promo_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidPromoResponse _$PostV1PosOrdersUuidPromoResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidPromoResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data268.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidPromoResponseToJson(
  PostV1PosOrdersUuidPromoResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
