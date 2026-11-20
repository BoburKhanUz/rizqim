// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data375.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data375 _$Data375FromJson(Map<String, dynamic> json) => Data375(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data375ToJson(Data375 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
