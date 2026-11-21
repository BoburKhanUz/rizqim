// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data239.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data239 _$Data239FromJson(Map<String, dynamic> json) => Data239(
  schedule: Schedule2.fromJson(json['schedule'] as Map<String, dynamic>),
  entry: Entry2.fromJson(json['entry'] as Map<String, dynamic>),
  fiscal: Fiscal22.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data239ToJson(Data239 instance) => <String, dynamic>{
  'schedule': instance.schedule,
  'entry': instance.entry,
  'fiscal': instance.fiscal,
};
