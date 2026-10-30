// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data72.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data72 _$Data72FromJson(Map<String, dynamic> json) => Data72(
  code: json['code'] as String,
  expiresAt: json['expires_at'] as String,
  type: json['type'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data72ToJson(Data72 instance) => <String, dynamic>{
  'code': instance.code,
  'expires_at': instance.expiresAt,
  'type': instance.type,
  'branch_id': instance.branchId,
};
