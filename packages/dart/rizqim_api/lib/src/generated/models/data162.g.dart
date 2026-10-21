// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data162.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data162 _$Data162FromJson(Map<String, dynamic> json) => Data162(
  shift: json['shift'] == null
      ? null
      : Shift.fromJson(json['shift'] as Map<String, dynamic>),
  report: json['report'] as Map<String, dynamic>?,
);

Map<String, dynamic> _$Data162ToJson(Data162 instance) => <String, dynamic>{
  'shift': instance.shift,
  'report': instance.report,
};
