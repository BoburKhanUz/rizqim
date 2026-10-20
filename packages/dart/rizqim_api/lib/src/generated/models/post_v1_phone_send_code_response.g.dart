// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_phone_send_code_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PhoneSendCodeResponse _$PostV1PhoneSendCodeResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PhoneSendCodeResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  code: json['code'] as String?,
);

Map<String, dynamic> _$PostV1PhoneSendCodeResponseToJson(
  PostV1PhoneSendCodeResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'code': instance.code,
};
