// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_close_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidCloseResponse _$PostV1PosOrdersUuidCloseResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidCloseResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data160.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidCloseResponseToJson(
  PostV1PosOrdersUuidCloseResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
