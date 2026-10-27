// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data194.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data194 _$Data194FromJson(Map<String, dynamic> json) => Data194(
  shift: json['shift'] == null
      ? null
      : Shift2.fromJson(json['shift'] as Map<String, dynamic>),
  report: json['report'] == null
      ? null
      : Report.fromJson(json['report'] as Map<String, dynamic>),
  shifts: (json['shifts'] as List<dynamic>)
      .map((e) => Shifts.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data194ToJson(Data194 instance) => <String, dynamic>{
  'shift': instance.shift,
  'report': instance.report,
  'shifts': instance.shifts,
};
