// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data381.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data381 _$Data381FromJson(Map<String, dynamic> json) => Data381(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data381ToJson(Data381 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
