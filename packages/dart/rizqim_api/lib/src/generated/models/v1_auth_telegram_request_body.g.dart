// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_auth_telegram_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1AuthTelegramRequestBody _$V1AuthTelegramRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1AuthTelegramRequestBody(
  initData: json['initData'] as String,
  botUsername: json['bot_username'] as String,
  startParam: json['start_param'] as String?,
  device: json['device'] == null
      ? null
      : Device8.fromJson(json['device'] as Map<String, dynamic>),
);

Map<String, dynamic> _$V1AuthTelegramRequestBodyToJson(
  V1AuthTelegramRequestBody instance,
) => <String, dynamic>{
  'initData': instance.initData,
  'bot_username': instance.botUsername,
  'start_param': instance.startParam,
  'device': instance.device,
};
