// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_items_item_void_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidItemsItemVoidResponse
_$PostV1PosOrdersUuidItemsItemVoidResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosOrdersUuidItemsItemVoidResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data154.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosOrdersUuidItemsItemVoidResponseToJson(
  PostV1PosOrdersUuidItemsItemVoidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
