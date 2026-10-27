// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_bookings_uuid_confirmation_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBookingsUuidConfirmationResponse
_$GetV1PosBookingsUuidConfirmationResponseFromJson(Map<String, dynamic> json) =>
    GetV1PosBookingsUuidConfirmationResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data150.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1PosBookingsUuidConfirmationResponseToJson(
  GetV1PosBookingsUuidConfirmationResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
