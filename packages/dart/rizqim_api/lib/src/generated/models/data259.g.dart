// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data259.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data259 _$Data259FromJson(Map<String, dynamic> json) => Data259(
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

Map<String, dynamic> _$Data259ToJson(Data259 instance) => <String, dynamic>{
  'shift': instance.shift,
  'report': instance.report,
  'shifts': instance.shifts,
};
