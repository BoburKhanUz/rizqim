// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_admin_auth2fa_confirm_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AdminAuth2faConfirmResponse _$PostV1AdminAuth2faConfirmResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AdminAuth2faConfirmResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data3.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1AdminAuth2faConfirmResponseToJson(
  PostV1AdminAuth2faConfirmResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
