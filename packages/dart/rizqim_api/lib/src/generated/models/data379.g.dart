// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data379.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data379 _$Data379FromJson(Map<String, dynamic> json) => Data379(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data379ToJson(Data379 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
