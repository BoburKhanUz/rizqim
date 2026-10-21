// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_owner_devices_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1OwnerDevicesUuidResponse _$PatchV1OwnerDevicesUuidResponseFromJson(
  Map<String, dynamic> json,
) => PatchV1OwnerDevicesUuidResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data32.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PatchV1OwnerDevicesUuidResponseToJson(
  PatchV1OwnerDevicesUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
