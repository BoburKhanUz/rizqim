// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_owner_auto_guest_items_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1OwnerAutoGuestItemsIdResponse
_$PatchV1OwnerAutoGuestItemsIdResponseFromJson(Map<String, dynamic> json) =>
    PatchV1OwnerAutoGuestItemsIdResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data30.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PatchV1OwnerAutoGuestItemsIdResponseToJson(
  PatchV1OwnerAutoGuestItemsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
