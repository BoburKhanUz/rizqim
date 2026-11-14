// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data234.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data234 _$Data234FromJson(Map<String, dynamic> json) => Data234(
  schedule: Schedule.fromJson(json['schedule'] as Map<String, dynamic>),
  entry: Entry.fromJson(json['entry'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data234ToJson(Data234 instance) => <String, dynamic>{
  'schedule': instance.schedule,
  'entry': instance.entry,
};
