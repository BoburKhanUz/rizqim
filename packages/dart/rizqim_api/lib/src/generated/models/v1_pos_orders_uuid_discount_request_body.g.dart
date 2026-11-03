// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_discount_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidDiscountRequestBody _$V1PosOrdersUuidDiscountRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersUuidDiscountRequestBody(
  version: (json['version'] as num).toInt(),
  kind: Kind5.fromJson(json['kind'] as String),
  value: json['value'] as num,
  reason: json['reason'] as String?,
  approvalToken: json['approval_token'] as String?,
);

Map<String, dynamic> _$V1PosOrdersUuidDiscountRequestBodyToJson(
  V1PosOrdersUuidDiscountRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'kind': instance.kind,
  'value': instance.value,
  'reason': instance.reason,
  'approval_token': instance.approvalToken,
};
