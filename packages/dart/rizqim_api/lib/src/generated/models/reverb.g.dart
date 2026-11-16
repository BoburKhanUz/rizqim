// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'reverb.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Reverb _$ReverbFromJson(Map<String, dynamic> json) => Reverb(
  key: json['key'] as String,
  host: json['host'] as String?,
  port: (json['port'] as num?)?.toInt(),
  scheme: json['scheme'] as String?,
);

Map<String, dynamic> _$ReverbToJson(Reverb instance) => <String, dynamic>{
  'key': instance.key,
  'host': instance.host,
  'port': instance.port,
  'scheme': instance.scheme,
};
