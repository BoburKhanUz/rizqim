// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'pool_periods2.g.dart';

@JsonSerializable()
class PoolPeriods2 {
  const PoolPeriods2({required this.key, required this.label});

  factory PoolPeriods2.fromJson(Map<String, Object?> json) =>
      _$PoolPeriods2FromJson(json);

  final String key;
  final String label;

  Map<String, Object?> toJson() => _$PoolPeriods2ToJson(this);
}
