// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'entries2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Entries2 _$Entries2FromJson(Map<String, dynamic> json) => Entries2(
  id: (json['id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  type: json['type'] as String,
  subjectId: (json['subject_id'] as num).toInt(),
  subjectUuid: json['subject_uuid'],
  name: json['name'],
  reason: json['reason'] as String?,
  until: json['until'] as String?,
  createdBy: (json['created_by'] as num?)?.toInt(),
  createdByName: json['created_by_name'] as String?,
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$Entries2ToJson(Entries2 instance) => <String, dynamic>{
  'id': instance.id,
  'branch_id': instance.branchId,
  'type': instance.type,
  'subject_id': instance.subjectId,
  'subject_uuid': instance.subjectUuid,
  'name': instance.name,
  'reason': instance.reason,
  'until': instance.until,
  'created_by': instance.createdBy,
  'created_by_name': instance.createdByName,
  'created_at': instance.createdAt,
};
