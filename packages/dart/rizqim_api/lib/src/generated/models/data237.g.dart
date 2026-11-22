// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data237.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data237 _$Data237FromJson(Map<String, dynamic> json) => Data237(
  event: Event4.fromJson(json['event'] as Map<String, dynamic>),
  summary: Summary6.fromJson(json['summary'] as Map<String, dynamic>),
  rows: (json['rows'] as List<dynamic>)
      .map((e) => Rows7.fromJson(e as Map<String, dynamic>))
      .toList(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries5.fromJson(e as Map<String, dynamic>))
      .toList(),
  cancellation: json['cancellation'] == null
      ? null
      : Cancellation2.fromJson(json['cancellation'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data237ToJson(Data237 instance) => <String, dynamic>{
  'event': instance.event,
  'summary': instance.summary,
  'rows': instance.rows,
  'entries': instance.entries,
  'cancellation': instance.cancellation,
};
