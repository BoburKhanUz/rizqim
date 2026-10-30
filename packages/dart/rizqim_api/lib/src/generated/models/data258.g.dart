// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data258.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data258 _$Data258FromJson(Map<String, dynamic> json) => Data258(
  shift: json['shift'] == null
      ? null
      : Shift2.fromJson(json['shift'] as Map<String, dynamic>),
  report: json['report'] == null
      ? null
      : Report.fromJson(json['report'] as Map<String, dynamic>),
  shifts: (json['shifts'] as List<dynamic>)
      .map((e) => Shifts4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data258ToJson(Data258 instance) => <String, dynamic>{
  'shift': instance.shift,
  'report': instance.report,
  'shifts': instance.shifts,
};
