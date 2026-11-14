// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'event9.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Event9 _$Event9FromJson(Map<String, dynamic> json) => Event9(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  version: (json['version'] as num).toInt(),
  orderUuid: json['order_uuid'] as String?,
);

Map<String, dynamic> _$Event9ToJson(Event9 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'status': instance.status,
  'version': instance.version,
  'order_uuid': instance.orderUuid,
};
