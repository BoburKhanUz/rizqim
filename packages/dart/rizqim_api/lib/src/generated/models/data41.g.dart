// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data41.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data41 _$Data41FromJson(Map<String, dynamic> json) => Data41(
  code: json['code'] as String,
  expiresAt: json['expires_at'] as String,
  type: json['type'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data41ToJson(Data41 instance) => <String, dynamic>{
  'code': instance.code,
  'expires_at': instance.expiresAt,
  'type': instance.type,
  'branch_id': instance.branchId,
};
