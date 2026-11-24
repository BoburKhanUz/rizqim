// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'events4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Events4 _$Events4FromJson(Map<String, dynamic> json) => Events4(
  uuid: json['uuid'] as String,
  title: json['title'] as String?,
  eventType: json['event_type'] as String,
  eventDate: json['event_date'] as String,
  slot: json['slot'] as String,
  status: json['status'] as String,
  plannedGuests: (json['planned_guests'] as num).toInt(),
);

Map<String, dynamic> _$Events4ToJson(Events4 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'title': instance.title,
  'event_type': instance.eventType,
  'event_date': instance.eventDate,
  'slot': instance.slot,
  'status': instance.status,
  'planned_guests': instance.plannedGuests,
};
