// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'device10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Device10 _$Device10FromJson(Map<String, dynamic> json) => Device10(
  uuid: json['uuid'] as String,
  type: json['type'] as String,
  name: json['name'] as String?,
  isPrintingDevice: json['is_printing_device'] as bool,
);

Map<String, dynamic> _$Device10ToJson(Device10 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'type': instance.type,
  'name': instance.name,
  'is_printing_device': instance.isPrintingDevice,
};
