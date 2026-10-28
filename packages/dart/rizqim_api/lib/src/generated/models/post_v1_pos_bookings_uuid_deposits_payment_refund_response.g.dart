// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_bookings_uuid_deposits_payment_refund_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBookingsUuidDepositsPaymentRefundResponse
_$PostV1PosBookingsUuidDepositsPaymentRefundResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosBookingsUuidDepositsPaymentRefundResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data133.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosBookingsUuidDepositsPaymentRefundResponseToJson(
  PostV1PosBookingsUuidDepositsPaymentRefundResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
