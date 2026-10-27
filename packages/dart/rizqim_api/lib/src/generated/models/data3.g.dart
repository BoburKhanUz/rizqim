// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data3 _$Data3FromJson(Map<String, dynamic> json) => Data3(
  token: json['token'] as String,
  expiresAt: json['expires_at'] as String,
  member: Member.fromJson(json['member'] as Map<String, dynamic>),
  recoveryCodes: (json['recovery_codes'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Data3ToJson(Data3 instance) => <String, dynamic>{
  'token': instance.token,
  'expires_at': instance.expiresAt,
  'member': instance.member,
  'recovery_codes': instance.recoveryCodes,
};
