// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data138.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data138 _$Data138FromJson(Map<String, dynamic> json) => Data138(
  token: json['token'] as String,
  permission: json['permission'] as String,
  expiresAt: json['expires_at'] as String,
  approvedBy: ApprovedBy.fromJson(json['approved_by'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data138ToJson(Data138 instance) => <String, dynamic>{
  'token': instance.token,
  'permission': instance.permission,
  'expires_at': instance.expiresAt,
  'approved_by': instance.approvedBy,
};
