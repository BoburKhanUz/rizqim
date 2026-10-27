// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_admin_auth2fa_verify_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AdminAuth2faVerifyResponse _$PostV1AdminAuth2faVerifyResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AdminAuth2faVerifyResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data6.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1AdminAuth2faVerifyResponseToJson(
  PostV1AdminAuth2faVerifyResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
