// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_bookings_uuid_cancel_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBookingsUuidCancelResponse
_$PostV1PosBookingsUuidCancelResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosBookingsUuidCancelResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data147.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosBookingsUuidCancelResponseToJson(
  PostV1PosBookingsUuidCancelResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
