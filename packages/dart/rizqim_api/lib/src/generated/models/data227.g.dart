// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data227.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data227 _$Data227FromJson(Map<String, dynamic> json) => Data227(
  event: Event.fromJson(json['event'] as Map<String, dynamic>),
  summary: Summary5.fromJson(json['summary'] as Map<String, dynamic>),
  rows: (json['rows'] as List<dynamic>)
      .map((e) => Rows5.fromJson(e as Map<String, dynamic>))
      .toList(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries4.fromJson(e as Map<String, dynamic>))
      .toList(),
  cancellation: json['cancellation'] == null
      ? null
      : Cancellation.fromJson(json['cancellation'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data227ToJson(Data227 instance) => <String, dynamic>{
  'event': instance.event,
  'summary': instance.summary,
  'rows': instance.rows,
  'entries': instance.entries,
  'cancellation': instance.cancellation,
};
