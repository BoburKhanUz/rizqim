// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_cancel_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidCancelResponse _$PostV1PosOrdersUuidCancelResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidCancelResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data177.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidCancelResponseToJson(
  PostV1PosOrdersUuidCancelResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
