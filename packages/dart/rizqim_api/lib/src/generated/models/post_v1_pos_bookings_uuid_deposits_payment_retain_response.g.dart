// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_bookings_uuid_deposits_payment_retain_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBookingsUuidDepositsPaymentRetainResponse
_$PostV1PosBookingsUuidDepositsPaymentRetainResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosBookingsUuidDepositsPaymentRetainResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data153.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosBookingsUuidDepositsPaymentRetainResponseToJson(
  PostV1PosBookingsUuidDepositsPaymentRetainResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
