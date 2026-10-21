// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_auto_guest_items_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerAutoGuestItemsResponse _$PostV1OwnerAutoGuestItemsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerAutoGuestItemsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data28.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerAutoGuestItemsResponseToJson(
  PostV1OwnerAutoGuestItemsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
