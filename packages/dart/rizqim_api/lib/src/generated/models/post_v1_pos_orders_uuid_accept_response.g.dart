// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_accept_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidAcceptResponse _$PostV1PosOrdersUuidAcceptResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidAcceptResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data208.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidAcceptResponseToJson(
  PostV1PosOrdersUuidAcceptResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
