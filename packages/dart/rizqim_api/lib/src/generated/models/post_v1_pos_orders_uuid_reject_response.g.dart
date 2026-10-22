// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_reject_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidRejectResponse _$PostV1PosOrdersUuidRejectResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidRejectResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data163.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidRejectResponseToJson(
  PostV1PosOrdersUuidRejectResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
