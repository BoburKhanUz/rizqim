// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'results2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Results2 _$Results2FromJson(Map<String, dynamic> json) => Results2(
  opId: json['op_id'] as String,
  seq: (json['seq'] as num).toInt(),
  type: json['type'] as String,
  status: json['status'] as String,
  reason: json['reason'] as String?,
  message: json['message'] as String?,
  duplicate: json['duplicate'] as bool,
  sigStatus: json['sig_status'] as String?,
  refs: json['refs'] as Map<String, dynamic>,
);

Map<String, dynamic> _$Results2ToJson(Results2 instance) => <String, dynamic>{
  'op_id': instance.opId,
  'seq': instance.seq,
  'type': instance.type,
  'status': instance.status,
  'reason': instance.reason,
  'message': instance.message,
  'duplicate': instance.duplicate,
  'sig_status': instance.sigStatus,
  'refs': instance.refs,
};
