// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data183.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data183 _$Data183FromJson(Map<String, dynamic> json) => Data183(
  token: json['token'] as String,
  permission: json['permission'] as String,
  expiresAt: json['expires_at'] as String,
  approvedBy: ApprovedBy.fromJson(json['approved_by'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data183ToJson(Data183 instance) => <String, dynamic>{
  'token': instance.token,
  'permission': instance.permission,
  'expires_at': instance.expiresAt,
  'approved_by': instance.approvedBy,
};
