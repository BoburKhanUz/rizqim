// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data240.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data240 _$Data240FromJson(Map<String, dynamic> json) => Data240(
  event: Event6.fromJson(json['event'] as Map<String, dynamic>),
  summary: Summary8.fromJson(json['summary'] as Map<String, dynamic>),
  rows: (json['rows'] as List<dynamic>)
      .map((e) => Rows10.fromJson(e as Map<String, dynamic>))
      .toList(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries7.fromJson(e as Map<String, dynamic>))
      .toList(),
  cancellation: json['cancellation'] == null
      ? null
      : Cancellation4.fromJson(json['cancellation'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data240ToJson(Data240 instance) => <String, dynamic>{
  'event': instance.event,
  'summary': instance.summary,
  'rows': instance.rows,
  'entries': instance.entries,
  'cancellation': instance.cancellation,
};
