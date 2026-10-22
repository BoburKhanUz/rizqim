// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_items_item_void_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidItemsItemVoidRequestBody
_$V1PosOrdersUuidItemsItemVoidRequestBodyFromJson(Map<String, dynamic> json) =>
    V1PosOrdersUuidItemsItemVoidRequestBody(
      version: (json['version'] as num).toInt(),
      reason: json['reason'] as String?,
      approvalToken: json['approval_token'] as String?,
      restock: json['restock'] as bool?,
    );

Map<String, dynamic> _$V1PosOrdersUuidItemsItemVoidRequestBodyToJson(
  V1PosOrdersUuidItemsItemVoidRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'reason': instance.reason,
  'approval_token': instance.approvalToken,
  'restock': instance.restock,
};
