// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data219.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data219 _$Data219FromJson(Map<String, dynamic> json) => Data219(
  token: json['token'] as String,
  permission: json['permission'] as String,
  expiresAt: json['expires_at'] as String,
  approvedBy: ApprovedBy.fromJson(json['approved_by'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data219ToJson(Data219 instance) => <String, dynamic>{
  'token': instance.token,
  'permission': instance.permission,
  'expires_at': instance.expiresAt,
  'approved_by': instance.approvedBy,
};
