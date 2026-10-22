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
    this.reference,
    this.accountUuid,
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
  final String? reference;
  @JsonKey(name: 'account_uuid')
  final String? accountUuid;

  Map<String, Object?> toJson() => _$TendersToJson(this);
}
