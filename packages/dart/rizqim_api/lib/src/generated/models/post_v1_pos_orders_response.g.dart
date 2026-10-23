// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersResponse _$PostV1PosOrdersResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data149.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersResponseToJson(
  PostV1PosOrdersResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
