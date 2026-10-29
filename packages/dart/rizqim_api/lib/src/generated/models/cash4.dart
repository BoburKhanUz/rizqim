// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'cash4.g.dart';

@JsonSerializable()
class Cash4 {
  const Cash4({
    required this.opening,
    required this.sales,
    required this.refunds,
    required this.cashIn,
    required this.cashOut,
    required this.wages,
    required this.purchases,
    required this.deposits,
    required this.depositRefunds,
    required this.repayments,
    required this.tips,
    required this.tipPayouts,
    required this.expected,
  });

  factory Cash4.fromJson(Map<String, Object?> json) => _$Cash4FromJson(json);

  final String opening;
  final String sales;
  final String refunds;
  @JsonKey(name: 'cash_in')
  final String cashIn;
  @JsonKey(name: 'cash_out')
  final String cashOut;
  final String wages;
  final String purchases;
  final String deposits;
  @JsonKey(name: 'deposit_refunds')
  final String depositRefunds;
  final String repayments;
  final String tips;
  @JsonKey(name: 'tip_payouts')
  final String tipPayouts;
  final String expected;

  Map<String, Object?> toJson() => _$Cash4ToJson(this);
}
