// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data12.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data12 _$Data12FromJson(Map<String, dynamic> json) => Data12(
  token: json['token'] as String,
  expiresAt: json['expires_at'] as String,
  user: User8.fromJson(json['user'] as Map<String, dynamic>),
  permissions: (json['permissions'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Data12ToJson(Data12 instance) => <String, dynamic>{
  'token': instance.token,
  'expires_at': instance.expiresAt,
  'user': instance.user,
  'permissions': instance.permissions,
};
