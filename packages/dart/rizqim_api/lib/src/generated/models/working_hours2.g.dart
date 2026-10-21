// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'working_hours2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

WorkingHours2 _$WorkingHours2FromJson(Map<String, dynamic> json) =>
    WorkingHours2(
      open: json['open'] as String?,
      close: json['close'] as String?,
      allDay: json['all_day'] as bool,
      overnight: json['overnight'] as bool,
      timezone: json['timezone'] as String,
    );

Map<String, dynamic> _$WorkingHours2ToJson(WorkingHours2 instance) =>
    <String, dynamic>{
      'open': instance.open,
      'close': instance.close,
      'all_day': instance.allDay,
      'overnight': instance.overnight,
      'timezone': instance.timezone,
    };
