// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_bookings_uuid_confirmation_send_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBookingsUuidConfirmationSendResponse
_$PostV1PosBookingsUuidConfirmationSendResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosBookingsUuidConfirmationSendResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data179.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosBookingsUuidConfirmationSendResponseToJson(
  PostV1PosBookingsUuidConfirmationSendResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
