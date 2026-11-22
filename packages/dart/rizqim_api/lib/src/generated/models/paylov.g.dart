// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'paylov.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Paylov _$PaylovFromJson(Map<String, dynamic> json) => Paylov(
  enabled: json['enabled'] as bool?,
  merchantId: json['merchant_id'] as String?,
  consumerKey: json['consumer_key'] as String?,
  consumerSecret: json['consumer_secret'] as String?,
  username: json['username'] as String?,
  password: json['password'] as String?,
  callbackLogin: json['callback_login'] as String?,
  callbackPassword: json['callback_password'] as String?,
);

Map<String, dynamic> _$PaylovToJson(Paylov instance) => <String, dynamic>{
  'enabled': instance.enabled,
  'merchant_id': instance.merchantId,
  'consumer_key': instance.consumerKey,
  'consumer_secret': instance.consumerSecret,
  'username': instance.username,
  'password': instance.password,
  'callback_login': instance.callbackLogin,
  'callback_password': instance.callbackPassword,
};
