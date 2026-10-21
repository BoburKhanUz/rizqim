// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data65.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data65 _$Data65FromJson(Map<String, dynamic> json) => Data65(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data65ToJson(Data65 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
