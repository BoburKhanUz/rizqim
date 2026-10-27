// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data117.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data117 _$Data117FromJson(Map<String, dynamic> json) => Data117(
  token: json['token'] as String,
  permission: json['permission'] as String,
  expiresAt: json['expires_at'] as String,
  approvedBy: ApprovedBy.fromJson(json['approved_by'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data117ToJson(Data117 instance) => <String, dynamic>{
  'token': instance.token,
  'permission': instance.permission,
  'expires_at': instance.expiresAt,
  'approved_by': instance.approvedBy,
};
