// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_pos_lan_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPosLanRequestBody _$V1OwnerPosLanRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerPosLanRequestBody(
  hubDeviceId: json['hub_device_id'] as String?,
  port: (json['port'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1OwnerPosLanRequestBodyToJson(
  V1OwnerPosLanRequestBody instance,
) => <String, dynamic>{
  'hub_device_id': instance.hubDeviceId,
  'port': instance.port,
};
