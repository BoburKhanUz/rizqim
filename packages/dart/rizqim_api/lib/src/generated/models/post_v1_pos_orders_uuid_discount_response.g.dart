// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_discount_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidDiscountResponse
_$PostV1PosOrdersUuidDiscountResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosOrdersUuidDiscountResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data184.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosOrdersUuidDiscountResponseToJson(
  PostV1PosOrdersUuidDiscountResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
