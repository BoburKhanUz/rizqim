// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'device4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Device4 _$Device4FromJson(Map<String, dynamic> json) => Device4(
  type: json['type'] == null ? null : Type2.fromJson(json['type'] as String),
  uuid: json['uuid'] as String?,
  name: json['name'] as String?,
  platform: json['platform'] as String?,
  appVersion: json['app_version'] as String?,
  pushToken: json['push_token'] as String?,
);

Map<String, dynamic> _$Device4ToJson(Device4 instance) => <String, dynamic>{
  'type': instance.type,
  'uuid': instance.uuid,
  'name': instance.name,
  'platform': instance.platform,
  'app_version': instance.appVersion,
  'push_token': instance.pushToken,
};
