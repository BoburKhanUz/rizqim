// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'payments29.g.dart';

@JsonSerializable()
class Payments29 {
  const Payments29({
    required this.uuid,
    required this.tender,
    required this.amount,
    required this.tenderedAmount,
    required this.changeAmount,
    required this.cardType,
    required this.transactionId,
    required this.cardLast4,
    required this.reference,
    required this.status,
    required this.paidAt,
    required this.tipAmount,
  });

  factory Payments29.fromJson(Map<String, Object?> json) =>
      _$Payments29FromJson(json);

  final String uuid;
  final String tender;
  final String amount;
  @JsonKey(name: 'tendered_amount')
  final String? tenderedAmount;
  @JsonKey(name: 'change_amount')
  final String? changeAmount;
  @JsonKey(name: 'card_type')
  final String? cardType;
  @JsonKey(name: 'transaction_id')
  final String? transactionId;
  @JsonKey(name: 'card_last4')
  final String? cardLast4;
  final String? reference;
  final String status;
  @JsonKey(name: 'paid_at')
  final String? paidAt;
  @JsonKey(name: 'tip_amount')
  final String tipAmount;

  Map<String, Object?> toJson() => _$Payments29ToJson(this);
}
