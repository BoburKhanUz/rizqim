// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'current_period2.g.dart';

@JsonSerializable()
class CurrentPeriod2 {
  const CurrentPeriod2({
    required this.period,
    required this.start,
    required this.end,
  });

  factory CurrentPeriod2.fromJson(Map<String, Object?> json) =>
      _$CurrentPeriod2FromJson(json);

  final String period;
  final String start;
  final String end;

  Map<String, Object?> toJson() => _$CurrentPeriod2ToJson(this);
}
