// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data48.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data48 _$Data48FromJson(Map<String, dynamic> json) => Data48(
  code: json['code'] as String,
  expiresAt: json['expires_at'] as String,
  type: json['type'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data48ToJson(Data48 instance) => <String, dynamic>{
  'code': instance.code,
  'expires_at': instance.expiresAt,
  'type': instance.type,
  'branch_id': instance.branchId,
};
