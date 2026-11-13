// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data266.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data266 _$Data266FromJson(Map<String, dynamic> json) => Data266(
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

Map<String, dynamic> _$Data266ToJson(Data266 instance) => <String, dynamic>{
  'shift': instance.shift,
  'report': instance.report,
  'shifts': instance.shifts,
};
