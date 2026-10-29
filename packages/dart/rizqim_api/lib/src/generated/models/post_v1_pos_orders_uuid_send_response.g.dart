// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_send_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidSendResponse _$PostV1PosOrdersUuidSendResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidSendResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data192.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidSendResponseToJson(
  PostV1PosOrdersUuidSendResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
