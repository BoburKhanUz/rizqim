// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_pos_tips_uuid_corrections_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPosTipsUuidCorrectionsRequestBody
_$V1OwnerPosTipsUuidCorrectionsRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerPosTipsUuidCorrectionsRequestBody(
      mode: json['mode'] as String,
      reason: json['reason'] as String,
      amount: json['amount'] as String?,
      staffId: (json['staff_id'] as num?)?.toInt(),
    );

Map<String, dynamic> _$V1OwnerPosTipsUuidCorrectionsRequestBodyToJson(
  V1OwnerPosTipsUuidCorrectionsRequestBody instance,
) => <String, dynamic>{
  'amount': instance.amount,
  'mode': instance.mode,
  'staff_id': instance.staffId,
  'reason': instance.reason,
};
