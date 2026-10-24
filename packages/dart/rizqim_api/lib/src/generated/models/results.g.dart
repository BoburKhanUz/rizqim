// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'results.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Results _$ResultsFromJson(Map<String, dynamic> json) => Results(
  opId: json['op_id'] as String,
  seq: (json['seq'] as num).toInt(),
  type: json['type'] as String,
  status: json['status'] as String,
  reason: json['reason'] as String?,
  message: json['message'] as String?,
  duplicate: json['duplicate'] as bool,
  refs: json['refs'] as Map<String, dynamic>,
);

Map<String, dynamic> _$ResultsToJson(Results instance) => <String, dynamic>{
  'op_id': instance.opId,
  'seq': instance.seq,
  'type': instance.type,
  'status': instance.status,
  'reason': instance.reason,
  'message': instance.message,
  'duplicate': instance.duplicate,
  'refs': instance.refs,
};
