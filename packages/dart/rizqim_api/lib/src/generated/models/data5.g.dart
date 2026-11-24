// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data5 _$Data5FromJson(Map<String, dynamic> json) => Data5(
  token: json['token'] as String,
  tokenExpiresAt: json['token_expires_at'] as String,
  refreshToken: json['refresh_token'] as String,
  refreshTokenExpiresAt: json['refresh_token_expires_at'] as String,
  device: Device5.fromJson(json['device'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data5ToJson(Data5 instance) => <String, dynamic>{
  'token': instance.token,
  'token_expires_at': instance.tokenExpiresAt,
  'refresh_token': instance.refreshToken,
  'refresh_token_expires_at': instance.refreshTokenExpiresAt,
  'device': instance.device,
};
