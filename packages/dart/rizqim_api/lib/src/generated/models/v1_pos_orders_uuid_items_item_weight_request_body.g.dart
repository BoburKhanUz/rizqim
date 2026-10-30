// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_items_item_weight_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidItemsItemWeightRequestBody
_$V1PosOrdersUuidItemsItemWeightRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersUuidItemsItemWeightRequestBody(
  version: (json['version'] as num).toInt(),
  quantity: json['quantity'] as num,
  approvalToken: json['approval_token'] as String?,
);

Map<String, dynamic> _$V1PosOrdersUuidItemsItemWeightRequestBodyToJson(
  V1PosOrdersUuidItemsItemWeightRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'quantity': instance.quantity,
  'approval_token': instance.approvalToken,
};
