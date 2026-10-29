// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_pos_bookings_uuid_items_item_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1PosBookingsUuidItemsItemResponse
_$DeleteV1PosBookingsUuidItemsItemResponseFromJson(Map<String, dynamic> json) =>
    DeleteV1PosBookingsUuidItemsItemResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data155.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$DeleteV1PosBookingsUuidItemsItemResponseToJson(
  DeleteV1PosBookingsUuidItemsItemResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
