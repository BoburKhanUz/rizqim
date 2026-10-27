// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_admin_auth_login_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AdminAuthLoginResponse _$PostV1AdminAuthLoginResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AdminAuthLoginResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data9.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1AdminAuthLoginResponseToJson(
  PostV1AdminAuthLoginResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
