// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_handed_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidHandedResponse _$PostV1PosOrdersUuidHandedResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidHandedResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data183.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidHandedResponseToJson(
  PostV1PosOrdersUuidHandedResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
