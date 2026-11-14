// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'event10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Event10 _$Event10FromJson(Map<String, dynamic> json) => Event10(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  version: (json['version'] as num).toInt(),
  orderUuid: json['order_uuid'] as String?,
);

Map<String, dynamic> _$Event10ToJson(Event10 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'status': instance.status,
  'version': instance.version,
  'order_uuid': instance.orderUuid,
};
