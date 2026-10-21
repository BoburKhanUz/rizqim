// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_charges_charge_waive_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidChargesChargeWaiveRequestBody
_$V1PosOrdersUuidChargesChargeWaiveRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersUuidChargesChargeWaiveRequestBody(
  version: (json['version'] as num).toInt(),
  reason: json['reason'] as String,
  approvalToken: json['approval_token'] as String?,
);

Map<String, dynamic> _$V1PosOrdersUuidChargesChargeWaiveRequestBodyToJson(
  V1PosOrdersUuidChargesChargeWaiveRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'reason': instance.reason,
  'approval_token': instance.approvalToken,
};
