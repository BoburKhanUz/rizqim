// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_tips_uuid_corrections_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosTipsUuidCorrectionsRequestBody
_$V1PosTipsUuidCorrectionsRequestBodyFromJson(Map<String, dynamic> json) =>
    V1PosTipsUuidCorrectionsRequestBody(
      mode: json['mode'] as String,
      reason: json['reason'] as String,
      amount: json['amount'] as String?,
      staffId: (json['staff_id'] as num?)?.toInt(),
      approvalToken: json['approval_token'] as String?,
    );

Map<String, dynamic> _$V1PosTipsUuidCorrectionsRequestBodyToJson(
  V1PosTipsUuidCorrectionsRequestBody instance,
) => <String, dynamic>{
  'amount': instance.amount,
  'mode': instance.mode,
  'staff_id': instance.staffId,
  'reason': instance.reason,
  'approval_token': instance.approvalToken,
};
