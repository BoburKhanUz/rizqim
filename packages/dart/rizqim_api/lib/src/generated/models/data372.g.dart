// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data372.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data372 _$Data372FromJson(Map<String, dynamic> json) => Data372(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data372ToJson(Data372 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
