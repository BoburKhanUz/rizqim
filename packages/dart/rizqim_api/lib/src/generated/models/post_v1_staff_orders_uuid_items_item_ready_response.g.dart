// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_staff_orders_uuid_items_item_ready_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1StaffOrdersUuidItemsItemReadyResponse
_$PostV1StaffOrdersUuidItemsItemReadyResponseFromJson(
  Map<String, dynamic> json,
) => PostV1StaffOrdersUuidItemsItemReadyResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data187.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1StaffOrdersUuidItemsItemReadyResponseToJson(
  PostV1StaffOrdersUuidItemsItemReadyResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
