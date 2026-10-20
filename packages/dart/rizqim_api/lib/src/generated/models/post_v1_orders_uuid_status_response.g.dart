// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_orders_uuid_status_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OrdersUuidStatusResponse _$PostV1OrdersUuidStatusResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OrdersUuidStatusResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Order.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OrdersUuidStatusResponseToJson(
  PostV1OrdersUuidStatusResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
