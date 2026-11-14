// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'user21.dart';
import 'fiscal19.dart';

part 'entry2.g.dart';

@JsonSerializable()
class Entry2 {
  const Entry2({
    required this.uuid,
    required this.type,
    required this.typeLabel,
    required this.paymentUuid,
    required this.amount,
    required this.tender,
    required this.cardType,
    required this.reference,
    required this.businessDate,
    required this.shiftId,
    required this.user,
    required this.approvedBy,
    required this.reason,
    required this.transferPending,
    required this.fiscal,
    required this.originalReceiptUuid,
    required this.createdAt,
  });

  factory Entry2.fromJson(Map<String, Object?> json) => _$Entry2FromJson(json);

  final String uuid;
  final String type;
  @JsonKey(name: 'type_label')
  final String typeLabel;
  @JsonKey(name: 'payment_uuid')
  final String? paymentUuid;
  final String amount;
  final String tender;
  @JsonKey(name: 'card_type')
  final String? cardType;
  final String? reference;
  @JsonKey(name: 'business_date')
  final String? businessDate;
  @JsonKey(name: 'shift_id')
  final int? shiftId;
  final User21? user;
  @JsonKey(name: 'approved_by')
  final int? approvedBy;
  final String? reason;
  @JsonKey(name: 'transfer_pending')
  final bool transferPending;
  final Fiscal19 fiscal;
  @JsonKey(name: 'original_receipt_uuid')
  final String? originalReceiptUuid;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Entry2ToJson(this);
}
