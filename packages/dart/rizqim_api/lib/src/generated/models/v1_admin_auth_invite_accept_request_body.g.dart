// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_admin_auth_invite_accept_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1AdminAuthInviteAcceptRequestBody _$V1AdminAuthInviteAcceptRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1AdminAuthInviteAcceptRequestBody(
  token: json['token'] as String,
  password: json['password'] as String,
);

Map<String, dynamic> _$V1AdminAuthInviteAcceptRequestBodyToJson(
  V1AdminAuthInviteAcceptRequestBody instance,
) => <String, dynamic>{'token': instance.token, 'password': instance.password};
