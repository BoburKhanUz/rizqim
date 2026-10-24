// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data171.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data171 _$Data171FromJson(Map<String, dynamic> json) => Data171(
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

Map<String, dynamic> _$Data171ToJson(Data171 instance) => <String, dynamic>{
  'shift': instance.shift,
  'report': instance.report,
  'shifts': instance.shifts,
};
