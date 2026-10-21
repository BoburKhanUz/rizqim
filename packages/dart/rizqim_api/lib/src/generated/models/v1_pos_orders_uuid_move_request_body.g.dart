// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_move_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidMoveRequestBody _$V1PosOrdersUuidMoveRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersUuidMoveRequestBody(
  version: (json['version'] as num).toInt(),
  tableId: (json['table_id'] as num).toInt(),
  approvalToken: json['approval_token'] as String?,
);

Map<String, dynamic> _$V1PosOrdersUuidMoveRequestBodyToJson(
  V1PosOrdersUuidMoveRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'table_id': instance.tableId,
  'approval_token': instance.approvalToken,
};
