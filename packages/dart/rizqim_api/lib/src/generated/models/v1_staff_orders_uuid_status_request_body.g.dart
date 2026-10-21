// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_staff_orders_uuid_status_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1StaffOrdersUuidStatusRequestBody _$V1StaffOrdersUuidStatusRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1StaffOrdersUuidStatusRequestBody(
  status: Status4.fromJson(json['status'] as String),
  reason: json['reason'] == null
      ? null
      : Reason2.fromJson(json['reason'] as String?),
  note: json['note'] as String?,
);

Map<String, dynamic> _$V1StaffOrdersUuidStatusRequestBodyToJson(
  V1StaffOrdersUuidStatusRequestBody instance,
) => <String, dynamic>{
  'status': instance.status,
  'reason': instance.reason,
  'note': instance.note,
};
