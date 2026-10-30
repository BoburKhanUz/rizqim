// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rizqim_breakdown3.g.dart';

@JsonSerializable()
class RizqimBreakdown3 {
  const RizqimBreakdown3({
    required this.payments,
    required this.deposits,
    required this.repayments,
    required this.refunds,
    required this.depositRefunds,
    required this.tips,
  });

  factory RizqimBreakdown3.fromJson(Map<String, Object?> json) =>
      _$RizqimBreakdown3FromJson(json);

  final String payments;
  final String deposits;
  final String repayments;
  final String refunds;
  @JsonKey(name: 'deposit_refunds')
  final String depositRefunds;
  final String tips;

  Map<String, Object?> toJson() => _$RizqimBreakdown3ToJson(this);
}
