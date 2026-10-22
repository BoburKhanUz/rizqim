// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'payments15.g.dart';

@JsonSerializable()
class Payments15 {
  const Payments15({
    required this.uuid,
    required this.amount,
    required this.tender,
    required this.cardType,
    required this.transactionId,
    required this.status,
    required this.statusLabel,
    required this.remaining,
    required this.appliedAmount,
    required this.refundedAmount,
    required this.retainedAmount,
    required this.fiscalReceiptUuid,
    required this.fiscalStatus,
    required this.settleReason,
    required this.settleReceiptUuid,
    required this.settleFiscalStatus,
    required this.approvedBy,
    required this.userId,
    required this.createdAt,
    required this.settledAt,
  });

  factory Payments15.fromJson(Map<String, Object?> json) =>
      _$Payments15FromJson(json);

  final String uuid;
  final String amount;
  final String tender;
  @JsonKey(name: 'card_type')
  final String? cardType;
  @JsonKey(name: 'transaction_id')
  final String? transactionId;
  final String status;
  @JsonKey(name: 'status_label')
  final String statusLabel;
  final String remaining;
  @JsonKey(name: 'applied_amount')
  final String appliedAmount;
  @JsonKey(name: 'refunded_amount')
  final String refundedAmount;
  @JsonKey(name: 'retained_amount')
  final String retainedAmount;
  @JsonKey(name: 'fiscal_receipt_uuid')
  final String? fiscalReceiptUuid;
  @JsonKey(name: 'fiscal_status')
  final String? fiscalStatus;
  @JsonKey(name: 'settle_reason')
  final String? settleReason;
  @JsonKey(name: 'settle_receipt_uuid')
  final String? settleReceiptUuid;
  @JsonKey(name: 'settle_fiscal_status')
  final String? settleFiscalStatus;
  @JsonKey(name: 'approved_by')
  final int? approvedBy;
  @JsonKey(name: 'user_id')
  final int? userId;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'settled_at')
  final String? settledAt;

  Map<String, Object?> toJson() => _$Payments15ToJson(this);
}
