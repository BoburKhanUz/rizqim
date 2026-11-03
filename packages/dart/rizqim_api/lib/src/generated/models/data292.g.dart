// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data292.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data292 _$Data292FromJson(Map<String, dynamic> json) => Data292(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data292ToJson(Data292 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
