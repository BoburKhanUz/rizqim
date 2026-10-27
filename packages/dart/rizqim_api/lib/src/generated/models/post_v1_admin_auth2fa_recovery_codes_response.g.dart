// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_admin_auth2fa_recovery_codes_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AdminAuth2faRecoveryCodesResponse
_$PostV1AdminAuth2faRecoveryCodesResponseFromJson(Map<String, dynamic> json) =>
    PostV1AdminAuth2faRecoveryCodesResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data4.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1AdminAuth2faRecoveryCodesResponseToJson(
  PostV1AdminAuth2faRecoveryCodesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
