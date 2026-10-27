// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_admin_auth2fa_verify_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1AdminAuth2faVerifyRequestBody _$V1AdminAuth2faVerifyRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1AdminAuth2faVerifyRequestBody(
  challengeToken: json['challenge_token'] as String,
  code: json['code'] as String?,
  recoveryCode: json['recovery_code'] as String?,
);

Map<String, dynamic> _$V1AdminAuth2faVerifyRequestBodyToJson(
  V1AdminAuth2faVerifyRequestBody instance,
) => <String, dynamic>{
  'challenge_token': instance.challengeToken,
  'code': instance.code,
  'recovery_code': instance.recoveryCode,
};
