// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data96.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data96 _$Data96FromJson(Map<String, dynamic> json) => Data96(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data96ToJson(Data96 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
