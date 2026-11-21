// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data378.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data378 _$Data378FromJson(Map<String, dynamic> json) => Data378(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data378ToJson(Data378 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
