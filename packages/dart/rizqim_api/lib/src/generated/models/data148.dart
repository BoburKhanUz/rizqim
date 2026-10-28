// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account4.dart';
import 'entries3.dart';

part 'data148.g.dart';

@JsonSerializable()
class Data148 {
  const Data148({
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

  factory Data148.fromJson(Map<String, Object?> json) =>
      _$Data148FromJson(json);

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

  Map<String, Object?> toJson() => _$Data148ToJson(this);
}
