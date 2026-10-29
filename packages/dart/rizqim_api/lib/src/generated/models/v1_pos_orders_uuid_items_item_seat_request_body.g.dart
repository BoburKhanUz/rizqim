// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_items_item_seat_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidItemsItemSeatRequestBody
_$V1PosOrdersUuidItemsItemSeatRequestBodyFromJson(Map<String, dynamic> json) =>
    V1PosOrdersUuidItemsItemSeatRequestBody(
      version: (json['version'] as num).toInt(),
      seatNo: (json['seat_no'] as num?)?.toInt(),
      approvalToken: json['approval_token'] as String?,
    );

Map<String, dynamic> _$V1PosOrdersUuidItemsItemSeatRequestBodyToJson(
  V1PosOrdersUuidItemsItemSeatRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'seat_no': instance.seatNo,
  'approval_token': instance.approvalToken,
};
