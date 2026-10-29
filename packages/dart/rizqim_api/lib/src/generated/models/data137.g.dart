// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data137.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data137 _$Data137FromJson(Map<String, dynamic> json) => Data137(
  token: json['token'] as String,
  permission: json['permission'] as String,
  expiresAt: json['expires_at'] as String,
  approvedBy: ApprovedBy.fromJson(json['approved_by'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data137ToJson(Data137 instance) => <String, dynamic>{
  'token': instance.token,
  'permission': instance.permission,
  'expires_at': instance.expiresAt,
  'approved_by': instance.approvedBy,
};
