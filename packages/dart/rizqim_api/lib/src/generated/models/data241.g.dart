// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data241.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data241 _$Data241FromJson(Map<String, dynamic> json) => Data241(
  schedule: Schedule2.fromJson(json['schedule'] as Map<String, dynamic>),
  entry: Entry2.fromJson(json['entry'] as Map<String, dynamic>),
  fiscal: Fiscal22.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data241ToJson(Data241 instance) => <String, dynamic>{
  'schedule': instance.schedule,
  'entry': instance.entry,
  'fiscal': instance.fiscal,
};
