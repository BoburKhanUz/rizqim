// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'user18.dart';
import 'approved_by5.dart';

part 'entries4.g.dart';

@JsonSerializable()
class Entries4 {
  const Entries4({
    required this.uuid,
    required this.type,
    required this.typeLabel,
    required this.amount,
    required this.balanceAfter,
    required this.billUuid,
    required this.tender,
    required this.cardType,
    required this.transactionId,
    required this.fiscalReceiptUuid,
    required this.fiscalStatus,
    required this.branchId,
    required this.user,
    required this.approvedBy,
    required this.note,
    required this.createdAt,
  });

  factory Entries4.fromJson(Map<String, Object?> json) =>
      _$Entries4FromJson(json);

  final String uuid;
  final String type;
  @JsonKey(name: 'type_label')
  final String typeLabel;
  final String amount;
  @JsonKey(name: 'balance_after')
  final String balanceAfter;
  @JsonKey(name: 'bill_uuid')
  final String? billUuid;
  final String? tender;
  @JsonKey(name: 'card_type')
  final String? cardType;
  @JsonKey(name: 'transaction_id')
  final String? transactionId;
  @JsonKey(name: 'fiscal_receipt_uuid')
  final String? fiscalReceiptUuid;
  @JsonKey(name: 'fiscal_status')
  final String? fiscalStatus;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final User18? user;
  @JsonKey(name: 'approved_by')
  final ApprovedBy5? approvedBy;
  final String? note;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Entries4ToJson(this);
}
