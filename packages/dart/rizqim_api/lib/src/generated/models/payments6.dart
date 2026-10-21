// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'payments6.g.dart';

@JsonSerializable()
class Payments6 {
  const Payments6({
    required this.uuid,
    required this.tender,
    required this.amount,
    required this.tenderedAmount,
    required this.changeAmount,
    required this.cardType,
    required this.transactionId,
    required this.reference,
    required this.status,
    required this.paidAt,
  });

  factory Payments6.fromJson(Map<String, Object?> json) =>
      _$Payments6FromJson(json);

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
  final String? reference;
  final String status;
  @JsonKey(name: 'paid_at')
  final String? paidAt;

  Map<String, Object?> toJson() => _$Payments6ToJson(this);
}
