// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data64.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data64 _$Data64FromJson(Map<String, dynamic> json) => Data64(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data64ToJson(Data64 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
