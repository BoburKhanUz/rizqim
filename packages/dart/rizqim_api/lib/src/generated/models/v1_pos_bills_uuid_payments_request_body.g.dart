// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_bills_uuid_payments_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBillsUuidPaymentsRequestBody _$V1PosBillsUuidPaymentsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBillsUuidPaymentsRequestBody(
  tenders: (json['tenders'] as List<dynamic>)
      .map((e) => Tenders.fromJson(e as Map<String, dynamic>))
      .toList(),
  tipStaffId: (json['tip_staff_id'] as num?)?.toInt(),
  tipApprovalToken: json['tip_approval_token'] as String?,
  approvalToken: json['approval_token'] as String?,
  shiftUuid: json['shift_uuid'] as String?,
  version: (json['version'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1PosBillsUuidPaymentsRequestBodyToJson(
  V1PosBillsUuidPaymentsRequestBody instance,
) => <String, dynamic>{
  'tenders': instance.tenders,
  'tip_staff_id': instance.tipStaffId,
  'tip_approval_token': instance.tipApprovalToken,
  'approval_token': instance.approvalToken,
  'shift_uuid': instance.shiftUuid,
  'version': instance.version,
};
