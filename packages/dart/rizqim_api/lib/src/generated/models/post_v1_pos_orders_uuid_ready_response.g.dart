// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_ready_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidReadyResponse _$PostV1PosOrdersUuidReadyResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidReadyResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data188.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidReadyResponseToJson(
  PostV1PosOrdersUuidReadyResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
