// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_items_item_seat_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidItemsItemSeatResponse
_$PostV1PosOrdersUuidItemsItemSeatResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosOrdersUuidItemsItemSeatResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data236.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosOrdersUuidItemsItemSeatResponseToJson(
  PostV1PosOrdersUuidItemsItemSeatResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
