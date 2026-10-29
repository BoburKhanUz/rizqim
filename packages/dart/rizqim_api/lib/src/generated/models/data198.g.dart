// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data198.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data198 _$Data198FromJson(Map<String, dynamic> json) => Data198(
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

Map<String, dynamic> _$Data198ToJson(Data198 instance) => <String, dynamic>{
  'shift': instance.shift,
  'report': instance.report,
  'shifts': instance.shifts,
};
