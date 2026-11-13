// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data286.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data286 _$Data286FromJson(Map<String, dynamic> json) => Data286(
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

Map<String, dynamic> _$Data286ToJson(Data286 instance) => <String, dynamic>{
  'shift': instance.shift,
  'report': instance.report,
  'shifts': instance.shifts,
};
