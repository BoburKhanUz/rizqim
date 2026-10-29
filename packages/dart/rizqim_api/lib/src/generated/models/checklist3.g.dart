// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'checklist3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Checklist3 _$Checklist3FromJson(Map<String, dynamic> json) => Checklist3(
  key: json['key'] as String,
  label: json['label'] as String,
  hint: json['hint'] as String,
  done: json['done'] as bool,
  doneAt: json['done_at'] as String?,
  doneBy: json['done_by'] == null
      ? null
      : DoneBy2.fromJson(json['done_by'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Checklist3ToJson(Checklist3 instance) =>
    <String, dynamic>{
      'key': instance.key,
      'label': instance.label,
      'hint': instance.hint,
      'done': instance.done,
      'done_at': instance.doneAt,
      'done_by': instance.doneBy,
    };
