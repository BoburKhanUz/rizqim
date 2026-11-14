// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'event2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Event2 _$Event2FromJson(Map<String, dynamic> json) => Event2(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  version: (json['version'] as num).toInt(),
  orderUuid: json['order_uuid'] as String?,
  startedAt: json['started_at'] as String?,
);

Map<String, dynamic> _$Event2ToJson(Event2 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'status': instance.status,
  'version': instance.version,
  'order_uuid': instance.orderUuid,
  'started_at': instance.startedAt,
};
