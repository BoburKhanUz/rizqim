// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data193.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data193 _$Data193FromJson(Map<String, dynamic> json) => Data193(
  token: json['token'] as String,
  permission: json['permission'] as String,
  expiresAt: json['expires_at'] as String,
  approvedBy: ApprovedBy.fromJson(json['approved_by'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data193ToJson(Data193 instance) => <String, dynamic>{
  'token': instance.token,
  'permission': instance.permission,
  'expires_at': instance.expiresAt,
  'approved_by': instance.approvedBy,
};
