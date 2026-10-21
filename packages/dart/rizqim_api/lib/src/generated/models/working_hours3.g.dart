// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'working_hours3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

WorkingHours3 _$WorkingHours3FromJson(Map<String, dynamic> json) =>
    WorkingHours3(
      open: json['open'] as String?,
      close: json['close'] as String?,
      allDay: json['all_day'] as bool,
      overnight: json['overnight'] as bool,
      timezone: json['timezone'] as String,
    );

Map<String, dynamic> _$WorkingHours3ToJson(WorkingHours3 instance) =>
    <String, dynamic>{
      'open': instance.open,
      'close': instance.close,
      'all_day': instance.allDay,
      'overnight': instance.overnight,
      'timezone': instance.timezone,
    };
