// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'device8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Device8 _$Device8FromJson(Map<String, dynamic> json) => Device8(
  type: json['type'] == null ? null : Type2.fromJson(json['type'] as String),
  uuid: json['uuid'] as String?,
  name: json['name'] as String?,
  platform: json['platform'] as String?,
  appVersion: json['app_version'] as String?,
  pushToken: json['push_token'] as String?,
);

Map<String, dynamic> _$Device8ToJson(Device8 instance) => <String, dynamic>{
  'type': instance.type,
  'uuid': instance.uuid,
  'name': instance.name,
  'platform': instance.platform,
  'app_version': instance.appVersion,
  'push_token': instance.pushToken,
};
