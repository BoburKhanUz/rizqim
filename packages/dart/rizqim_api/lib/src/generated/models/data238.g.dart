// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data238.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data238 _$Data238FromJson(Map<String, dynamic> json) => Data238(
  schedule: Schedule2.fromJson(json['schedule'] as Map<String, dynamic>),
  entry: Entry2.fromJson(json['entry'] as Map<String, dynamic>),
  fiscal: Fiscal22.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data238ToJson(Data238 instance) => <String, dynamic>{
  'schedule': instance.schedule,
  'entry': instance.entry,
  'fiscal': instance.fiscal,
};
