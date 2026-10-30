// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data291.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data291 _$Data291FromJson(Map<String, dynamic> json) => Data291(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data291ToJson(Data291 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
