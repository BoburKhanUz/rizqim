// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_items_item_weight_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidItemsItemWeightResponse
_$PostV1PosOrdersUuidItemsItemWeightResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidItemsItemWeightResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data239.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidItemsItemWeightResponseToJson(
  PostV1PosOrdersUuidItemsItemWeightResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
