// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data77.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data77 _$Data77FromJson(Map<String, dynamic> json) => Data77(
  id: (json['id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  type: json['type'] as String,
  subjectId: (json['subject_id'] as num).toInt(),
  subjectUuid: json['subject_uuid'],
  name: json['name'],
  reason: json['reason'] as String?,
  until: json['until'] as String?,
  createdBy: (json['created_by'] as num?)?.toInt(),
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$Data77ToJson(Data77 instance) => <String, dynamic>{
  'id': instance.id,
  'branch_id': instance.branchId,
  'type': instance.type,
  'subject_id': instance.subjectId,
  'subject_uuid': instance.subjectUuid,
  'name': instance.name,
  'reason': instance.reason,
  'until': instance.until,
  'created_by': instance.createdBy,
  'created_at': instance.createdAt,
};
