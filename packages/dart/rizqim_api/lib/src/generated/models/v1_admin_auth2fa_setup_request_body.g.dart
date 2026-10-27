// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_admin_auth2fa_setup_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1AdminAuth2faSetupRequestBody _$V1AdminAuth2faSetupRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1AdminAuth2faSetupRequestBody(
  challengeToken: json['challenge_token'] as String,
);

Map<String, dynamic> _$V1AdminAuth2faSetupRequestBodyToJson(
  V1AdminAuth2faSetupRequestBody instance,
) => <String, dynamic>{'challenge_token': instance.challengeToken};
