// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account4.dart';
import 'entries3.dart';

part 'data147.g.dart';

@JsonSerializable()
class Data147 {
  const Data147({
    required this.account,
    required this.from,
    required this.to,
    required this.openingBalance,
    required this.closingBalance,
    required this.chargesTotal,
    required this.repaymentsTotal,
    required this.adjustmentsTotal,
    required this.entries,
  });

  factory Data147.fromJson(Map<String, Object?> json) =>
      _$Data147FromJson(json);

  final Account4 account;
  final String from;
  final String to;
  @JsonKey(name: 'opening_balance')
  final String openingBalance;
  @JsonKey(name: 'closing_balance')
  final String closingBalance;
  @JsonKey(name: 'charges_total')
  final String chargesTotal;
  @JsonKey(name: 'repayments_total')
  final String repaymentsTotal;
  @JsonKey(name: 'adjustments_total')
  final String adjustmentsTotal;
  final List<Entries3> entries;

  Map<String, Object?> toJson() => _$Data147ToJson(this);
}
