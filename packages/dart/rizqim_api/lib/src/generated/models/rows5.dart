// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'paid_by.dart';
import 'fiscal8.dart';

part 'rows5.g.dart';

@JsonSerializable()
class Rows5 {
  const Rows5({
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

  factory Rows5.fromJson(Map<String, Object?> json) => _$Rows5FromJson(json);

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
  final PaidBy? paidBy;
  final Fiscal8 fiscal;
  @JsonKey(name: 'refunded_amount')
  final String refundedAmount;
  @JsonKey(name: 'retained_amount')
  final String retainedAmount;
  @JsonKey(name: 'settle_receipt_uuid')
  final String? settleReceiptUuid;
  final String? note;

  Map<String, Object?> toJson() => _$Rows5ToJson(this);
}
