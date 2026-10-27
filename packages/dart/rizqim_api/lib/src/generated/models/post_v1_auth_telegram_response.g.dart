// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_auth_telegram_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AuthTelegramResponse _$PostV1AuthTelegramResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AuthTelegramResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data5.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1AuthTelegramResponseToJson(
  PostV1AuthTelegramResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
