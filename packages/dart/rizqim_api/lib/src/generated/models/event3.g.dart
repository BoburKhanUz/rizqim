// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'event3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Event3 _$Event3FromJson(Map<String, dynamic> json) => Event3(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  version: (json['version'] as num).toInt(),
  orderUuid: json['order_uuid'] as String?,
);

Map<String, dynamic> _$Event3ToJson(Event3 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'status': instance.status,
  'version': instance.version,
  'order_uuid': instance.orderUuid,
};
