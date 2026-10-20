// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data31.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data31 _$Data31FromJson(Map<String, dynamic> json) => Data31(
  code: json['code'] as String,
  expiresAt: json['expires_at'] as String,
  type: json['type'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data31ToJson(Data31 instance) => <String, dynamic>{
  'code': instance.code,
  'expires_at': instance.expiresAt,
  'type': instance.type,
  'branch_id': instance.branchId,
};
