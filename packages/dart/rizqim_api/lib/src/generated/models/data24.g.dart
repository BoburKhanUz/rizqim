// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data24.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data24 _$Data24FromJson(Map<String, dynamic> json) => Data24(
  token: json['token'] as String,
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
  isNewUser: json['is_new_user'] as bool,
  tokenExpiresAt: json['token_expires_at'] as String?,
  refreshToken: json['refresh_token'] as String?,
  refreshTokenExpiresAt: json['refresh_token_expires_at'] as String?,
  device: json['device'] == null
      ? null
      : Device4.fromJson(json['device'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data24ToJson(Data24 instance) => <String, dynamic>{
  'token': instance.token,
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
  'is_new_user': instance.isNewUser,
  'token_expires_at': instance.tokenExpiresAt,
  'refresh_token': instance.refreshToken,
  'refresh_token_expires_at': instance.refreshTokenExpiresAt,
  'device': instance.device,
};
