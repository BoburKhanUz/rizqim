// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'plan3.g.dart';

@JsonSerializable()
class Plan3 {
  const Plan3({
    required this.code,
    required this.name,
    required this.description,
    required this.summary,
    required this.commissionPercent,
    required this.monthlyBaseFee,
    required this.monthlyFeePerTerminal,
    required this.includedTerminals,
    required this.trialDays,
  });

  factory Plan3.fromJson(Map<String, Object?> json) => _$Plan3FromJson(json);

  final String code;
  final String name;
  final String? description;
  final String summary;
  @JsonKey(name: 'commission_percent')
  final num? commissionPercent;
  @JsonKey(name: 'monthly_base_fee')
  final num monthlyBaseFee;
  @JsonKey(name: 'monthly_fee_per_terminal')
  final num monthlyFeePerTerminal;
  @JsonKey(name: 'included_terminals')
  final int includedTerminals;
  @JsonKey(name: 'trial_days')
  final int trialDays;

  Map<String, Object?> toJson() => _$Plan3ToJson(this);
}
