// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_table_label_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidTableLabelRequestBody
_$V1PosOrdersUuidTableLabelRequestBodyFromJson(Map<String, dynamic> json) =>
    V1PosOrdersUuidTableLabelRequestBody(
      version: (json['version'] as num).toInt(),
      tableLabel: json['table_label'] as String?,
      approvalToken: json['approval_token'] as String?,
    );

Map<String, dynamic> _$V1PosOrdersUuidTableLabelRequestBodyToJson(
  V1PosOrdersUuidTableLabelRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'table_label': instance.tableLabel,
  'approval_token': instance.approvalToken,
};
