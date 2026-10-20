// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_devices_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1DevicesRequestBody _$V1DevicesRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1DevicesRequestBody(
  type: Type.fromJson(json['type'] as String),
  uuid: json['uuid'] as String?,
  name: json['name'] as String?,
  platform: json['platform'] as String?,
  appVersion: json['app_version'] as String?,
  pushToken: json['push_token'] as String?,
);

Map<String, dynamic> _$V1DevicesRequestBodyToJson(
  V1DevicesRequestBody instance,
) => <String, dynamic>{
  'type': instance.type,
  'uuid': instance.uuid,
  'name': instance.name,
  'platform': instance.platform,
  'app_version': instance.appVersion,
  'push_token': instance.pushToken,
};
