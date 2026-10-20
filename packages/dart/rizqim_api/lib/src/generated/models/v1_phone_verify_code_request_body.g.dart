// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_phone_verify_code_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PhoneVerifyCodeRequestBody _$V1PhoneVerifyCodeRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PhoneVerifyCodeRequestBody(
  phone: json['phone'] as String,
  code: json['code'] as String,
);

Map<String, dynamic> _$V1PhoneVerifyCodeRequestBodyToJson(
  V1PhoneVerifyCodeRequestBody instance,
) => <String, dynamic>{'phone': instance.phone, 'code': instance.code};
