// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_auth_phone_send_code_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AuthPhoneSendCodeResponse _$PostV1AuthPhoneSendCodeResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AuthPhoneSendCodeResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data2.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1AuthPhoneSendCodeResponseToJson(
  PostV1AuthPhoneSendCodeResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
