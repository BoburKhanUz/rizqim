// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'hub5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Hub5 _$Hub5FromJson(Map<String, dynamic> json) => Hub5(
  device: json['device'] as String?,
  name: json['name'] as String?,
  port: (json['port'] as num).toInt(),
);

Map<String, dynamic> _$Hub5ToJson(Hub5 instance) => <String, dynamic>{
  'device': instance.device,
  'name': instance.name,
  'port': instance.port,
};
