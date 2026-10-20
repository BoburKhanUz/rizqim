// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'payment.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Payment _$PaymentFromJson(Map<String, dynamic> json) => Payment(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  orderId: (json['order_id'] as num?)?.toInt(),
  provider: json['provider'] as String,
  providerTransactionId: json['provider_transaction_id'] as String?,
  amount: json['amount'] as String,
  status: PaymentStatus.fromJson(json['status'] as String),
  payload: json['payload'] as Map<String, dynamic>?,
  paidAt: json['paid_at'] == null
      ? null
      : DateTime.parse(json['paid_at'] as String),
  cancelledAt: json['cancelled_at'] == null
      ? null
      : DateTime.parse(json['cancelled_at'] as String),
  expiredAt: json['expired_at'] == null
      ? null
      : DateTime.parse(json['expired_at'] as String),
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  order: json['order'] == null
      ? null
      : Order.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PaymentToJson(Payment instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'order_id': instance.orderId,
  'provider': instance.provider,
  'provider_transaction_id': instance.providerTransactionId,
  'amount': instance.amount,
  'status': instance.status,
  'payload': instance.payload,
  'paid_at': instance.paidAt?.toIso8601String(),
  'cancelled_at': instance.cancelledAt?.toIso8601String(),
  'expired_at': instance.expiredAt?.toIso8601String(),
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
  'order': instance.order,
};
