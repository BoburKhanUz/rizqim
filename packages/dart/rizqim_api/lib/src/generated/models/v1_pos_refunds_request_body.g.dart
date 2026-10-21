// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_refunds_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosRefundsRequestBody _$V1PosRefundsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosRefundsRequestBody(
  paymentUuid: json['payment_uuid'] as String,
  amount: json['amount'] as num,
  reason: json['reason'] as String,
  approvalToken: json['approval_token'] as String?,
  shiftUuid: json['shift_uuid'] as String?,
);

Map<String, dynamic> _$V1PosRefundsRequestBodyToJson(
  V1PosRefundsRequestBody instance,
) => <String, dynamic>{
  'payment_uuid': instance.paymentUuid,
  'amount': instance.amount,
  'reason': instance.reason,
  'approval_token': instance.approvalToken,
  'shift_uuid': instance.shiftUuid,
};
