// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_admin_auth2fa_setup_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AdminAuth2faSetupResponse _$PostV1AdminAuth2faSetupResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AdminAuth2faSetupResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data5.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1AdminAuth2faSetupResponseToJson(
  PostV1AdminAuth2faSetupResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
