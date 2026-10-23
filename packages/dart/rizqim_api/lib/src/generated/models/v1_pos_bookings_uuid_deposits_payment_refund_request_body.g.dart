// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_bookings_uuid_deposits_payment_refund_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBookingsUuidDepositsPaymentRefundRequestBody
_$V1PosBookingsUuidDepositsPaymentRefundRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBookingsUuidDepositsPaymentRefundRequestBody(
  reason: json['reason'] as String,
  approvalToken: json['approval_token'] as String?,
);

Map<String, dynamic> _$V1PosBookingsUuidDepositsPaymentRefundRequestBodyToJson(
  V1PosBookingsUuidDepositsPaymentRefundRequestBody instance,
) => <String, dynamic>{
  'reason': instance.reason,
  'approval_token': instance.approvalToken,
};
