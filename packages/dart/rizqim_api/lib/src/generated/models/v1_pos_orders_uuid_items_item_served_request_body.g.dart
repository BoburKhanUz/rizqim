// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_items_item_served_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidItemsItemServedRequestBody
_$V1PosOrdersUuidItemsItemServedRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersUuidItemsItemServedRequestBody(
  version: (json['version'] as num).toInt(),
  approvalToken: json['approval_token'] as String?,
);

Map<String, dynamic> _$V1PosOrdersUuidItemsItemServedRequestBodyToJson(
  V1PosOrdersUuidItemsItemServedRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'approval_token': instance.approvalToken,
};
