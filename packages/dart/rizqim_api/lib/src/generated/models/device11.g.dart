// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'device11.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Device11 _$Device11FromJson(Map<String, dynamic> json) => Device11(
  uuid: json['uuid'] as String,
  type: json['type'] as String,
  name: json['name'] as String?,
  isPrintingDevice: json['is_printing_device'] as bool,
);

Map<String, dynamic> _$Device11ToJson(Device11 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'type': instance.type,
  'name': instance.name,
  'is_printing_device': instance.isPrintingDevice,
};
