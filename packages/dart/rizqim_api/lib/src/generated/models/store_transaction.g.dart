// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'store_transaction.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

StoreTransaction _$StoreTransactionFromJson(Map<String, dynamic> json) =>
    StoreTransaction(
      id: (json['id'] as num).toInt(),
      storeId: (json['store_id'] as num).toInt(),
      type: StoreTransactionType.fromJson(json['type'] as String),
      amount: json['amount'] as String,
      balanceBefore: json['balance_before'] as String,
      balanceAfter: json['balance_after'] as String,
      referenceId: json['reference_id'] as String?,
      orderId: (json['order_id'] as num?)?.toInt(),
      paymentId: json['payment_id'] as String?,
      performedBy: (json['performed_by'] as num?)?.toInt(),
      notes: json['notes'] as String?,
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
      updatedAt: json['updated_at'] == null
          ? null
          : DateTime.parse(json['updated_at'] as String),
      subscriptionInvoiceId: (json['subscription_invoice_id'] as num?)?.toInt(),
      payoutRequestId: (json['payout_request_id'] as num?)?.toInt(),
    );

Map<String, dynamic> _$StoreTransactionToJson(StoreTransaction instance) =>
    <String, dynamic>{
      'id': instance.id,
      'store_id': instance.storeId,
      'type': instance.type,
      'amount': instance.amount,
      'balance_before': instance.balanceBefore,
      'balance_after': instance.balanceAfter,
      'reference_id': instance.referenceId,
      'order_id': instance.orderId,
      'payment_id': instance.paymentId,
      'performed_by': instance.performedBy,
      'notes': instance.notes,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'subscription_invoice_id': instance.subscriptionInvoiceId,
      'payout_request_id': instance.payoutRequestId,
    };
