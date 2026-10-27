// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_orders_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OrdersResponse _$PostV1OrdersResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OrdersResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data44.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OrdersResponseToJson(
  PostV1OrdersResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
