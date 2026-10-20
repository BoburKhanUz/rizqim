// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_auth_refresh_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1AuthRefreshRequestBody _$V1AuthRefreshRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1AuthRefreshRequestBody(
  refreshToken: json['refresh_token'] as String,
  deviceUuid: json['device_uuid'] as String,
);

Map<String, dynamic> _$V1AuthRefreshRequestBodyToJson(
  V1AuthRefreshRequestBody instance,
) => <String, dynamic>{
  'refresh_token': instance.refreshToken,
  'device_uuid': instance.deviceUuid,
};
