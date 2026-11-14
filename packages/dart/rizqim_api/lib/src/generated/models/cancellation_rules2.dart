// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'cancellation_rules2.g.dart';

@JsonSerializable()
class CancellationRules2 {
  const CancellationRules2({
    required this.minDays,
    required this.retainedPercent,
  });

  factory CancellationRules2.fromJson(Map<String, Object?> json) =>
      _$CancellationRules2FromJson(json);

  @JsonKey(name: 'min_days')
  final int minDays;
  @JsonKey(name: 'retained_percent')
  final String retainedPercent;

  Map<String, Object?> toJson() => _$CancellationRules2ToJson(this);
}
