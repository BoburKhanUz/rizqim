// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_pos_bookings_uuid_items_item_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1PosBookingsUuidItemsItemResponse
_$PatchV1PosBookingsUuidItemsItemResponseFromJson(Map<String, dynamic> json) =>
    PatchV1PosBookingsUuidItemsItemResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data136.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PatchV1PosBookingsUuidItemsItemResponseToJson(
  PatchV1PosBookingsUuidItemsItemResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
