// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data165.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data165 _$Data165FromJson(Map<String, dynamic> json) => Data165(
  shift: json['shift'] == null
      ? null
      : Shift.fromJson(json['shift'] as Map<String, dynamic>),
  report: json['report'] as Map<String, dynamic>?,
);

Map<String, dynamic> _$Data165ToJson(Data165 instance) => <String, dynamic>{
  'shift': instance.shift,
  'report': instance.report,
};
