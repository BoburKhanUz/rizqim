// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data181.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data181 _$Data181FromJson(Map<String, dynamic> json) => Data181(
  opId: json['op_id'] as String,
  seq: (json['seq'] as num).toInt(),
  type: json['type'] as String,
  typeLabel: json['type_label'] as String,
  status: json['status'] as String,
  reason: json['reason'] as String?,
  message: json['message'] as String?,
  staff: json['staff'] == null
      ? null
      : Staff4.fromJson(json['staff'] as Map<String, dynamic>),
  device: json['device'] == null
      ? null
      : Device21.fromJson(json['device'] as Map<String, dynamic>),
  occurredAt: json['occurred_at'] as String?,
  receivedAt: json['received_at'] as String?,
  appliedAt: json['applied_at'] as String?,
  clockSkewSeconds: (json['clock_skew_seconds'] as num?)?.toInt(),
  order: json['order'] == null
      ? null
      : Order12.fromJson(json['order'] as Map<String, dynamic>),
  payload: json['payload'] as Map<String, dynamic>,
  refs: json['refs'] as Map<String, dynamic>,
  resolvedAt: json['resolved_at'] as String?,
  resolvedBy: json['resolved_by'] == null
      ? null
      : ResolvedBy2.fromJson(json['resolved_by'] as Map<String, dynamic>),
  resolution: json['resolution'] as String?,
  resolutionNote: json['resolution_note'] as String?,
);

Map<String, dynamic> _$Data181ToJson(Data181 instance) => <String, dynamic>{
  'op_id': instance.opId,
  'seq': instance.seq,
  'type': instance.type,
  'type_label': instance.typeLabel,
  'status': instance.status,
  'reason': instance.reason,
  'message': instance.message,
  'staff': instance.staff,
  'device': instance.device,
  'occurred_at': instance.occurredAt,
  'received_at': instance.receivedAt,
  'applied_at': instance.appliedAt,
  'clock_skew_seconds': instance.clockSkewSeconds,
  'order': instance.order,
  'payload': instance.payload,
  'refs': instance.refs,
  'resolved_at': instance.resolvedAt,
  'resolved_by': instance.resolvedBy,
  'resolution': instance.resolution,
  'resolution_note': instance.resolutionNote,
};
