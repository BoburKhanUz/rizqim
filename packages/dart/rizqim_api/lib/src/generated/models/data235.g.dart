// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data235.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data235 _$Data235FromJson(Map<String, dynamic> json) => Data235(
  schedule: Schedule.fromJson(json['schedule'] as Map<String, dynamic>),
  entry: Entry.fromJson(json['entry'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data235ToJson(Data235 instance) => <String, dynamic>{
  'schedule': instance.schedule,
  'entry': instance.entry,
};
