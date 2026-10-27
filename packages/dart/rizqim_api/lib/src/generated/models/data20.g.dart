// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data20.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data20 _$Data20FromJson(Map<String, dynamic> json) => Data20(
  inviteUrl: json['invite_url'] as String,
  inviteToken: json['invite_token'] as String,
  inviteExpiresAt: json['invite_expires_at'] as String,
);

Map<String, dynamic> _$Data20ToJson(Data20 instance) => <String, dynamic>{
  'invite_url': instance.inviteUrl,
  'invite_token': instance.inviteToken,
  'invite_expires_at': instance.inviteExpiresAt,
};
