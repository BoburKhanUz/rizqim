// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'member2.g.dart';

@JsonSerializable()
class Member2 {
  const Member2({
    required this.amount,
    required this.meals,
    required this.dailyLimit,
    required this.dailyAvailable,
    required this.mealsPerDay,
    required this.mealsLeft,
  });

  factory Member2.fromJson(Map<String, Object?> json) =>
      _$Member2FromJson(json);

  final String amount;
  final int meals;
  @JsonKey(name: 'daily_limit')
  final String? dailyLimit;
  @JsonKey(name: 'daily_available')
  final String? dailyAvailable;
  @JsonKey(name: 'meals_per_day')
  final int? mealsPerDay;
  @JsonKey(name: 'meals_left')
  final int? mealsLeft;

  Map<String, Object?> toJson() => _$Member2ToJson(this);
}
