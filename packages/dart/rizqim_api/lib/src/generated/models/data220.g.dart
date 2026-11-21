// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data220.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data220 _$Data220FromJson(Map<String, dynamic> json) => Data220(
  token: json['token'] as String,
  permission: json['permission'] as String,
  expiresAt: json['expires_at'] as String,
  approvedBy: ApprovedBy.fromJson(json['approved_by'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data220ToJson(Data220 instance) => <String, dynamic>{
  'token': instance.token,
  'permission': instance.permission,
  'expires_at': instance.expiresAt,
  'approved_by': instance.approvedBy,
};
