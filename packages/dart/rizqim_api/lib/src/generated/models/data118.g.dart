// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data118.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data118 _$Data118FromJson(Map<String, dynamic> json) => Data118(
  token: json['token'] as String,
  permission: json['permission'] as String,
  expiresAt: json['expires_at'] as String,
  approvedBy: ApprovedBy.fromJson(json['approved_by'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data118ToJson(Data118 instance) => <String, dynamic>{
  'token': instance.token,
  'permission': instance.permission,
  'expires_at': instance.expiresAt,
  'approved_by': instance.approvedBy,
};
