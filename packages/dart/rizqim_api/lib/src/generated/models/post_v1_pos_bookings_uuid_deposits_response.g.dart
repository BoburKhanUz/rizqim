// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_bookings_uuid_deposits_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBookingsUuidDepositsResponse
_$PostV1PosBookingsUuidDepositsResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosBookingsUuidDepositsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data180.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosBookingsUuidDepositsResponseToJson(
  PostV1PosBookingsUuidDepositsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
