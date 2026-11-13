// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'qrs.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Qrs _$QrsFromJson(Map<String, dynamic> json) => Qrs(
  uuid: json['uuid'] as String,
  billUuid: json['bill_uuid'] as String,
  orderUuid: json['order_uuid'] as String?,
  provider: json['provider'] as String,
  providerLabel: json['provider_label'] as String,
  amount: json['amount'] as String,
  status: json['status'] as String,
  payload: json['payload'] as String,
  display: Display2.fromJson(json['display'] as Map<String, dynamic>),
  expiresAt: json['expires_at'] as String,
  held: json['held'] as bool,
  paidAt: json['paid_at'] as String?,
  cancelledAt: json['cancelled_at'] as String?,
  cancelReason: json['cancel_reason'] as String?,
  paymentUuid: json['payment_uuid'] as String?,
  refundedAmount: json['refunded_amount'] as String,
  simulated: json['simulated'] as bool,
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$QrsToJson(Qrs instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'bill_uuid': instance.billUuid,
  'order_uuid': instance.orderUuid,
  'provider': instance.provider,
  'provider_label': instance.providerLabel,
  'amount': instance.amount,
  'status': instance.status,
  'payload': instance.payload,
  'display': instance.display,
  'expires_at': instance.expiresAt,
  'held': instance.held,
  'paid_at': instance.paidAt,
  'cancelled_at': instance.cancelledAt,
  'cancel_reason': instance.cancelReason,
  'payment_uuid': instance.paymentUuid,
  'refunded_amount': instance.refundedAmount,
  'simulated': instance.simulated,
  'created_at': instance.createdAt,
};
