// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_devices_pair_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1DevicesPairRequestBody _$V1DevicesPairRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1DevicesPairRequestBody(
  code: json['code'] as String,
  name: json['name'] as String?,
  platform: json['platform'] as String?,
  appVersion: json['app_version'] as String?,
);

Map<String, dynamic> _$V1DevicesPairRequestBodyToJson(
  V1DevicesPairRequestBody instance,
) => <String, dynamic>{
  'code': instance.code,
  'name': instance.name,
  'platform': instance.platform,
  'app_version': instance.appVersion,
};
