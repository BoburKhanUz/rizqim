// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_orders_uuid_cancel_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OrdersUuidCancelResponse _$PostV1OrdersUuidCancelResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OrdersUuidCancelResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Order.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OrdersUuidCancelResponseToJson(
  PostV1OrdersUuidCancelResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
