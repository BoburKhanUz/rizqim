// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data234.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data234 _$Data234FromJson(Map<String, dynamic> json) => Data234(
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

Map<String, dynamic> _$Data234ToJson(Data234 instance) => <String, dynamic>{
  'event': instance.event,
  'summary': instance.summary,
  'rows': instance.rows,
  'entries': instance.entries,
  'cancellation': instance.cancellation,
};
