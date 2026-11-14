// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'event8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Event8 _$Event8FromJson(Map<String, dynamic> json) => Event8(
  uuid: json['uuid'] as String,
  title: json['title'] as String?,
  eventType: json['event_type'] as String,
  eventTypeLabel: json['event_type_label'] as String,
  eventDate: json['event_date'] as String,
  slot: json['slot'] as String,
  startsAt: json['starts_at'] as String?,
  endsAt: json['ends_at'] as String?,
  localTime: json['local_time'] as String,
  status: json['status'] as String,
  halls: (json['halls'] as List<dynamic>).map((e) => e as String).toList(),
);

Map<String, dynamic> _$Event8ToJson(Event8 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'title': instance.title,
  'event_type': instance.eventType,
  'event_type_label': instance.eventTypeLabel,
  'event_date': instance.eventDate,
  'slot': instance.slot,
  'starts_at': instance.startsAt,
  'ends_at': instance.endsAt,
  'local_time': instance.localTime,
  'status': instance.status,
  'halls': instance.halls,
};
