// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rizqim_breakdown.g.dart';

@JsonSerializable()
class RizqimBreakdown {
  const RizqimBreakdown({
    required this.payments,
    required this.deposits,
    required this.repayments,
    required this.refunds,
    required this.depositRefunds,
  });

  factory RizqimBreakdown.fromJson(Map<String, Object?> json) =>
      _$RizqimBreakdownFromJson(json);

  final String payments;
  final String deposits;
  final String repayments;
  final String refunds;
  @JsonKey(name: 'deposit_refunds')
  final String depositRefunds;

  Map<String, Object?> toJson() => _$RizqimBreakdownToJson(this);
}
