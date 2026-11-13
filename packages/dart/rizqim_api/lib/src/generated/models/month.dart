// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'month.g.dart';

@JsonSerializable()
class Month {
  const Month({
    required this.amount,
    required this.monthlyLimit,
    required this.available,
  });

  factory Month.fromJson(Map<String, Object?> json) => _$MonthFromJson(json);

  final String amount;
  @JsonKey(name: 'monthly_limit')
  final String? monthlyLimit;
  final String? available;

  Map<String, Object?> toJson() => _$MonthToJson(this);
}
