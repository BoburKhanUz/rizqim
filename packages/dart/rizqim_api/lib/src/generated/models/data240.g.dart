// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data240.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data240 _$Data240FromJson(Map<String, dynamic> json) => Data240(
  schedule: Schedule.fromJson(json['schedule'] as Map<String, dynamic>),
  entry: Entry.fromJson(json['entry'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data240ToJson(Data240 instance) => <String, dynamic>{
  'schedule': instance.schedule,
  'entry': instance.entry,
};
