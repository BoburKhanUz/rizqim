// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'store_transaction_type.dart';

part 'store_transaction.g.dart';

@JsonSerializable()
class StoreTransaction {
  const StoreTransaction({
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
  });

  factory StoreTransaction.fromJson(Map<String, Object?> json) =>
      _$StoreTransactionFromJson(json);

  final int id;
  @JsonKey(name: 'store_id')
  final int storeId;
  final StoreTransactionType type;
  final String amount;
  @JsonKey(name: 'balance_before')
  final String balanceBefore;
  @JsonKey(name: 'balance_after')
  final String balanceAfter;
  @JsonKey(name: 'reference_id')
  final String? referenceId;
  @JsonKey(name: 'order_id')
  final int? orderId;
  @JsonKey(name: 'payment_id')
  final String? paymentId;
  @JsonKey(name: 'performed_by')
  final int? performedBy;
  final String? notes;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;

  Map<String, Object?> toJson() => _$StoreTransactionToJson(this);
}
