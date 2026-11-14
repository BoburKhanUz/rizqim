// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'event6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Event6 _$Event6FromJson(Map<String, dynamic> json) => Event6(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  statusLabel: json['status_label'] as String,
  eventDate: json['event_date'] as String,
  total: json['total'] as String,
  paidTotal: json['paid_total'] as String,
  version: (json['version'] as num).toInt(),
);

Map<String, dynamic> _$Event6ToJson(Event6 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'status': instance.status,
  'status_label': instance.statusLabel,
  'event_date': instance.eventDate,
  'total': instance.total,
  'paid_total': instance.paidTotal,
  'version': instance.version,
};
