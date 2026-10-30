// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data166.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data166 _$Data166FromJson(Map<String, dynamic> json) => Data166(
  token: json['token'] as String,
  permission: json['permission'] as String,
  expiresAt: json['expires_at'] as String,
  approvedBy: ApprovedBy.fromJson(json['approved_by'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data166ToJson(Data166 instance) => <String, dynamic>{
  'token': instance.token,
  'permission': instance.permission,
  'expires_at': instance.expiresAt,
  'approved_by': instance.approvedBy,
};
