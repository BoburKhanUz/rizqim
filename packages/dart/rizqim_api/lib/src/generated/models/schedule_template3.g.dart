// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'schedule_template3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ScheduleTemplate3 _$ScheduleTemplate3FromJson(Map<String, dynamic> json) =>
    ScheduleTemplate3(
      kind: Kind3.fromJson(json['kind'] as String),
      percent: json['percent'] as num?,
      daysBefore: (json['days_before'] as num?)?.toInt(),
    );

Map<String, dynamic> _$ScheduleTemplate3ToJson(ScheduleTemplate3 instance) =>
    <String, dynamic>{
      'kind': instance.kind,
      'percent': instance.percent,
      'days_before': instance.daysBefore,
    };
