// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'pool_periods.g.dart';

@JsonSerializable()
class PoolPeriods {
  const PoolPeriods({required this.key, required this.label});

  factory PoolPeriods.fromJson(Map<String, Object?> json) =>
      _$PoolPeriodsFromJson(json);

  final String key;
  final String label;

  Map<String, Object?> toJson() => _$PoolPeriodsToJson(this);
}
