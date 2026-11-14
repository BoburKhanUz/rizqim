// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'rows4.dart';

part 'data226.g.dart';

@JsonSerializable()
class Data226 {
  const Data226({
    required this.daysBefore,
    required this.rulePercent,
    required this.paid,
    required this.computedRetained,
    required this.retained,
    required this.refund,
    required this.overridden,
    required this.cashRefund,
    required this.transferRefund,
    required this.rows,
  });

  factory Data226.fromJson(Map<String, Object?> json) =>
      _$Data226FromJson(json);

  @JsonKey(name: 'days_before')
  final int daysBefore;
  @JsonKey(name: 'rule_percent')
  final String rulePercent;
  final String paid;
  @JsonKey(name: 'computed_retained')
  final String computedRetained;
  final String retained;
  final String refund;
  final bool overridden;
  @JsonKey(name: 'cash_refund')
  final String cashRefund;
  @JsonKey(name: 'transfer_refund')
  final String transferRefund;
  final List<Rows4> rows;

  Map<String, Object?> toJson() => _$Data226ToJson(this);
}
