// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data224.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data224 _$Data224FromJson(Map<String, dynamic> json) => Data224(
  token: json['token'] as String,
  permission: json['permission'] as String,
  expiresAt: json['expires_at'] as String,
  approvedBy: ApprovedBy.fromJson(json['approved_by'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data224ToJson(Data224 instance) => <String, dynamic>{
  'token': instance.token,
  'permission': instance.permission,
  'expires_at': instance.expiresAt,
  'approved_by': instance.approvedBy,
};
