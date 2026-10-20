// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'device5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Device5 _$Device5FromJson(Map<String, dynamic> json) => Device5(
  type: json['type'] == null ? null : Type.fromJson(json['type'] as String),
  uuid: json['uuid'] as String?,
  name: json['name'] as String?,
  platform: json['platform'] as String?,
  appVersion: json['app_version'] as String?,
  pushToken: json['push_token'] as String?,
);

Map<String, dynamic> _$Device5ToJson(Device5 instance) => <String, dynamic>{
  'type': instance.type,
  'uuid': instance.uuid,
  'name': instance.name,
  'platform': instance.platform,
  'app_version': instance.appVersion,
  'push_token': instance.pushToken,
};
