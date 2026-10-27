// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'device2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Device2 _$Device2FromJson(Map<String, dynamic> json) => Device2(
  type: Type.fromJson(json['type'] as String),
  uuid: json['uuid'] as String?,
  name: json['name'] as String?,
  platform: json['platform'] as String?,
  appVersion: json['app_version'] as String?,
  pushToken: json['push_token'] as String?,
);

Map<String, dynamic> _$Device2ToJson(Device2 instance) => <String, dynamic>{
  'type': instance.type,
  'uuid': instance.uuid,
  'name': instance.name,
  'platform': instance.platform,
  'app_version': instance.appVersion,
  'push_token': instance.pushToken,
};
