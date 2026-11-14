// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'schedule2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Schedule2 _$Schedule2FromJson(Map<String, dynamic> json) => Schedule2(
  event: Event8.fromJson(json['event'] as Map<String, dynamic>),
  summary: Summary10.fromJson(json['summary'] as Map<String, dynamic>),
  rows: (json['rows'] as List<dynamic>)
      .map((e) => Rows12.fromJson(e as Map<String, dynamic>))
      .toList(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries9.fromJson(e as Map<String, dynamic>))
      .toList(),
  cancellation: json['cancellation'] == null
      ? null
      : Cancellation6.fromJson(json['cancellation'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Schedule2ToJson(Schedule2 instance) => <String, dynamic>{
  'event': instance.event,
  'summary': instance.summary,
  'rows': instance.rows,
  'entries': instance.entries,
  'cancellation': instance.cancellation,
};
