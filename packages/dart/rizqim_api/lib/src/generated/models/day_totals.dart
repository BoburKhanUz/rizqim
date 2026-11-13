// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'day_totals.g.dart';

@JsonSerializable()
class DayTotals {
  const DayTotals({
    required this.date,
    required this.amount,
    required this.meals,
  });

  factory DayTotals.fromJson(Map<String, Object?> json) =>
      _$DayTotalsFromJson(json);

  final String date;
  final String amount;
  final int meals;

  Map<String, Object?> toJson() => _$DayTotalsToJson(this);
}
