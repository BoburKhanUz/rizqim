// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_bookings_uuid_items_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBookingsUuidItemsResponse _$PostV1PosBookingsUuidItemsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosBookingsUuidItemsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data134.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosBookingsUuidItemsResponseToJson(
  PostV1PosBookingsUuidItemsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
