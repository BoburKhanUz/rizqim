// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_staff_pay_work_sessions_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerStaffPayWorkSessionsRequestBody
_$V1OwnerStaffPayWorkSessionsRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerStaffPayWorkSessionsRequestBody(
      userId: (json['user_id'] as num).toInt(),
      clockInAt: json['clock_in_at'] as String,
      reason: json['reason'] as String,
      branchId: (json['branch_id'] as num?)?.toInt(),
      clockOutAt: json['clock_out_at'] as String?,
    );

Map<String, dynamic> _$V1OwnerStaffPayWorkSessionsRequestBodyToJson(
  V1OwnerStaffPayWorkSessionsRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'user_id': instance.userId,
  'clock_in_at': instance.clockInAt,
  'clock_out_at': instance.clockOutAt,
  'reason': instance.reason,
};
