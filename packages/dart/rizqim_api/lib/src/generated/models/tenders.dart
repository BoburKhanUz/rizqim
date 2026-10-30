// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tender.dart';

part 'tenders.g.dart';

@JsonSerializable()
class Tenders {
  const Tenders({
    required this.tender,
    required this.amount,
    this.tenderedAmount,
    this.cardType,
    this.transactionId,
    this.cardLast4,
    this.reference,
    this.accountUuid,
    this.tipAmount,
    this.tipFromChange,
  });

  factory Tenders.fromJson(Map<String, Object?> json) =>
      _$TendersFromJson(json);

  final Tender tender;
  final num amount;
  @JsonKey(name: 'tendered_amount')
  final num? tenderedAmount;
  @JsonKey(name: 'card_type')
  final String? cardType;
  @JsonKey(name: 'transaction_id')
  final String? transactionId;
  @JsonKey(name: 'card_last4')
  final String? cardLast4;
  final String? reference;
  @JsonKey(name: 'account_uuid')
  final String? accountUuid;

  /// P6-22: a tip on top of the tender, or the cash change left as one
  @JsonKey(name: 'tip_amount')
  final num? tipAmount;
  @JsonKey(name: 'tip_from_change')
  final bool? tipFromChange;

  Map<String, Object?> toJson() => _$TendersToJson(this);
}
