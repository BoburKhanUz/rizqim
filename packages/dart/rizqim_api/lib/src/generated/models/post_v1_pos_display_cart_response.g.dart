// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_display_cart_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosDisplayCartResponse _$PostV1PosDisplayCartResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosDisplayCartResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data200.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosDisplayCartResponseToJson(
  PostV1PosDisplayCartResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
