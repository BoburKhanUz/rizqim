// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data243.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data243 _$Data243FromJson(Map<String, dynamic> json) => Data243(
  schedule: Schedule2.fromJson(json['schedule'] as Map<String, dynamic>),
  entry: Entry2.fromJson(json['entry'] as Map<String, dynamic>),
  fiscal: Fiscal22.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data243ToJson(Data243 instance) => <String, dynamic>{
  'schedule': instance.schedule,
  'entry': instance.entry,
  'fiscal': instance.fiscal,
};
