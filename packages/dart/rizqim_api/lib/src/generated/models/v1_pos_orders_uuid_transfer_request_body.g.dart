// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_transfer_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidTransferRequestBody _$V1PosOrdersUuidTransferRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersUuidTransferRequestBody(
  version: (json['version'] as num).toInt(),
  waiterId: (json['waiter_id'] as num).toInt(),
  reason: json['reason'] as String?,
  approvalToken: json['approval_token'] as String?,
);

Map<String, dynamic> _$V1PosOrdersUuidTransferRequestBodyToJson(
  V1PosOrdersUuidTransferRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'waiter_id': instance.waiterId,
  'reason': instance.reason,
  'approval_token': instance.approvalToken,
};
