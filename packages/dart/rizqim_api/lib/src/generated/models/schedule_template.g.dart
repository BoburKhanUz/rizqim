// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'schedule_template.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ScheduleTemplate _$ScheduleTemplateFromJson(Map<String, dynamic> json) =>
    ScheduleTemplate(
      kind: json['kind'] as String,
      percent: json['percent'] as String?,
      daysBefore: (json['days_before'] as num?)?.toInt(),
    );

Map<String, dynamic> _$ScheduleTemplateToJson(ScheduleTemplate instance) =>
    <String, dynamic>{
      'kind': instance.kind,
      'percent': instance.percent,
      'days_before': instance.daysBefore,
    };
