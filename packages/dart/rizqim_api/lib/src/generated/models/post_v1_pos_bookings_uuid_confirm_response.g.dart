// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_bookings_uuid_confirm_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBookingsUuidConfirmResponse
_$PostV1PosBookingsUuidConfirmResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosBookingsUuidConfirmResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data129.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosBookingsUuidConfirmResponseToJson(
  PostV1PosBookingsUuidConfirmResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
