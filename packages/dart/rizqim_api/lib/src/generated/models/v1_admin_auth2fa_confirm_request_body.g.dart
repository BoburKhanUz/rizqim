// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_admin_auth2fa_confirm_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1AdminAuth2faConfirmRequestBody _$V1AdminAuth2faConfirmRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1AdminAuth2faConfirmRequestBody(
  challengeToken: json['challenge_token'] as String,
  code: json['code'] as String,
);

Map<String, dynamic> _$V1AdminAuth2faConfirmRequestBodyToJson(
  V1AdminAuth2faConfirmRequestBody instance,
) => <String, dynamic>{
  'challenge_token': instance.challengeToken,
  'code': instance.code,
};
