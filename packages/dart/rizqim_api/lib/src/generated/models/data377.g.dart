// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data377.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data377 _$Data377FromJson(Map<String, dynamic> json) => Data377(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data377ToJson(Data377 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
