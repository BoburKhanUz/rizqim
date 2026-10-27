// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data17.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data17 _$Data17FromJson(Map<String, dynamic> json) => Data17(
  member: Member5.fromJson(json['member'] as Map<String, dynamic>),
  inviteUrl: json['invite_url'] as String,
  inviteToken: json['invite_token'] as String,
  inviteExpiresAt: json['invite_expires_at'] as String,
);

Map<String, dynamic> _$Data17ToJson(Data17 instance) => <String, dynamic>{
  'member': instance.member,
  'invite_url': instance.inviteUrl,
  'invite_token': instance.inviteToken,
  'invite_expires_at': instance.inviteExpiresAt,
};
