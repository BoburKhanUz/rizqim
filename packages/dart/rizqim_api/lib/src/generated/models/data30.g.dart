// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data30.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data30 _$Data30FromJson(Map<String, dynamic> json) => Data30(
  code: json['code'] as String,
  expiresAt: json['expires_at'] as String,
  type: json['type'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data30ToJson(Data30 instance) => <String, dynamic>{
  'code': instance.code,
  'expires_at': instance.expiresAt,
  'type': instance.type,
  'branch_id': instance.branchId,
};
