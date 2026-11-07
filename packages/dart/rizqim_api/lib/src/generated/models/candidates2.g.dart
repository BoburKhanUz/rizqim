// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'candidates2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Candidates2 _$Candidates2FromJson(Map<String, dynamic> json) => Candidates2(
  uuid: json['uuid'] as String,
  name: json['name'] as String?,
  platform: json['platform'] as String?,
  app: json['app'] == null ? null : DeviceApp.fromJson(json['app'] as String),
  lastSeenAt: json['last_seen_at'] as String?,
);

Map<String, dynamic> _$Candidates2ToJson(Candidates2 instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'name': instance.name,
      'platform': instance.platform,
      'app': instance.app,
      'last_seen_at': instance.lastSeenAt,
    };
