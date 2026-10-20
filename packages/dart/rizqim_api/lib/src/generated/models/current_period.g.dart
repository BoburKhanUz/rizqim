// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'current_period.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CurrentPeriod _$CurrentPeriodFromJson(Map<String, dynamic> json) =>
    CurrentPeriod(
      period: json['period'] as String,
      start: json['start'] as String,
      end: json['end'] as String,
    );

Map<String, dynamic> _$CurrentPeriodToJson(CurrentPeriod instance) =>
    <String, dynamic>{
      'period': instance.period,
      'start': instance.start,
      'end': instance.end,
    };
