// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'cancellation_rules3.g.dart';

@JsonSerializable()
class CancellationRules3 {
  const CancellationRules3({
    required this.minDays,
    required this.retainedPercent,
  });

  factory CancellationRules3.fromJson(Map<String, Object?> json) =>
      _$CancellationRules3FromJson(json);

  @JsonKey(name: 'min_days')
  final int minDays;
  @JsonKey(name: 'retained_percent')
  final num retainedPercent;

  Map<String, Object?> toJson() => _$CancellationRules3ToJson(this);
}
