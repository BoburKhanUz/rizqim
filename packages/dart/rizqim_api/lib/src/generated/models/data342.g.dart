// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data342.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data342 _$Data342FromJson(Map<String, dynamic> json) => Data342(
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

Map<String, dynamic> _$Data342ToJson(Data342 instance) => <String, dynamic>{
  'shift': instance.shift,
  'report': instance.report,
  'shifts': instance.shifts,
};
