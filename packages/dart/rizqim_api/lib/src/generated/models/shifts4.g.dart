// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'shifts4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Shifts4 _$Shifts4FromJson(Map<String, dynamic> json) => Shifts4(
  shift: Shift3.fromJson(json['shift'] as Map<String, dynamic>),
  report: Report2.fromJson(json['report'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Shifts4ToJson(Shifts4 instance) => <String, dynamic>{
  'shift': instance.shift,
  'report': instance.report,
};
