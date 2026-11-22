// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data382.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data382 _$Data382FromJson(Map<String, dynamic> json) => Data382(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data382ToJson(Data382 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
