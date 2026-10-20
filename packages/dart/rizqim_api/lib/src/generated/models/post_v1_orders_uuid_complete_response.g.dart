// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_orders_uuid_complete_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OrdersUuidCompleteResponse _$PostV1OrdersUuidCompleteResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OrdersUuidCompleteResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Order.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OrdersUuidCompleteResponseToJson(
  PostV1OrdersUuidCompleteResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
