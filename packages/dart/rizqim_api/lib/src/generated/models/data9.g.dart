// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data9.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data9 _$Data9FromJson(Map<String, dynamic> json) => Data9(
  status: json['status'] as String,
  challengeToken: json['challenge_token'] as String,
  expiresAt: json['expires_at'] as String,
);

Map<String, dynamic> _$Data9ToJson(Data9 instance) => <String, dynamic>{
  'status': instance.status,
  'challenge_token': instance.challengeToken,
  'expires_at': instance.expiresAt,
};
