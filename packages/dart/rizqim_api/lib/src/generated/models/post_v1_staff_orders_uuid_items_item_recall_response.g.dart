// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_staff_orders_uuid_items_item_recall_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1StaffOrdersUuidItemsItemRecallResponse
_$PostV1StaffOrdersUuidItemsItemRecallResponseFromJson(
  Map<String, dynamic> json,
) => PostV1StaffOrdersUuidItemsItemRecallResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data208.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1StaffOrdersUuidItemsItemRecallResponseToJson(
  PostV1StaffOrdersUuidItemsItemRecallResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
