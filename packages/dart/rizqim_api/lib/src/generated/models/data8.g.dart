// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data8 _$Data8FromJson(Map<String, dynamic> json) => Data8(
  status: json['status'] as String,
  challengeToken: json['challenge_token'] as String,
  expiresAt: json['expires_at'] as String,
);

Map<String, dynamic> _$Data8ToJson(Data8 instance) => <String, dynamic>{
  'status': instance.status,
  'challenge_token': instance.challengeToken,
  'expires_at': instance.expiresAt,
};
