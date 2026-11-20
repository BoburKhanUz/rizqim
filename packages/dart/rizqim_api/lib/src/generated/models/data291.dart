// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account6.dart';
import 'entries11.dart';

part 'data291.g.dart';

@JsonSerializable()
class Data291 {
  const Data291({
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

  factory Data291.fromJson(Map<String, Object?> json) =>
      _$Data291FromJson(json);

  final Account6 account;
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
  final List<Entries11> entries;

  Map<String, Object?> toJson() => _$Data291ToJson(this);
}
