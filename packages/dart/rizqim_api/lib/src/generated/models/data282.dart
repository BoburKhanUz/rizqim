// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account4.dart';
import 'rows15.dart';
import 'day_totals.dart';

part 'data282.g.dart';

@JsonSerializable()
class Data282 {
  const Data282({
    required this.account,
    required this.month,
    required this.from,
    required this.to,
    required this.days,
    required this.rows,
    required this.dayTotals,
    required this.total,
    required this.meals,
    required this.repaidTotal,
    required this.monthlyLimit,
    required this.balance,
    required this.branchId,
    required this.masked,
  });

  factory Data282.fromJson(Map<String, Object?> json) =>
      _$Data282FromJson(json);

  final Account4 account;
  final String month;
  final String from;
  final String to;
  final List<String> days;
  final List<Rows15> rows;
  @JsonKey(name: 'day_totals')
  final List<DayTotals> dayTotals;
  final String total;
  final int meals;
  @JsonKey(name: 'repaid_total')
  final String repaidTotal;
  @JsonKey(name: 'monthly_limit')
  final String? monthlyLimit;
  final String balance;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final bool masked;

  Map<String, Object?> toJson() => _$Data282ToJson(this);
}
