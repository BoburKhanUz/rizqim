// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data264.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data264 _$Data264FromJson(Map<String, dynamic> json) => Data264(
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

Map<String, dynamic> _$Data264ToJson(Data264 instance) => <String, dynamic>{
  'shift': instance.shift,
  'report': instance.report,
  'shifts': instance.shifts,
};
