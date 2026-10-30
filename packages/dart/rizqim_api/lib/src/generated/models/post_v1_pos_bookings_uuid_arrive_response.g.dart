// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_bookings_uuid_arrive_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBookingsUuidArriveResponse
_$PostV1PosBookingsUuidArriveResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosBookingsUuidArriveResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data191.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosBookingsUuidArriveResponseToJson(
  PostV1PosBookingsUuidArriveResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
