// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data156.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data156 _$Data156FromJson(Map<String, dynamic> json) => Data156(
  branchId: (json['branch_id'] as num).toInt(),
  keyId: (json['key_id'] as num).toInt(),
  rotatedAt: json['rotated_at'] as String?,
  port: (json['port'] as num).toInt(),
  hub: json['hub'] == null
      ? null
      : Hub2.fromJson(json['hub'] as Map<String, dynamic>),
  candidates: (json['candidates'] as List<dynamic>)
      .map((e) => Candidates2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data156ToJson(Data156 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'key_id': instance.keyId,
  'rotated_at': instance.rotatedAt,
  'port': instance.port,
  'hub': instance.hub,
  'candidates': instance.candidates,
};
