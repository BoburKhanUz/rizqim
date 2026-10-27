// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_devices_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1DevicesResponse _$PostV1DevicesResponseFromJson(
  Map<String, dynamic> json,
) => PostV1DevicesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data31.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1DevicesResponseToJson(
  PostV1DevicesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
