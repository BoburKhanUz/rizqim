// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data26.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data26 _$Data26FromJson(Map<String, dynamic> json) => Data26(
  token: json['token'] as String,
  user: json['user'],
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
  tokenExpiresAt: json['token_expires_at'] as String?,
  refreshToken: json['refresh_token'] as String?,
  refreshTokenExpiresAt: json['refresh_token_expires_at'] as String?,
  device: json['device'] == null
      ? null
      : Device7.fromJson(json['device'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data26ToJson(Data26 instance) => <String, dynamic>{
  'token': instance.token,
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
  'token_expires_at': instance.tokenExpiresAt,
  'refresh_token': instance.refreshToken,
  'refresh_token_expires_at': instance.refreshTokenExpiresAt,
  'device': instance.device,
};
