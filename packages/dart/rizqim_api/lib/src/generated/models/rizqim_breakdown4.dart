// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rizqim_breakdown4.g.dart';

@JsonSerializable()
class RizqimBreakdown4 {
  const RizqimBreakdown4({
    required this.payments,
    required this.deposits,
    required this.repayments,
    required this.refunds,
    required this.depositRefunds,
    required this.tips,
  });

  factory RizqimBreakdown4.fromJson(Map<String, Object?> json) =>
      _$RizqimBreakdown4FromJson(json);

  final String payments;
  final String deposits;
  final String repayments;
  final String refunds;
  @JsonKey(name: 'deposit_refunds')
  final String depositRefunds;
  final String tips;

  Map<String, Object?> toJson() => _$RizqimBreakdown4ToJson(this);
}
