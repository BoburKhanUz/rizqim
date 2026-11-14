// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'schedule_template2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ScheduleTemplate2 _$ScheduleTemplate2FromJson(Map<String, dynamic> json) =>
    ScheduleTemplate2(
      kind: json['kind'] as String,
      percent: json['percent'] as String?,
      daysBefore: (json['days_before'] as num?)?.toInt(),
    );

Map<String, dynamic> _$ScheduleTemplate2ToJson(ScheduleTemplate2 instance) =>
    <String, dynamic>{
      'kind': instance.kind,
      'percent': instance.percent,
      'days_before': instance.daysBefore,
    };
