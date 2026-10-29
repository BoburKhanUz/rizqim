// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'device20.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Device20 _$Device20FromJson(Map<String, dynamic> json) => Device20(
  uuid: json['uuid'] as String,
  name: json['name'] as String?,
  prefix: json['prefix'] as String?,
  lastSeq: (json['last_seq'] as num).toInt(),
  pendingOps: (json['pending_ops'] as num).toInt(),
  oldestOpAt: json['oldest_op_at'] as String?,
  reportedAt: json['reported_at'] as String?,
  clockSkewSeconds: (json['clock_skew_seconds'] as num?)?.toInt(),
  clockWarning: json['clock_warning'] as bool,
);

Map<String, dynamic> _$Device20ToJson(Device20 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'name': instance.name,
  'prefix': instance.prefix,
  'last_seq': instance.lastSeq,
  'pending_ops': instance.pendingOps,
  'oldest_op_at': instance.oldestOpAt,
  'reported_at': instance.reportedAt,
  'clock_skew_seconds': instance.clockSkewSeconds,
  'clock_warning': instance.clockWarning,
};
