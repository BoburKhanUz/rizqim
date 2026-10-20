// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data11.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data11 _$Data11FromJson(Map<String, dynamic> json) => Data11(
  token: json['token'] as String,
  expiresAt: json['expires_at'] as String,
  user: User7.fromJson(json['user'] as Map<String, dynamic>),
  permissions: (json['permissions'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Data11ToJson(Data11 instance) => <String, dynamic>{
  'token': instance.token,
  'expires_at': instance.expiresAt,
  'user': instance.user,
  'permissions': instance.permissions,
};
