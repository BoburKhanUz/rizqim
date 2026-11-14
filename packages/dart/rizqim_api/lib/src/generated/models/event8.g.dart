// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'event8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Event8 _$Event8FromJson(Map<String, dynamic> json) => Event8(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  statusLabel: json['status_label'] as String,
  eventDate: json['event_date'] as String,
  total: json['total'] as String,
  paidTotal: json['paid_total'] as String,
  version: (json['version'] as num).toInt(),
  billed: json['billed'] as bool,
);

Map<String, dynamic> _$Event8ToJson(Event8 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'status': instance.status,
  'status_label': instance.statusLabel,
  'event_date': instance.eventDate,
  'total': instance.total,
  'paid_total': instance.paidTotal,
  'version': instance.version,
  'billed': instance.billed,
};
