// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data237.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data237 _$Data237FromJson(Map<String, dynamic> json) => Data237(
  schedule: Schedule.fromJson(json['schedule'] as Map<String, dynamic>),
  entry: Entry.fromJson(json['entry'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data237ToJson(Data237 instance) => <String, dynamic>{
  'schedule': instance.schedule,
  'entry': instance.entry,
};
