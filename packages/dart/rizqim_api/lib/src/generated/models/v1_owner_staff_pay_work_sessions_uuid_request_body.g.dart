// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_staff_pay_work_sessions_uuid_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerStaffPayWorkSessionsUuidRequestBody
_$V1OwnerStaffPayWorkSessionsUuidRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerStaffPayWorkSessionsUuidRequestBody(
  clockInAt: json['clock_in_at'] as String,
  reason: json['reason'] as String,
  clockOutAt: json['clock_out_at'] as String?,
);

Map<String, dynamic> _$V1OwnerStaffPayWorkSessionsUuidRequestBodyToJson(
  V1OwnerStaffPayWorkSessionsUuidRequestBody instance,
) => <String, dynamic>{
  'clock_in_at': instance.clockInAt,
  'clock_out_at': instance.clockOutAt,
  'reason': instance.reason,
};
