// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data299.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data299 _$Data299FromJson(Map<String, dynamic> json) => Data299(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  smsVerificationRequired: json['sms_verification_required'] as bool,
);

Map<String, dynamic> _$Data299ToJson(Data299 instance) => <String, dynamic>{
  'user': instance.user,
  'store': instance.store,
  'sms_verification_required': instance.smsVerificationRequired,
};
