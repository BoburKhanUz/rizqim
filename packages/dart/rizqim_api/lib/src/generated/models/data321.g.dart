// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data321.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data321 _$Data321FromJson(Map<String, dynamic> json) => Data321(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data321ToJson(Data321 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
