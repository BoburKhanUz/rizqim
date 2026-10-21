// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_auth_phone_verify_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AuthPhoneVerifyResponse _$PostV1AuthPhoneVerifyResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AuthPhoneVerifyResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data3.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1AuthPhoneVerifyResponseToJson(
  PostV1AuthPhoneVerifyResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
