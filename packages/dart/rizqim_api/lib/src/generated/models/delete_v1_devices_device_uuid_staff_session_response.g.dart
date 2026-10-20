// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_devices_device_uuid_staff_session_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1DevicesDeviceUuidStaffSessionResponse
_$DeleteV1DevicesDeviceUuidStaffSessionResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1DevicesDeviceUuidStaffSessionResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$DeleteV1DevicesDeviceUuidStaffSessionResponseToJson(
  DeleteV1DevicesDeviceUuidStaffSessionResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
