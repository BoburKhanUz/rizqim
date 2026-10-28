// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_items_item_served_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidItemsItemServedResponse
_$PostV1PosOrdersUuidItemsItemServedResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidItemsItemServedResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data163.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidItemsItemServedResponseToJson(
  PostV1PosOrdersUuidItemsItemServedResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
