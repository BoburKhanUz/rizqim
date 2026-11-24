// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data383.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data383 _$Data383FromJson(Map<String, dynamic> json) => Data383(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data383ToJson(Data383 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
