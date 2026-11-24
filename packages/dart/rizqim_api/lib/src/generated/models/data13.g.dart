// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data13.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data13 _$Data13FromJson(Map<String, dynamic> json) => Data13(
  token: json['token'] as String,
  expiresAt: json['expires_at'] as String,
  user: User8.fromJson(json['user'] as Map<String, dynamic>),
  permissions: (json['permissions'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Data13ToJson(Data13 instance) => <String, dynamic>{
  'token': instance.token,
  'expires_at': instance.expiresAt,
  'user': instance.user,
  'permissions': instance.permissions,
};
