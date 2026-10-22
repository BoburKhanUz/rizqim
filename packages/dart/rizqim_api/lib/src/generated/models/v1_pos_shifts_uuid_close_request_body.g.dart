// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_shifts_uuid_close_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosShiftsUuidCloseRequestBody _$V1PosShiftsUuidCloseRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosShiftsUuidCloseRequestBody(
  countedBreakdown: (json['counted_breakdown'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  force: json['force'] as bool?,
  reason: json['reason'] as String?,
  approvalToken: json['approval_token'] as String?,
);

Map<String, dynamic> _$V1PosShiftsUuidCloseRequestBodyToJson(
  V1PosShiftsUuidCloseRequestBody instance,
) => <String, dynamic>{
  'counted_breakdown': instance.countedBreakdown,
  'force': instance.force,
  'reason': instance.reason,
  'approval_token': instance.approvalToken,
};
