// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data6 _$Data6FromJson(Map<String, dynamic> json) => Data6(
  token: json['token'] as String,
  expiresAt: json['expires_at'] as String,
  member: Member2.fromJson(json['member'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data6ToJson(Data6 instance) => <String, dynamic>{
  'token': instance.token,
  'expires_at': instance.expiresAt,
  'member': instance.member,
};
