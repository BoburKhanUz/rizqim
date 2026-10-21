// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_cancel_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidCancelRequestBody _$V1PosOrdersUuidCancelRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersUuidCancelRequestBody(
  version: (json['version'] as num).toInt(),
  reason: json['reason'] as String,
  approvalToken: json['approval_token'] as String?,
);

Map<String, dynamic> _$V1PosOrdersUuidCancelRequestBodyToJson(
  V1PosOrdersUuidCancelRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'reason': instance.reason,
  'approval_token': instance.approvalToken,
};
