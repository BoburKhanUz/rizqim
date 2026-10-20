// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_devices_device_uuid_staff_session_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1DevicesDeviceUuidStaffSessionRequestBody
_$V1DevicesDeviceUuidStaffSessionRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1DevicesDeviceUuidStaffSessionRequestBody(
  pin: json['pin'] as String,
  userId: (json['user_id'] as num?)?.toInt(),
  staffCode: json['staff_code'] as String?,
);

Map<String, dynamic> _$V1DevicesDeviceUuidStaffSessionRequestBodyToJson(
  V1DevicesDeviceUuidStaffSessionRequestBody instance,
) => <String, dynamic>{
  'user_id': instance.userId,
  'staff_code': instance.staffCode,
  'pin': instance.pin,
};
