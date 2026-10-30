// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_call_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidCallResponse _$PostV1PosOrdersUuidCallResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidCallResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data211.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidCallResponseToJson(
  PostV1PosOrdersUuidCallResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
