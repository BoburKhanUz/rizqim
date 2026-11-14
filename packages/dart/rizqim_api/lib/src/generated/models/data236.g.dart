// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data236.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data236 _$Data236FromJson(Map<String, dynamic> json) => Data236(
  schedule: Schedule2.fromJson(json['schedule'] as Map<String, dynamic>),
  entry: Entry2.fromJson(json['entry'] as Map<String, dynamic>),
  fiscal: Fiscal22.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data236ToJson(Data236 instance) => <String, dynamic>{
  'schedule': instance.schedule,
  'entry': instance.entry,
  'fiscal': instance.fiscal,
};
