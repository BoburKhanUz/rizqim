// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data235.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data235 _$Data235FromJson(Map<String, dynamic> json) => Data235(
  event: Event5.fromJson(json['event'] as Map<String, dynamic>),
  summary: Summary7.fromJson(json['summary'] as Map<String, dynamic>),
  rows: (json['rows'] as List<dynamic>)
      .map((e) => Rows8.fromJson(e as Map<String, dynamic>))
      .toList(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries6.fromJson(e as Map<String, dynamic>))
      .toList(),
  cancellation: json['cancellation'] == null
      ? null
      : Cancellation3.fromJson(json['cancellation'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data235ToJson(Data235 instance) => <String, dynamic>{
  'event': instance.event,
  'summary': instance.summary,
  'rows': instance.rows,
  'entries': instance.entries,
  'cancellation': instance.cancellation,
};
