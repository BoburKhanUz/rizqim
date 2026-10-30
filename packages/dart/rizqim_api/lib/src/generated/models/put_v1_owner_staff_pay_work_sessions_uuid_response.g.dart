// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_staff_pay_work_sessions_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerStaffPayWorkSessionsUuidResponse
_$PutV1OwnerStaffPayWorkSessionsUuidResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerStaffPayWorkSessionsUuidResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data174.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerStaffPayWorkSessionsUuidResponseToJson(
  PutV1OwnerStaffPayWorkSessionsUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
