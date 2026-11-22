// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data238.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data238 _$Data238FromJson(Map<String, dynamic> json) => Data238(
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

Map<String, dynamic> _$Data238ToJson(Data238 instance) => <String, dynamic>{
  'event': instance.event,
  'summary': instance.summary,
  'rows': instance.rows,
  'entries': instance.entries,
  'cancellation': instance.cancellation,
};
