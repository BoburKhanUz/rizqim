// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data222.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data222 _$Data222FromJson(Map<String, dynamic> json) => Data222(
  token: json['token'] as String,
  permission: json['permission'] as String,
  expiresAt: json['expires_at'] as String,
  approvedBy: ApprovedBy.fromJson(json['approved_by'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data222ToJson(Data222 instance) => <String, dynamic>{
  'token': instance.token,
  'permission': instance.permission,
  'expires_at': instance.expiresAt,
  'approved_by': instance.approvedBy,
};
