// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data182.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data182 _$Data182FromJson(Map<String, dynamic> json) => Data182(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data182ToJson(Data182 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
