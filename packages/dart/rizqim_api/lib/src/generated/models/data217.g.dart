// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data217.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data217 _$Data217FromJson(Map<String, dynamic> json) => Data217(
  token: json['token'] as String,
  permission: json['permission'] as String,
  expiresAt: json['expires_at'] as String,
  approvedBy: ApprovedBy.fromJson(json['approved_by'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data217ToJson(Data217 instance) => <String, dynamic>{
  'token': instance.token,
  'permission': instance.permission,
  'expires_at': instance.expiresAt,
  'approved_by': instance.approvedBy,
};
