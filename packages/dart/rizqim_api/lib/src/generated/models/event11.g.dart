// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'event11.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Event11 _$Event11FromJson(Map<String, dynamic> json) => Event11(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  version: (json['version'] as num).toInt(),
  orderUuid: json['order_uuid'] as String?,
);

Map<String, dynamic> _$Event11ToJson(Event11 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'status': instance.status,
  'version': instance.version,
  'order_uuid': instance.orderUuid,
};
