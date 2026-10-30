// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data59.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data59 _$Data59FromJson(Map<String, dynamic> json) => Data59(
  code: json['code'] as String,
  expiresAt: json['expires_at'] as String,
  type: json['type'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data59ToJson(Data59 instance) => <String, dynamic>{
  'code': instance.code,
  'expires_at': instance.expiresAt,
  'type': instance.type,
  'branch_id': instance.branchId,
};
