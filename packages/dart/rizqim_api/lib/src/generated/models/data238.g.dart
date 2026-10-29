// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data238.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data238 _$Data238FromJson(Map<String, dynamic> json) => Data238(
  shift: json['shift'] == null
      ? null
      : Shift2.fromJson(json['shift'] as Map<String, dynamic>),
  report: json['report'] == null
      ? null
      : Report.fromJson(json['report'] as Map<String, dynamic>),
  shifts: (json['shifts'] as List<dynamic>)
      .map((e) => Shifts3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data238ToJson(Data238 instance) => <String, dynamic>{
  'shift': instance.shift,
  'report': instance.report,
  'shifts': instance.shifts,
};
