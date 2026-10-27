// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data46.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data46 _$Data46FromJson(Map<String, dynamic> json) => Data46(
  id: (json['id'] as num).toInt(),
  action: json['action'] as String,
  actor: json['actor'] == null
      ? null
      : Actor2.fromJson(json['actor'] as Map<String, dynamic>),
  device: json['device'] == null
      ? null
      : Device10.fromJson(json['device'] as Map<String, dynamic>),
  subjectType: json['subject_type'] as String?,
  subjectId: (json['subject_id'] as num?)?.toInt(),
  branchId: (json['branch_id'] as num?)?.toInt(),
  before: json['before'] as Map<String, dynamic>?,
  after: json['after'] as Map<String, dynamic>?,
  meta: json['meta'] as Map<String, dynamic>?,
  ip: json['ip'] as String?,
  createdAt: json['created_at'] as String,
);

Map<String, dynamic> _$Data46ToJson(Data46 instance) => <String, dynamic>{
  'id': instance.id,
  'action': instance.action,
  'actor': instance.actor,
  'device': instance.device,
  'subject_type': instance.subjectType,
  'subject_id': instance.subjectId,
  'branch_id': instance.branchId,
  'before': instance.before,
  'after': instance.after,
  'meta': instance.meta,
  'ip': instance.ip,
  'created_at': instance.createdAt,
};
