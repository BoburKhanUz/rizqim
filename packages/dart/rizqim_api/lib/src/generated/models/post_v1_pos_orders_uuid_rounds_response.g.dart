// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_rounds_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidRoundsResponse _$PostV1PosOrdersUuidRoundsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidRoundsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data168.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidRoundsResponseToJson(
  PostV1PosOrdersUuidRoundsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
