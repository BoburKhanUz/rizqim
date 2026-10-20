// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_auth_phone_verify_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1AuthPhoneVerifyRequestBody _$V1AuthPhoneVerifyRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1AuthPhoneVerifyRequestBody(
  phone: json['phone'] as String,
  code: json['code'] as String,
  device: json['device'] == null
      ? null
      : Device4.fromJson(json['device'] as Map<String, dynamic>),
  account: json['account'] == null
      ? null
      : Account.fromJson(json['account'] as String),
);

Map<String, dynamic> _$V1AuthPhoneVerifyRequestBodyToJson(
  V1AuthPhoneVerifyRequestBody instance,
) => <String, dynamic>{
  'device': instance.device,
  'phone': instance.phone,
  'code': instance.code,
  'account': instance.account,
};
