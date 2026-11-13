// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'hub4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Hub4 _$Hub4FromJson(Map<String, dynamic> json) => Hub4(
  device: json['device'] as String?,
  name: json['name'] as String?,
  port: (json['port'] as num).toInt(),
);

Map<String, dynamic> _$Hub4ToJson(Hub4 instance) => <String, dynamic>{
  'device': instance.device,
  'name': instance.name,
  'port': instance.port,
};
