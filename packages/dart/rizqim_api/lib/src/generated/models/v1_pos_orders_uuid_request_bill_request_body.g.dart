// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_request_bill_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidRequestBillRequestBody
_$V1PosOrdersUuidRequestBillRequestBodyFromJson(Map<String, dynamic> json) =>
    V1PosOrdersUuidRequestBillRequestBody(
      version: (json['version'] as num?)?.toInt(),
      approvalToken: json['approval_token'] as String?,
    );

Map<String, dynamic> _$V1PosOrdersUuidRequestBillRequestBodyToJson(
  V1PosOrdersUuidRequestBillRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'approval_token': instance.approvalToken,
};
