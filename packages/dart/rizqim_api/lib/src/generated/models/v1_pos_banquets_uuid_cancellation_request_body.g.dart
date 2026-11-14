// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_banquets_uuid_cancellation_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBanquetsUuidCancellationRequestBody
_$V1PosBanquetsUuidCancellationRequestBodyFromJson(Map<String, dynamic> json) =>
    V1PosBanquetsUuidCancellationRequestBody(
      reason: json['reason'] as String,
      retained: json['retained'] as num?,
      overrideReason: json['override_reason'] as String?,
      approvalToken: json['approval_token'] as String?,
    );

Map<String, dynamic> _$V1PosBanquetsUuidCancellationRequestBodyToJson(
  V1PosBanquetsUuidCancellationRequestBody instance,
) => <String, dynamic>{
  'reason': instance.reason,
  'retained': instance.retained,
  'override_reason': instance.overrideReason,
  'approval_token': instance.approvalToken,
};
