// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_admin_auth_logout_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AdminAuthLogoutResponse _$PostV1AdminAuthLogoutResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AdminAuthLogoutResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'] as Map<String, dynamic>,
);

Map<String, dynamic> _$PostV1AdminAuthLogoutResponseToJson(
  PostV1AdminAuthLogoutResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
