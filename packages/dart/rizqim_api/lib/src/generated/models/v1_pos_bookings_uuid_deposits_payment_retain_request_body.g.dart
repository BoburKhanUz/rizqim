// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_bookings_uuid_deposits_payment_retain_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBookingsUuidDepositsPaymentRetainRequestBody
_$V1PosBookingsUuidDepositsPaymentRetainRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBookingsUuidDepositsPaymentRetainRequestBody(
  reason: json['reason'] as String,
  approvalToken: json['approval_token'] as String?,
);

Map<String, dynamic> _$V1PosBookingsUuidDepositsPaymentRetainRequestBodyToJson(
  V1PosBookingsUuidDepositsPaymentRetainRequestBody instance,
) => <String, dynamic>{
  'reason': instance.reason,
  'approval_token': instance.approvalToken,
};
