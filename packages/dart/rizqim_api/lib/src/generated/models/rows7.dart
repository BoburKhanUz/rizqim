// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'paid_by2.dart';
import 'fiscal10.dart';

part 'rows7.g.dart';

@JsonSerializable()
class Rows7 {
  const Rows7({
    required this.uuid,
    required this.kind,
    required this.kindLabel,
    required this.dueDate,
    required this.amount,
    required this.status,
    required this.statusLabel,
    required this.overdue,
    required this.tender,
    required this.cardType,
    required this.transactionId,
    required this.reference,
    required this.shiftId,
    required this.paidAt,
    required this.paidBy,
    required this.fiscal,
    required this.refundedAmount,
    required this.retainedAmount,
    required this.settleReceiptUuid,
    required this.note,
  });

  factory Rows7.fromJson(Map<String, Object?> json) => _$Rows7FromJson(json);

  final String uuid;
  final String kind;
  @JsonKey(name: 'kind_label')
  final String kindLabel;
  @JsonKey(name: 'due_date')
  final String? dueDate;
  final String amount;
  final String status;
  @JsonKey(name: 'status_label')
  final String statusLabel;
  final bool overdue;
  final String? tender;
  @JsonKey(name: 'card_type')
  final String? cardType;
  @JsonKey(name: 'transaction_id')
  final String? transactionId;
  final String? reference;
  @JsonKey(name: 'shift_id')
  final int? shiftId;
  @JsonKey(name: 'paid_at')
  final String? paidAt;
  @JsonKey(name: 'paid_by')
  final PaidBy2? paidBy;
  final Fiscal10 fiscal;
  @JsonKey(name: 'refunded_amount')
  final String refundedAmount;
  @JsonKey(name: 'retained_amount')
  final String retainedAmount;
  @JsonKey(name: 'settle_receipt_uuid')
  final String? settleReceiptUuid;
  final String? note;

  Map<String, Object?> toJson() => _$Rows7ToJson(this);
}
