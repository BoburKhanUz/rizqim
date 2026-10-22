// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_reject_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidRejectRequestBody _$V1PosOrdersUuidRejectRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersUuidRejectRequestBody(
  reason: json['reason'] as String,
  version: (json['version'] as num?)?.toInt(),
  cancelReason: json['cancel_reason'] == null
      ? null
      : CancelReason.fromJson(json['cancel_reason'] as String?),
  approvalToken: json['approval_token'] as String?,
);

Map<String, dynamic> _$V1PosOrdersUuidRejectRequestBodyToJson(
  V1PosOrdersUuidRejectRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'reason': instance.reason,
  'cancel_reason': instance.cancelReason,
  'approval_token': instance.approvalToken,
};
