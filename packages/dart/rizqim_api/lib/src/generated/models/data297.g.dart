// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data297.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data297 _$Data297FromJson(Map<String, dynamic> json) => Data297(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data297ToJson(Data297 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
