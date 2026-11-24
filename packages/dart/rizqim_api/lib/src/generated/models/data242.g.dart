// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data242.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data242 _$Data242FromJson(Map<String, dynamic> json) => Data242(
  schedule: Schedule.fromJson(json['schedule'] as Map<String, dynamic>),
  entry: Entry.fromJson(json['entry'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data242ToJson(Data242 instance) => <String, dynamic>{
  'schedule': instance.schedule,
  'entry': instance.entry,
};
