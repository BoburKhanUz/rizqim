// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data47.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data47 _$Data47FromJson(Map<String, dynamic> json) => Data47(
  code: json['code'] as String,
  expiresAt: json['expires_at'] as String,
  type: json['type'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data47ToJson(Data47 instance) => <String, dynamic>{
  'code': instance.code,
  'expires_at': instance.expiresAt,
  'type': instance.type,
  'branch_id': instance.branchId,
};
