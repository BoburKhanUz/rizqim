// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'device.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Device _$DeviceFromJson(Map<String, dynamic> json) => Device(
  uuid: json['uuid'] as String,
  name: json['name'] as String?,
  type: json['type'] as String,
);

Map<String, dynamic> _$DeviceToJson(Device instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'name': instance.name,
  'type': instance.type,
};
