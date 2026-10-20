// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order.dart';
import 'store.dart';

part 'store_wallet_transaction.g.dart';

@JsonSerializable()
class StoreWalletTransaction {
  const StoreWalletTransaction({
    required this.id,
    required this.storeId,
    required this.type,
    required this.amount,
    required this.balanceBefore,
    required this.balanceAfter,
    required this.referenceId,
    required this.orderId,
    required this.paymentId,
    required this.performedBy,
    required this.notes,
    required this.createdAt,
    required this.updatedAt,
    this.store,
    this.order,
  });

  factory StoreWalletTransaction.fromJson(Map<String, Object?> json) =>
      _$StoreWalletTransactionFromJson(json);

  final int id;
  @JsonKey(name: 'store_id')
  final int storeId;
  final String type;
  final num amount;
  @JsonKey(name: 'balance_before')
  final num balanceBefore;
  @JsonKey(name: 'balance_after')
  final num balanceAfter;
  @JsonKey(name: 'reference_id')
  final String? referenceId;
  @JsonKey(name: 'order_id')
  final int? orderId;
  @JsonKey(name: 'payment_id')
  final String? paymentId;
  @JsonKey(name: 'performed_by')
  final dynamic performedBy;
  final String? notes;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Store? store;
  final Order? order;

  Map<String, Object?> toJson() => _$StoreWalletTransactionToJson(this);
}
