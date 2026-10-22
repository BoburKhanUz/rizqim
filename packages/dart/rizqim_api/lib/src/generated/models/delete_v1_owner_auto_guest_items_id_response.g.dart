// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_auto_guest_items_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerAutoGuestItemsIdResponse
_$DeleteV1OwnerAutoGuestItemsIdResponseFromJson(Map<String, dynamic> json) =>
    DeleteV1OwnerAutoGuestItemsIdResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data29.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$DeleteV1OwnerAutoGuestItemsIdResponseToJson(
  DeleteV1OwnerAutoGuestItemsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
