// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account4.dart';
import 'rows3.dart';
import 'day_totals.dart';

part 'data231.g.dart';

@JsonSerializable()
class Data231 {
  const Data231({
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
  });

  factory Data231.fromJson(Map<String, Object?> json) =>
      _$Data231FromJson(json);

  final Account4 account;
  final String month;
  final String from;
  final String to;
  final List<String> days;
  final List<Rows3> rows;
  @JsonKey(name: 'day_totals')
  final List<DayTotals> dayTotals;
  final String total;
  final int meals;
  @JsonKey(name: 'repaid_total')
  final String repaidTotal;
  @JsonKey(name: 'monthly_limit')
  final String? monthlyLimit;
  final String balance;

  Map<String, Object?> toJson() => _$Data231ToJson(this);
}
