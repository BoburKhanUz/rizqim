// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data223.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data223 _$Data223FromJson(Map<String, dynamic> json) => Data223(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data223ToJson(Data223 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
