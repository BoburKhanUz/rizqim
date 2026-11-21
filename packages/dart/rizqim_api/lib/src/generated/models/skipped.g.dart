// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'skipped.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Skipped _$SkippedFromJson(Map<String, dynamic> json) => Skipped(
  line: (json['line'] as num).toInt(),
  opId: json['op_id'] as String?,
  seq: (json['seq'] as num?)?.toInt(),
  device: json['device'] as String?,
  reason: json['reason'] as String,
  message: json['message'] as String,
);

Map<String, dynamic> _$SkippedToJson(Skipped instance) => <String, dynamic>{
  'line': instance.line,
  'op_id': instance.opId,
  'seq': instance.seq,
  'device': instance.device,
  'reason': instance.reason,
  'message': instance.message,
};
