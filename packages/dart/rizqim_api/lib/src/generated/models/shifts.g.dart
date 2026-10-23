// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'shifts.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Shifts _$ShiftsFromJson(Map<String, dynamic> json) => Shifts(
  shift: Shift3.fromJson(json['shift'] as Map<String, dynamic>),
  report: Report2.fromJson(json['report'] as Map<String, dynamic>),
);

Map<String, dynamic> _$ShiftsToJson(Shifts instance) => <String, dynamic>{
  'shift': instance.shift,
  'report': instance.report,
};
