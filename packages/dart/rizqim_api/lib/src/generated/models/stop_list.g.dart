// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'stop_list.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

StopList _$StopListFromJson(Map<String, dynamic> json) => StopList(
  id: (json['id'] as num?)?.toInt(),
  type: json['type'] as String,
  subjectId: (json['subject_id'] as num).toInt(),
  reason: json['reason'] as String?,
  until: json['until'] as String?,
);

Map<String, dynamic> _$StopListToJson(StopList instance) => <String, dynamic>{
  'id': instance.id,
  'type': instance.type,
  'subject_id': instance.subjectId,
  'reason': instance.reason,
  'until': instance.until,
};
