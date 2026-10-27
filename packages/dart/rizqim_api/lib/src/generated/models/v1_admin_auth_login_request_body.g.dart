// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_admin_auth_login_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1AdminAuthLoginRequestBody _$V1AdminAuthLoginRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1AdminAuthLoginRequestBody(
  email: json['email'] as String,
  password: json['password'] as String,
);

Map<String, dynamic> _$V1AdminAuthLoginRequestBodyToJson(
  V1AdminAuthLoginRequestBody instance,
) => <String, dynamic>{'email': instance.email, 'password': instance.password};
