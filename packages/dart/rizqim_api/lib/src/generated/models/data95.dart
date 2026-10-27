// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'plan.dart';
import 'lines4.dart';

part 'data95.g.dart';

@JsonSerializable()
class Data95 {
  const Data95({
    required this.uuid,
    required this.number,
    required this.period,
    required this.periodStart,
    required this.periodEnd,
    required this.plan,
    required this.terminalsCount,
    required this.includedTerminals,
    required this.baseFee,
    required this.feePerTerminal,
    required this.lines,
    required this.amount,
    required this.status,
    required this.dueDate,
    required this.issuedAt,
    required this.paidAt,
    required this.voidedAt,
  });

  factory Data95.fromJson(Map<String, Object?> json) => _$Data95FromJson(json);

  final String uuid;
  final String number;
  final String period;
  @JsonKey(name: 'period_start')
  final String periodStart;
  @JsonKey(name: 'period_end')
  final String periodEnd;
  final Plan? plan;
  @JsonKey(name: 'terminals_count')
  final int terminalsCount;
  @JsonKey(name: 'included_terminals')
  final int includedTerminals;
  @JsonKey(name: 'base_fee')
  final num baseFee;
  @JsonKey(name: 'fee_per_terminal')
  final num feePerTerminal;
  final List<Lines4> lines;
  final num amount;
  final String status;
  @JsonKey(name: 'due_date')
  final String dueDate;
  @JsonKey(name: 'issued_at')
  final String issuedAt;
  @JsonKey(name: 'paid_at')
  final String? paidAt;
  @JsonKey(name: 'voided_at')
  final String? voidedAt;

  Map<String, Object?> toJson() => _$Data95ToJson(this);
}
