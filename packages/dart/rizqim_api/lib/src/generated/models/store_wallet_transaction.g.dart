// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'store_wallet_transaction.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

StoreWalletTransaction _$StoreWalletTransactionFromJson(
  Map<String, dynamic> json,
) => StoreWalletTransaction(
  id: (json['id'] as num).toInt(),
  storeId: (json['store_id'] as num).toInt(),
  type: json['type'] as String,
  amount: json['amount'] as num,
  balanceBefore: json['balance_before'] as num,
  balanceAfter: json['balance_after'] as num,
  referenceId: json['reference_id'] as String?,
  orderId: (json['order_id'] as num?)?.toInt(),
  paymentId: json['payment_id'] as String?,
  performedBy: json['performed_by'],
  notes: json['notes'] as String?,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  order: json['order'] == null
      ? null
      : Order.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$StoreWalletTransactionToJson(
  StoreWalletTransaction instance,
) => <String, dynamic>{
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
  'store': instance.store,
  'order': instance.order,
};
