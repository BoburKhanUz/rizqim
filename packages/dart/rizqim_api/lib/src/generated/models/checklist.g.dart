// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'checklist.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Checklist _$ChecklistFromJson(Map<String, dynamic> json) => Checklist(
  key: json['key'] as String,
  label: json['label'] as String,
  hint: json['hint'] as String,
  done: json['done'] as bool,
  doneAt: json['done_at'] as String?,
  doneBy: json['done_by'] == null
      ? null
      : DoneBy.fromJson(json['done_by'] as Map<String, dynamic>),
);

Map<String, dynamic> _$ChecklistToJson(Checklist instance) => <String, dynamic>{
  'key': instance.key,
  'label': instance.label,
  'hint': instance.hint,
  'done': instance.done,
  'done_at': instance.doneAt,
  'done_by': instance.doneBy,
};
