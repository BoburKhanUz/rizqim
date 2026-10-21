// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_send_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidSendRequestBody _$V1PosOrdersUuidSendRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersUuidSendRequestBody(
  version: (json['version'] as num).toInt(),
  itemIds: (json['item_ids'] as List<dynamic>?)
      ?.map((e) => (e as num).toInt())
      .toList(),
);

Map<String, dynamic> _$V1PosOrdersUuidSendRequestBodyToJson(
  V1PosOrdersUuidSendRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'item_ids': instance.itemIds,
};
