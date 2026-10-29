// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data269.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data269 _$Data269FromJson(Map<String, dynamic> json) => Data269(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data269ToJson(Data269 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
