// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_devices_device_uuid_staff_session_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1DevicesDeviceUuidStaffSessionResponse
_$PostV1DevicesDeviceUuidStaffSessionResponseFromJson(
  Map<String, dynamic> json,
) => PostV1DevicesDeviceUuidStaffSessionResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data12.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1DevicesDeviceUuidStaffSessionResponseToJson(
  PostV1DevicesDeviceUuidStaffSessionResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
